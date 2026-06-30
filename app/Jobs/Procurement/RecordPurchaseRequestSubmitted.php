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

class RecordPurchaseRequestSubmitted implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $purchaseRequestId,
        public readonly int $submittedByUserId,
    ) {}

    public function handle(ActivityLogService $activityLogService): void
    {
        $purchaseRequest = PurchaseRequest::query()->find($this->purchaseRequestId);
        $user = User::query()->find($this->submittedByUserId);

        if (! $purchaseRequest instanceof PurchaseRequest || ! $user instanceof User) {
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
}
