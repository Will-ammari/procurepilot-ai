<?php

namespace Tests\Feature;

use App\Services\Monitoring\HealthCheckService;
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
                    'status',
                    'checked_at',
                    'checks' => [
                        'database' => ['status'],
                        'cache' => ['status'],
                        'redis' => ['status'],
                        'queue' => ['status'],
                    ],
                ],
            ]);
    }

    public function test_public_health_response_does_not_expose_dependency_errors(): void
    {
        $healthCheck = $this->createMock(HealthCheckService::class);
        $healthCheck->expects($this->once())
            ->method('run')
            ->willReturn([
                'status' => 'degraded',
                'checks' => [
                    'database' => ['status' => 'failed', 'message' => 'secret-host.internal password=test'],
                    'cache' => ['status' => 'ok'],
                    'redis' => ['status' => 'ok'],
                    'queue' => ['status' => 'ok'],
                ],
            ]);

        $this->app->instance(HealthCheckService::class, $healthCheck);

        $response = $this->getJson('/api/v1/health')->assertStatus(503);
        $response->assertJsonPath('data.checks.database.status', 'failed');
        $response->assertDontSee('secret-host.internal');
        $response->assertJsonMissingPath('data.environment');
    }
}
