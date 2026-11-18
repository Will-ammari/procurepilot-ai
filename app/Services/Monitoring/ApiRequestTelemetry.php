<?php

namespace App\Services\Monitoring;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kernel-level completion event sees the FINAL response code, including
 * exceptions rendered to 401/403/404/422/500 outside route middleware.
 */
class ApiRequestTelemetry
{
    public function __construct(private readonly OperationalMetrics $metrics) {}

    public function handle(RequestHandled $event): void
    {
        $request = $event->request;
        $started = $request->attributes->get('request_started_ns');
        if (! $request->is('api/*') || ! is_int($started)) {
            return;
        }

        $requestId = $request->attributes->get('request_id');
        $traceId = $request->attributes->get('trace_id');
        $traceparent = $request->attributes->get('traceparent');
        // Middleware may have thrown before attaching headers. Finish them here.
        if (is_string($requestId)) {
            $event->response->headers->set('X-Request-Id', $requestId);
        }
        if (is_string($traceparent)) {
            $event->response->headers->set('traceparent', $traceparent);
        }

        $status = $event->response->getStatusCode();
        $durationMs = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
        $this->metrics->count('http_total');
        if ($status >= 500) {
            $this->metrics->count('http_5xx');
        } elseif ($status >= 400) {
            $this->metrics->count('http_4xx');
        }
        $this->metrics->observe('http_duration_ms', $durationMs);

        try {
            if ($status >= 500 || random_int(1, 100) <= (int) config('operations.request_trace_sample_percent', 10)) {
                Log::info('api.request.completed', [
                    'request_id' => is_string($requestId) ? $requestId : null,
                    'trace_id' => is_string($traceId) ? $traceId : null,
                    'method' => $request->method(),
                    'route' => $request->route()?->getName() ?? 'unnamed',
                    'status' => $status,
                    'duration_ms' => $durationMs,
                ]);
            }
        } catch (Throwable) {
            // Logs and metrics must not turn a valid API response into a 500.
        }
    }
}
