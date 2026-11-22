<?php

namespace Tests\Feature\Operations;

use Tests\TestCase;

class ApiAuthenticationHardeningTest extends TestCase
{
    public function test_unauthenticated_api_without_accept_header_returns_json_401(): void
    {
        $this->get('/api/v1/purchase-requests')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']])
            ->assertDontSee('RouteNotFoundException');
    }

    public function test_unauthenticated_api_with_html_accept_still_returns_json_401(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get('/api/v1/vendors')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_api_errors_never_expose_exception_internals_even_if_debug_enabled(): void
    {
        config()->set('app.debug', true);

        $response = $this->get('/api/v1/purchase-requests')->assertUnauthorized();
        $response->assertJsonMissingPath('debug');
        $response->assertDontSee('trace');
        $response->assertDontSee('Illuminate\\');
        $response->assertDontSee('vendor/');
    }
}
