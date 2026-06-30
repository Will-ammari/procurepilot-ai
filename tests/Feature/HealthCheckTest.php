<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_application_dependencies(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'data' => [
                    'service',
                    'environment',
                    'status',
                    'checked_at',
                    'checks' => [
                        'database' => ['status'],
                        'cache' => ['status'],
                        'queue' => ['status', 'message'],
                    ],
                ],
            ]);
    }
}
