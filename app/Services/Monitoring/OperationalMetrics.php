<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Throwable;

/** Bounded, best-effort counters and fixed-bucket latency histograms. No user IDs. */
class OperationalMetrics
{
    public const COUNTERS = [
        'http_total', 'http_4xx', 'http_5xx',
        'ai_success', 'ai_failure',
        'outbox_dispatched', 'outbox_retried', 'outbox_failed',
    ];

    public const HISTOGRAMS = ['http_duration_ms', 'ai_duration_ms'];

    public const BUCKETS = [50, 100, 250, 500, 1000, 2500, 5000];

    public function count(string $name): void
    {
        if (! in_array($name, self::COUNTERS, true)) {
            return;
        }

        $this->increment($name);
    }

    public function observe(string $name, int $milliseconds): void
    {
        if (! in_array($name, self::HISTOGRAMS, true)) {
            return;
        }

        $bucket = 'overflow';
        foreach (self::BUCKETS as $bound) {
            if ($milliseconds <= $bound) {
                $bucket = (string) $bound;
                break;
            }
        }

        $this->increment($name.'_bucket_'.$bucket);
    }

    /** @return array<string, int|array<int|string, int>> */
    public function snapshot(int $minutes = 5): array
    {
        $minutes = max(1, min($minutes, 60));
        $counters = array_fill_keys(self::COUNTERS, 0);
        $histograms = [];
        foreach (self::HISTOGRAMS as $name) {
            $histograms[$name] = array_fill_keys(array_map('strval', self::BUCKETS), 0) + ['overflow' => 0];
        }

        if (! config('operations.metrics_enabled', true)) {
            return array_merge($counters, $histograms);
        }

        try {
            for ($index = 0; $index < $minutes; $index++) {
                $minute = now()->subMinutes($index)->utc()->format('YmdHi');
                foreach (self::COUNTERS as $counter) {
                    $counters[$counter] += (int) Cache::get($this->key($minute, $counter), 0);
                }
                foreach (self::HISTOGRAMS as $name) {
                    foreach (array_keys($histograms[$name]) as $bucket) {
                        $histograms[$name][$bucket] += (int) Cache::get($this->key($minute, $name.'_bucket_'.$bucket), 0);
                    }
                }
            }
        } catch (Throwable) {
            // Monitoring degradation must not affect procurement writes or responses.
        }

        return array_merge($counters, $histograms);
    }

    private function increment(string $name): void
    {
        if (! config('operations.metrics_enabled', true)) {
            return;
        }

        try {
            $minute = now()->utc()->format('YmdHi');
            $key = $this->key($minute, $name);
            Cache::add($key, 0, now()->addHours(2));
            Cache::increment($key);
        } catch (Throwable) {
            // Best effort: Redis or cache failure must never fail the business operation.
        }
    }

    private function key(string $minute, string $name): string
    {
        return 'ops_metrics:v1:'.$minute.':'.$name;
    }
}
