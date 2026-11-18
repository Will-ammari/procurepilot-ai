<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use App\Services\Monitoring\OperationalMetrics;
use Illuminate\Console\Command;

class ShowOperationalMetrics extends Command
{
    protected $signature = 'ops:metrics {--minutes=5 : Minutes of best-effort counters to aggregate (1-60)}';

    protected $description = 'Show safe operational counters and outbox backlog (internal CLI only)';

    public function handle(OperationalMetrics $metrics): int
    {
        $minutes = max(1, min((int) $this->option('minutes'), 60));
        $pending = OutboxMessage::query()->whereIn('status', [
            OutboxMessage::STATUS_PENDING,
            OutboxMessage::STATUS_PROCESSING,
        ])->count();
        $failed = OutboxMessage::query()->where('status', OutboxMessage::STATUS_FAILED)->count();
        $oldest = OutboxMessage::query()->whereIn('status', [
            OutboxMessage::STATUS_PENDING,
            OutboxMessage::STATUS_PROCESSING,
        ])->min('created_at');

        $this->line(json_encode([
            'window_minutes' => $minutes,
            'counters' => $metrics->snapshot($minutes),
            'outbox' => [
                'pending_or_processing' => $pending,
                'terminal_failures' => $failed,
                'oldest_pending_created_at' => $oldest,
            ],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
