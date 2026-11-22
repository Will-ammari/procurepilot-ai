<?php

namespace App\Services\Monitoring;

use App\Models\OutboxMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only, fixed-dimension operational state. Never queries business payloads. */
class OperationsSnapshot
{
    /** @return array{outbox: array<string, int>, oldest_undelivered_age_seconds: int, failed_jobs: int, queue_depth: array<string, int>} */
    public function collect(): array
    {
        $counts = array_fill_keys([
            OutboxMessage::STATUS_PENDING,
            OutboxMessage::STATUS_PROCESSING,
            OutboxMessage::STATUS_FAILED,
        ], 0);

        $rows = OutboxMessage::query()
            ->whereIn('status', array_keys($counts))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            $status = (string) $row->getAttribute('status');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->getAttribute('total');
            }
        }

        $oldest = OutboxMessage::query()
            ->whereIn('status', [OutboxMessage::STATUS_PENDING, OutboxMessage::STATUS_PROCESSING])
            ->min('created_at');

        $ageSeconds = $oldest === null ? 0 : max(0, (int) Carbon::parse($oldest)->diffInSeconds(now()));

        $failedJobs = -1; // -1 means unknown, never misrepresent a failed dependency as healthy.
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            // Failure in optional queue diagnostics should not hide the outbox signal.
        }

        $queueDepth = ['procurement-events' => -1, 'default' => -1];
        foreach (array_keys($queueDepth) as $queue) {
            try {
                $queueDepth[$queue] = (int) Queue::connection()->size($queue);
            } catch (Throwable) {
                // -1 indicates unavailable, not an empty queue.
            }
        }

        return [
            'outbox' => $counts,
            'oldest_undelivered_age_seconds' => $ageSeconds,
            'failed_jobs' => $failedJobs,
            'queue_depth' => $queueDepth,
        ];
    }
}
