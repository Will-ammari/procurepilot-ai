<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class QuoteAnalysisClient
{
    public function analyze(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.ai.url'), '/');
        $timeout = (int) config('services.ai.timeout', 5);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($baseUrl . '/analyze-quote', $payload);

        $response->throw();

        return $response->json();
    }
}
