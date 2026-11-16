<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class QuoteAnalysisClient
{
    public function analyze(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.ai.url'), '/');
        $timeout = max(1, (int) config('services.ai.timeout', 5));

        $response = Http::connectTimeout(min(3, $timeout))
            ->timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/analyze-quote', $payload);

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

        return $result;
    }
}
