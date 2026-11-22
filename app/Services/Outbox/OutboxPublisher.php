<?php

namespace App\Services\Outbox;

use App\Models\OutboxMessage;
use App\Services\Monitoring\OperationalMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * At-least-once publisher. A lease protects concurrent publishers; the consumer
 * must be idempotent because a process may crash after enqueue, before ACK.
 */
class OutboxPublisher
{
    private const MAX_BATCH = 100;

    private const MAX_ATTEMPTS = 12;

    private const LEASE_SECONDS = 90;

    public function __construct(
        private readonly OutboxTransport $transport,
        private readonly OperationalMetrics $metrics
    ) {}

    /** @return array{claimed: int, dispatched: int, retried: int, failed: int} */
    public function publish(int $limit = 50): array
    {
        $messages = $this->claim(max(1, min($limit, self::MAX_BATCH)));
        $result = ['claimed' => $messages->count(), 'dispatched' => 0, 'retried' => 0, 'failed' => 0];

        foreach ($messages as $message) {
            try {
                $this->transport->deliver($message);

                $updated = OutboxMessage::query()
                    ->whereKey($message->id)
                    ->where('status', OutboxMessage::STATUS_PROCESSING)
                    ->where('lease_token', $message->lease_token)
                    ->update([
                        'status' => OutboxMessage::STATUS_DISPATCHED,
                        'dispatched_at' => now(),
                        'reserved_until' => null,
                        'lease_token' => null,
                        'last_error_code' => null,
                    ]);

                $result['dispatched'] += $updated;
                if ($updated > 0) {
                    $this->metrics->count('outbox_dispatched');
                }
            } catch (Throwable $exception) {
                $terminal = $message->attempts >= self::MAX_ATTEMPTS;
                $updated = OutboxMessage::query()
                    ->whereKey($message->id)
                    ->where('status', OutboxMessage::STATUS_PROCESSING)
                    ->where('lease_token', $message->lease_token)
                    ->update([
                        'status' => $terminal ? OutboxMessage::STATUS_FAILED : OutboxMessage::STATUS_PENDING,
                        'next_attempt_at' => $terminal ? null : now()->addSeconds($this->backoff($message->attempts)),
                        'reserved_until' => null,
                        'lease_token' => null,
                        // Do not persist exception messages (may contain provider credentials).
                        'last_error_code' => substr($exception::class, 0, 160),
                    ]);

                if ($updated > 0) {
                    $result[$terminal ? 'failed' : 'retried']++;
                    $this->metrics->count($terminal ? 'outbox_failed' : 'outbox_retried');
                    Log::warning('Outbox delivery attempt failed.', [
                        'outbox_id' => $message->id,
                        'correlation_id' => $message->correlation_id,
                        'event_type' => $message->event_type,
                        'attempt' => $message->attempts,
                        'terminal' => $terminal,
                        'error_class' => $exception::class,
                    ]);
                }
            }
        }

        return $result;
    }

    /** @return Collection<int, OutboxMessage> */
    private function claim(int $limit): Collection
    {
        return DB::transaction(function () use ($limit): Collection {
            $now = now();
            $messages = OutboxMessage::query()
                ->where(function (Builder $query) use ($now): void {
                    $query->where(function (Builder $pending) use ($now): void {
                        $pending->where('status', OutboxMessage::STATUS_PENDING)
                            ->where('next_attempt_at', '<=', $now);
                    })->orWhere(function (Builder $expired) use ($now): void {
                        $expired->where('status', OutboxMessage::STATUS_PROCESSING)
                            ->where('reserved_until', '<=', $now);
                    });
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($messages as $message) {
                $message->update([
                    'status' => OutboxMessage::STATUS_PROCESSING,
                    'attempts' => $message->attempts + 1,
                    'lease_token' => (string) Str::uuid(),
                    'reserved_until' => now()->addSeconds(self::LEASE_SECONDS),
                ]);
            }

            return $messages;
        }, 3);
    }

    private function backoff(int $attempt): int
    {
        return min(3600, 15 * (2 ** min($attempt - 1, 8)));
    }
}
