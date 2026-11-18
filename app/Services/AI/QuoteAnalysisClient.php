<?php

namespace App\Services\AI;

use App\Services\Monitoring\OperationalMetrics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class QuoteAnalysisClient
{
    public function __construct(private readonly OperationalMetrics $metrics) {}

    public function analyze(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.ai.url'), '/');
        $timeout = max(1, (int) config('services.ai.timeout', 5));
        $started = hrtime(true);
        $success = false;
        $traceparent = request()->attributes->get('traceparent');
        $requestId = request()->attributes->get('request_id');

        try {
            $request = Http::connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->acceptJson()
                ->asJson();

            if (is_string($traceparent) && preg_match('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/D', $traceparent) === 1) {
                $request = $request->withHeaders(['traceparent' => $traceparent]);
            }
            if (is_string($requestId) && preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $requestId) === 1) {
                $request = $request->withHeaders(['X-Request-Id' => $requestId]);
            }

            $response = $request->post($baseUrl.'/analyze-quote', $payload);
            $response->throw();
            $result = $response->json();

            if (! is_array($result)
                || ($result['quote_id'] ?? null) !== $payload['quote_id']
                || ! is_string($result['summary'] ?? null)
                || trim($result['summary']) === ''
                || ! is_array($result['hidden_costs_notes'] ?? null)
                || count(array_filter($result['hidden_costs_notes'], 'is_string')) !== count($result['hidden_costs_notes'])
                || ! in_array($result['risk_level'] ?? null, ['low', 'medium', 'high'], true)
                || ! is_numeric($result['confidence_score'] ?? null)
                || ! is_finite((float) $result['confidence_score'])
                || (float) $result['confidence_score'] < 0
                || (float) $result['confidence_score'] > 1
                || ! is_string($result['recommendation'] ?? null)) {
                throw new UnexpectedValueException('AI provider returned an invalid quote analysis response.');
            }

            $success = true;

            return $result;
        } finally {
            $elapsedMs = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
            $this->metrics->count($success ? 'ai_success' : 'ai_failure');
            $this->metrics->observe('ai_duration_ms', $elapsedMs);
            try {
                Log::info('ai.quote_analysis.completed', [
                    'trace_id' => request()->attributes->get('trace_id'),
                    'request_id' => is_string($requestId) ? $requestId : null,
                    'duration_ms' => $elapsedMs,
                    'success' => $success,
                ]);
            } catch (Throwable) {
                // Never mask an AI result or provider failure with logging failures.
            }
        }
    }
}
