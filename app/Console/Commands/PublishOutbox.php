<?php

namespace App\Console\Commands;

use App\Services\Outbox\OutboxPublisher;
use Illuminate\Console\Command;
use Throwable;

class PublishOutbox extends Command
{
    protected $signature = 'ops:outbox:publish {--limit=50 : Maximum messages per batch (1-100)} {--loop : Poll continuously} {--sleep=5 : Seconds between polls}';

    protected $description = 'Publish durable procurement events to the application queue';

    public function handle(OutboxPublisher $publisher): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $sleep = max(1, min(60, (int) $this->option('sleep')));

        do {
            try {
                $result = $publisher->publish($limit);
                if (! $this->option('loop') || $result['failed'] > 0) {
                    $this->line(json_encode($result, JSON_THROW_ON_ERROR));
                }
            } catch (Throwable $exception) {
                $this->error('Outbox polling failed: '.$exception::class);
                if (! $this->option('loop')) {
                    return self::FAILURE;
                }
            }

            if (! $this->option('loop')) {
                return self::SUCCESS;
            }

            sleep($sleep);
        } while (true);
    }
}
