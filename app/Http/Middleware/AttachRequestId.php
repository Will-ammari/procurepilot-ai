<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $traceId = $this->resolveTraceId($request);
        $spanId = bin2hex(random_bytes(8));
        $traceparent = '00-'.$traceId.'-'.$spanId.'-01';
        $request->attributes->set('request_started_ns', hrtime(true));
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('traceparent', $traceparent);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('traceparent', $traceparent);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');
        // Reject arbitrary client strings to avoid injected log fields/control chars.
        if (is_string($header) && preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $header) === 1) {
            return $header;
        }

        return (string) Str::uuid();
    }

    private function resolveTraceId(Request $request): string
    {
        $header = $request->headers->get('traceparent', '');
        if (is_string($header)
            && preg_match('/^00-([0-9a-f]{32})-[0-9a-f]{16}-[0-9a-f]{2}$/D', $header, $matches) === 1
            && $matches[1] !== str_repeat('0', 32)) {
            return $matches[1];
        }

        return bin2hex(random_bytes(16));
    }
}
