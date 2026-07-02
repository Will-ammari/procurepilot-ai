<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *     status: string,
     *     checks: array<string, array{status: string, message?: string, latency_ms?: float, connection?: string}>
     * }
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->timed(fn (): array => $this->checkDatabase()),
            'cache' => $this->timed(fn (): array => $this->checkCache()),
            'redis' => $this->timed(fn (): array => $this->checkRedis()),
            'queue' => $this->checkQueueConfiguration(),
        ];

        $isHealthy = collect($checks)
            ->every(fn (array $check): bool => $check['status'] === 'ok');

        return [
            'status' => $isHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    /** @return array{status: string, message?: string, connection?: string} */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'connection' => DB::getDefaultConnection(),
            ];
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /** @return array{status: string, message?: string, connection?: string} */
    private function checkCache(): array
    {
        try {
            $key = 'health:'.config('app.name', 'procurepilot').':'.bin2hex(random_bytes(6));

            Cache::put($key, 'ok', now()->addMinute());

            return Cache::pull($key) === 'ok'
                ? ['status' => 'ok', 'connection' => (string) config('cache.default')]
                : ['status' => 'failed', 'message' => 'Cache read/write check failed.'];
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /** @return array{status: string, message?: string, connection?: string} */
    private function checkRedis(): array
    {
        if (config('queue.default') !== 'redis' && config('cache.default') !== 'redis') {
            return [
                'status' => 'ok',
                'message' => 'Redis is not required by the current cache or queue configuration.',
            ];
        }

        try {
            $pong = Redis::connection()->ping();

            if ($pong === true || $pong === 'PONG' || $pong === '+PONG') {
                return [
                    'status' => 'ok',
                    'connection' => (string) config('database.redis.client'),
                ];
            }

            return ['status' => 'failed', 'message' => 'Unexpected Redis ping response.'];
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /** @return array{status: string, message?: string, connection?: string} */
    private function checkQueueConfiguration(): array
    {
        $connection = config('queue.default');

        if (! is_string($connection) || $connection === '') {
            return [
                'status' => 'failed',
                'message' => 'Queue connection is not configured.',
            ];
        }

        return [
            'status' => 'ok',
            'connection' => $connection,
            'message' => "Queue connection [{$connection}] is configured.",
        ];
    }

    /**
     * @param  callable(): array{status: string, message?: string, connection?: string}  $callback
     * @return array{status: string, message?: string, latency_ms?: float, connection?: string}
     */
    private function timed(callable $callback): array
    {
        $startedAt = microtime(true);
        $result = $callback();
        $result['latency_ms'] = round((microtime(true) - $startedAt) * 1000, 2);

        return $result;
    }

    /** @return array{status: string, message: string} */
    private function failed(Throwable $exception): array
    {
        return [
            'status' => 'failed',
            'message' => $exception->getMessage(),
        ];
    }
}
