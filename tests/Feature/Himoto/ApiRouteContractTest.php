<?php

namespace Tests\Feature\Himoto;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ApiRouteContractTest extends TestCase
{
    /**
     * Test public health endpoint returns 200 with service information.
     */
    public function testHealthEndpointIsPublic()
    {
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'himoto-api'
        ]);
    }

    /**
     * Test verify-token and auth-guarded endpoints return HTTP 401 when accessed without JWT token.
     */
    public function testVerifyTokenRequiresAuth()
    {
        $response = $this->getJson('/api/verify-token');
        $this->assertEquals(
            401,
            $response->getStatusCode(),
            "Route /api/verify-token must return HTTP 401 Unauthenticated when called without JWT Bearer token"
        );
    }

    /**
     * Test auth controller protected actions return HTTP 401 when unauthenticated.
     */
    public function testAuthActionsRequireAuthentication()
    {
        $responseLogout = $this->postJson('/api/auth/logout');
        $this->assertEquals(401, $responseLogout->getStatusCode());

        $responseChangePass = $this->postJson('/api/auth/change-pass');
        $this->assertEquals(401, $responseChangePass->getStatusCode());
    }

    /**
     * Test login endpoint validates required email and password fields.
     */
    public function testLoginEndpointValidatesInput()
    {
        $response = $this->postJson('/api/auth/login', []);
        $response->assertStatus(422);
        $response->assertJsonStructure(['email', 'password']);
    }

    /**
     * Test that all critical HIMOTO business routes are registered in Laravel RouteCollection.
     */
    public function testCoreRoutesRegisteredInRouter()
    {
        $allRoutes = collect(Route::getRoutes()->getRoutes())->map(function ($r) {
            return $r->uri();
        })->toArray();

        $expectedUris = [
            'api/health',
            'api/verify-token',
            'api/auth/login',
            'api/auth/stores/all',
            'api/auth/vehicle/vehicles',
            'api/auth/customers',
            'api/auth/order/car-rental',
            'api/auth/leads',
            'api/auth/maintenance-schedules',
            'api/auth/banks/all',
            'api/auth/cash/all',
            'api/auth/dashboard/report'
        ];

        foreach ($expectedUris as $uri) {
            $this->assertContains(
                $uri,
                $allRoutes,
                "API route '{$uri}' must be registered in the Laravel Route collection"
            );
        }
    }
}
