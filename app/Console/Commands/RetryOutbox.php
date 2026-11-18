<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryOutbox extends Command
{
    protected $signature = 'ops:outbox:retry {id : Failed outbox message ID}';

    protected $description = 'Manually retry a terminal outbox failure after investigating its cause';

    public function handle(): int
    {
        $requeued = DB::transaction(function (): int {
            return OutboxMessage::query()
                ->whereKey((int) $this->argument('id'))
                ->where('status', OutboxMessage::STATUS_FAILED)
                ->update([
                    'status' => OutboxMessage::STATUS_PENDING,
                    'attempts' => 0,
                    'next_attempt_at' => now(),
                    'last_error_code' => null,
                ]);
        });

        $this->info($requeued === 1 ? 'Failed event requeued.' : 'No failed event with that ID.');

        return $requeued === 1 ? self::SUCCESS : self::FAILURE;
    }
}
