<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use Illuminate\Console\Command;

class PruneOutbox extends Command
{
    protected $signature = 'ops:outbox:prune {--days=30 : Retain dispatched events for this many days (min 7)} {--force : Actually delete eligible dispatched events}';

    protected $description = 'Prune only acknowledged outbox rows; dry-run by default';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $query = OutboxMessage::query()
            ->where('status', OutboxMessage::STATUS_DISPATCHED)
            ->where('dispatched_at', '<', $cutoff);

        $count = (clone $query)->count();
        if (! $this->option('force')) {
            $this->info("DRY RUN: {$count} acknowledged events older than {$days} days eligible for pruning.");

            return self::SUCCESS;
        }

        $deleted = 0;
        // Small batches avoid oversized DELETE transactions and table lock time.
        do {
            $ids = (clone $query)->orderBy('id')->limit(500)->pluck('id')->all();
            if ($ids === []) {
                break;
            }
            $deleted += OutboxMessage::query()->whereIn('id', $ids)
                ->where('status', OutboxMessage::STATUS_DISPATCHED)
                ->delete();
        } while (true);

        $this->info("Pruned {$deleted} acknowledged outbox events.");

        return self::SUCCESS;
    }
}
