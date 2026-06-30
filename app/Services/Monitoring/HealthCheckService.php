<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *     status: string,
     *     checks: array<string, array{status: string, message?: string}>
     * }
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueueConfiguration(),
        ];

        $isHealthy = collect($checks)
            ->every(fn (array $check): bool => $check['status'] === 'ok');

        return [
            'status' => $isHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    /** @return array{status: string, message?: string} */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /** @return array{status: string, message?: string} */
    private function checkCache(): array
    {
        try {
            $key = 'health:'.config('app.name', 'procurepilot');

            Cache::put($key, 'ok', now()->addMinute());

            return Cache::get($key) === 'ok'
                ? ['status' => 'ok']
                : ['status' => 'failed', 'message' => 'Cache read/write check failed.'];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /** @return array{status: string, message?: string} */
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
            'message' => "Queue connection [{$connection}] is configured.",
        ];
    }
}
