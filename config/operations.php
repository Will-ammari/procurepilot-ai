<?php

return [
    // The metrics are bounded, best-effort cache counters, not an SLO database.
    'metrics_enabled' => (bool) env('OPS_METRICS_ENABLED', true),
    // Record every 5xx; sample otherwise to control log volume.
    'request_trace_sample_percent' => max(0, min(100, (int) env('OPS_TRACE_SAMPLE_PERCENT', 10))),
];
