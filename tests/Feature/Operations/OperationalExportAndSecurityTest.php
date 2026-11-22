<?php

namespace Tests\Feature\Operations;

use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use App\Models\OutboxMessage;
use App\Services\Monitoring\OperationalMetrics;
use App\Services\Monitoring\OperationsSnapshot;
use App\Services\Monitoring\PrometheusMetricsFormatter;
use App\Services\Outbox\OutboxPublisher;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class OperationalExportAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_api_responses_receive_security_headers_without_changing_contract(): void
    {
        $response = $this->getJson('/api/v1/vendors')->assertUnauthorized();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertDontSee('APP_KEY');
    }

    public function test_metrics_formatter_only_includes_fixed_allowlisted_labels_and_valid_buckets(): void
    {
        $window = app(OperationalMetrics::class)->snapshot(1);
        $window['http_total'] = 7;
        $window['http_5xx'] = 2;
        $window['http_duration_ms'] = ['50' => 1, '100' => 2, 'overflow' => 1];
        $state = [
            'outbox' => ['pending' => 3, 'processing' => 1, 'dispatched' => 0, 'failed' => 0],
            'oldest_undelivered_age_seconds' => 95,
            'failed_jobs' => -1,
            'queue_depth' => ['default' => 0, 'procurement-events' => 1],
        ];
        $output = app(PrometheusMetricsFormatter::class)->format($window, $state, 5, 1770000000);
        $this->assertStringContainsString('procurepilot_http_requests_window{status_class="5xx"} 2', $output);
        $this->assertStringContainsString('procurepilot_duration_window_bucket{operation="http",le="0.1"} 3', $output);
        $this->assertStringContainsString('procurepilot_duration_window_bucket{operation="http",le="+Inf"} 4', $output);
        $this->assertStringContainsString('procurepilot_oldest_undelivered_age_seconds 95', $output);
        $this->assertStringNotContainsString('tenant_id', $output);
        $this->assertStringNotContainsString('password', $output);
    }

    public function test_snapshot_reads_empty_database_without_loading_payloads(): void
    {
        $snapshot = app(OperationsSnapshot::class)->collect();
        $this->assertSame(0, $snapshot['outbox'][OutboxMessage::STATUS_PENDING]);
        $this->assertSame(0, $snapshot['oldest_undelivered_age_seconds']);
        $this->assertArrayHasKey('procurement-events', $snapshot['queue_depth']);
    }

    public function test_outbox_correlation_is_passed_to_job_without_changing_delivery_semantics(): void
    {
        Queue::fake();
        $scenario = new ProcurementTestScenario;
        $purchaseRequest = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($purchaseRequest, $scenario->requester);

        $correlationId = 'a6e5f2bc-d8a3-44e0-98a6-263dc8484099';
        OutboxMessage::query()->update(['correlation_id' => $correlationId]);
        $this->assertSame(1, app(OutboxPublisher::class)->publish()['dispatched']);
        Queue::assertPushed(
            RecordPurchaseRequestSubmitted::class,
            fn (RecordPurchaseRequestSubmitted $job): bool => $job->correlationId === $correlationId
        );
    }

    public function test_security_preflight_is_strict_when_production_controls_are_missing(): void
    {
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost');
        $this->artisan('ops:security:audit --strict')->assertExitCode(1);
    }
}
