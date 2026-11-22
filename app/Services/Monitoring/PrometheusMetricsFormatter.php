<?php

namespace App\Services\Monitoring;

/**
 * Prometheus text exposition for periodically collected gauges.
 * Existing cache-backed counters reset per minute, so they MUST NOT be
 * exported as monotonic _total counters or used with rate().
 * All labels are fixed allow-lists: no tenant, URL, user or exception values.
 */
class PrometheusMetricsFormatter
{
    /**
     * @param  array<string, int|array<int|string, int>>  $window
     * @param  array{outbox: array<string, int>, oldest_undelivered_age_seconds: int, failed_jobs: int, queue_depth: array<string, int>}  $state
     */
    public function format(array $window, array $state, int $minutes, int $timestamp): string
    {
        $lines = [
            '# HELP procurepilot_export_timestamp_seconds Unix timestamp of the latest successful collection.',
            '# TYPE procurepilot_export_timestamp_seconds gauge',
            'procurepilot_export_timestamp_seconds '.$timestamp,
            '# HELP procurepilot_window_minutes Rolling aggregation lookback in minutes.',
            '# TYPE procurepilot_window_minutes gauge',
            'procurepilot_window_minutes '.$minutes,
            '# HELP procurepilot_http_requests_window API requests in the rolling window, NOT a monotonic counter.',
            '# TYPE procurepilot_http_requests_window gauge',
        ];
        foreach (['all' => 'http_total', '4xx' => 'http_4xx', '5xx' => 'http_5xx'] as $label => $key) {
            $lines[] = 'procurepilot_http_requests_window{status_class="'.$label.'"} '.(int) ($window[$key] ?? 0);
        }

        $lines[] = '# HELP procurepilot_events_window Count of outcomes recorded within the rolling window.';
        $lines[] = '# TYPE procurepilot_events_window gauge';
        foreach (['ai_success', 'ai_failure', 'outbox_dispatched', 'outbox_retried', 'outbox_failed'] as $key) {
            $lines[] = 'procurepilot_events_window{event="'.$key.'"} '.(int) ($window[$key] ?? 0);
        }

        $lines[] = '# HELP procurepilot_duration_window_bucket Completed duration observations at or below le seconds during the window.';
        $lines[] = '# TYPE procurepilot_duration_window_bucket gauge';
        foreach (['http_duration_ms' => 'http', 'ai_duration_ms' => 'ai'] as $key => $operation) {
            $counts = is_array($window[$key] ?? null) ? $window[$key] : [];
            $cumulative = 0;
            foreach (OperationalMetrics::BUCKETS as $bound) {
                $cumulative += (int) ($counts[(string) $bound] ?? 0);
                $lines[] = 'procurepilot_duration_window_bucket{operation="'.$operation.'",le="'.($bound / 1000).'"} '.$cumulative;
            }
            $cumulative += (int) ($counts['overflow'] ?? 0);
            $lines[] = 'procurepilot_duration_window_bucket{operation="'.$operation.'",le="+Inf"} '.$cumulative;
        }

        $lines[] = '# HELP procurepilot_outbox_messages Current outbox rows by delivery state.';
        $lines[] = '# TYPE procurepilot_outbox_messages gauge';
        foreach (['pending', 'processing', 'failed'] as $status) {
            $lines[] = 'procurepilot_outbox_messages{status="'.$status.'"} '.(int) ($state['outbox'][$status] ?? 0);
        }
        $lines[] = '# HELP procurepilot_oldest_undelivered_age_seconds Age of oldest pending or processing outbox message.';
        $lines[] = '# TYPE procurepilot_oldest_undelivered_age_seconds gauge';
        $lines[] = 'procurepilot_oldest_undelivered_age_seconds '.(int) $state['oldest_undelivered_age_seconds'];
        $lines[] = '# HELP procurepilot_failed_jobs Current failed job count; -1 means unavailable.';
        $lines[] = '# TYPE procurepilot_failed_jobs gauge';
        $lines[] = 'procurepilot_failed_jobs '.(int) $state['failed_jobs'];
        $lines[] = '# HELP procurepilot_queue_depth Current queue depth; -1 means unavailable.';
        $lines[] = '# TYPE procurepilot_queue_depth gauge';
        foreach (['default', 'procurement-events'] as $name) {
            $lines[] = 'procurepilot_queue_depth{queue="'.$name.'"} '.(int) ($state['queue_depth'][$name] ?? -1);
        }

        return implode("\n", $lines)."\n";
    }
}
