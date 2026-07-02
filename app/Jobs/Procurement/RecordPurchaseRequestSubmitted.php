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
    ) {
        $this->onQueue('procurement-events');
    }

    public function handle(ActivityLogService $activityLogService): void
    {
        $purchaseRequest = PurchaseRequest::query()->find($this->purchaseRequestId);
        $user = User::query()->find($this->submittedByUserId);

        if (! $purchaseRequest instanceof PurchaseRequest || ! $user instanceof User) {
            Log::warning('Skipped submitted purchase request activity log because related records were missing.', [
                'purchase_request_id' => $this->purchaseRequestId,
                'submitted_by_user_id' => $this->submittedByUserId,
                'job' => self::class,
            ]);

            return;
        }

        $activityLogService->log(
            event: ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED,
            user: $user,
            subject: $purchaseRequest,
            metadata: [
                'status' => PurchaseRequest::STATUS_SUBMITTED,
                'submitted_at' => now()->toISOString(),
                'processed_by' => self::class,
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to record submitted purchase request activity log.', [
            'purchase_request_id' => $this->purchaseRequestId,
            'submitted_by_user_id' => $this->submittedByUserId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
