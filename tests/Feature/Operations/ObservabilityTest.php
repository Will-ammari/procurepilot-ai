<?php

namespace Tests\Feature\Operations;

use App\Services\AI\QuoteAnalysisClient;
use App\Services\Monitoring\OperationalMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_response_propagates_trace_id_but_generates_new_span(): void
    {
        $traceId = '0123456789abcdef0123456789abcdef';
        $parentSpan = '1111222233334444';
        $response = $this->withHeaders([
            'traceparent' => '00-'.$traceId.'-'.$parentSpan.'-01',
            'X-Request-Id' => 'integration-test-11',
        ])->getJson('/api/v1/health')->assertOk();

        $response->assertHeader('X-Request-Id', 'integration-test-11');
        $outgoing = (string) $response->headers->get('traceparent');
        $this->assertMatchesRegularExpression('/^00-'.$traceId.'-[0-9a-f]{16}-01$/', $outgoing);
        $this->assertNotSame('00-'.$traceId.'-'.$parentSpan.'-01', $outgoing);
    }

    public function test_rendered_validation_error_is_counted_as_client_error_not_server_error(): void
    {
        $response = $this->withHeader('X-Request-Id', 'validation-case-1')
            ->postJson('/api/v1/auth/login', [])
            ->assertStatus(422);
        $response->assertHeader('X-Request-Id', 'validation-case-1');
        $metrics = app(OperationalMetrics::class)->snapshot();
        $this->assertSame(1, $metrics['http_total']);
        $this->assertSame(1, $metrics['http_4xx']);
        $this->assertSame(0, $metrics['http_5xx']);
    }

    public function test_unsupported_traceparent_is_rejected_and_not_reflected(): void
    {
        $response = $this->withHeader('traceparent', 'attacker-controlled-value')
            ->getJson('/api/v1/health')->assertOk();
        $this->assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/',
            (string) $response->headers->get('traceparent')
        );
        $this->assertNotSame('attacker-controlled-value', $response->headers->get('traceparent'));
    }

    public function test_quote_analysis_propagates_context_without_logging_quote_payload(): void
    {
        $traceparent = '00-0123456789abcdef0123456789abcdef-aabbccddeeff0011-01';
        request()->attributes->set('traceparent', $traceparent);
        request()->attributes->set('request_id', 'operation-123');
        Http::fake(['*' => Http::response([
            'quote_id' => 77,
            'summary' => 'Review delivery terms',
            'hidden_costs_notes' => [],
            'risk_level' => 'low',
            'confidence_score' => 0.9,
            'recommendation' => 'Proceed to approval',
        ], 200)]);

        $this->assertSame(77, app(QuoteAnalysisClient::class)->analyze(['quote_id' => 77])['quote_id']);
        Http::assertSent(function ($request) use ($traceparent): bool {
            return $request->hasHeader('traceparent', $traceparent)
                && $request->hasHeader('X-Request-Id', 'operation-123');
        });
        $metrics = app(OperationalMetrics::class)->snapshot();
        $this->assertSame(1, $metrics['ai_success']);
    }

    public function test_metrics_failure_cannot_break_business_path(): void
    {
        $failingCache = new class
        {
            public int $attempts = 0;

            public function add(string $key, int $value, mixed $ttl): bool
            {
                $this->attempts++;
                throw new RuntimeException('Cache unavailable');
            }
        };
        Cache::swap($failingCache);

        app(OperationalMetrics::class)->count('http_total');
        $this->assertSame(1, $failingCache->attempts);
    }

    public function test_counters_use_bounded_dimensions_and_fixed_buckets(): void
    {
        $metrics = app(OperationalMetrics::class);
        $metrics->count('ai_failure');
        $metrics->observe('ai_duration_ms', 123);
        $metrics->count('unsafe_user_id_123');

        $snapshot = $metrics->snapshot();
        $this->assertSame(1, $snapshot['ai_failure']);
        $histogram = $snapshot['ai_duration_ms'];
        $this->assertIsArray($histogram);
        $this->assertSame(1, $histogram['250']);
        $this->assertSame(0, $histogram['100']);
    }
}
