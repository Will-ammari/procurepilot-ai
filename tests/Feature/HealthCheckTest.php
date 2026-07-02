<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_application_dependencies(): void
    {
        $this->withHeader('X-Request-Id', 'health-check-test')
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'health-check-test')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'data' => [
                    'service',
                    'environment',
                    'status',
                    'checked_at',
                    'checks' => [
                        'database' => ['status', 'latency_ms'],
                        'cache' => ['status', 'latency_ms'],
                        'redis' => ['status', 'latency_ms'],
                        'queue' => ['status', 'connection', 'message'],
                    ],
                ],
            ]);
    }
}
