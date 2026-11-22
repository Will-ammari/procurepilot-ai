<?php

namespace App\Jobs\Procurement;

use App\Models\ActivityLog;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Support\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RecordPurchaseRequestSubmitted implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 30;

    public function __construct(
        public readonly int $purchaseRequestId,
        public readonly int $submittedByUserId,
        public readonly ?string $submittedAt = null,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue('procurement-events');
    }

    public function handle(ActivityLogService $activityLogService): void
    {
        $purchaseRequest = PurchaseRequest::query()->find($this->purchaseRequestId);
        $user = User::query()->find($this->submittedByUserId);

        if (! $purchaseRequest instanceof PurchaseRequest) {
            // Never ACK a missing aggregate as a successful delivery. Retain the
            // failed job for investigation rather than silently losing the audit.
            throw new RuntimeException('Cannot record submission event: purchase request no longer exists.');
        }

        // Deleted users are valid: activity_logs.user_id is nullable. Preserve
        // the original actor ID in metadata for audit traceability.

        $activityLogService->log(
            event: ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED,
            user: $user,
            subject: $purchaseRequest,
            metadata: [
                'status' => PurchaseRequest::STATUS_SUBMITTED,
                'submitted_by_user_id' => $this->submittedByUserId,
                'submitted_at' => $this->submittedAt ?? now()->toISOString(),
                'processed_by' => self::class,
                'correlation_id' => $this->correlationId,
            ],
            idempotencyKey: 'purchase_request.submitted:'.$this->purchaseRequestId
        );

        try {
            Log::info('outbox.consumer.completed', [
                'event' => ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED,
                'correlation_id' => $this->correlationId,
                'outcome' => 'committed_or_idempotent',
            ]);
        } catch (Throwable) {
            // Logging failure cannot retroactively fail a committed audit event.
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to record submitted purchase request activity log.', [
            'purchase_request_id' => $this->purchaseRequestId,
            'submitted_by_user_id' => $this->submittedByUserId,
            'exception' => $exception::class,
            'correlation_id' => $this->correlationId,
        ]);
    }
}
