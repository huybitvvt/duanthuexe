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

        $responseRegister = $this->postJson('/api/auth/register');
        $this->assertEquals(401, $responseRegister->getStatusCode());

        $responseTimezone = $this->getJson('/api/check-timezone');
        $this->assertEquals(401, $responseTimezone->getStatusCode());
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
            'api/auth/dashboard/report',
            'api/auth/report/kpi',
            'api/auth/accounting',
            'api/auth/hr/organization-chart',
            'api/auth/hr/attendance',
            'api/auth/customer-reminders/action-list',
            'api/auth/gps/overview'
        ];

        foreach ($expectedUris as $uri) {
            $this->assertContains(
                $uri,
                $allRoutes,
                "API route '{$uri}' must be registered in the Laravel Route collection"
            );
        }
    }

    /**
     * Every newly added operational area must reject anonymous requests before
     * its schema or business service is evaluated.
     */
    public function testOperationalRoutesRequireAuthentication()
    {
        $protectedUris = [
            '/api/auth/report/kpi',
            '/api/auth/accounting',
            '/api/auth/hr/organization-chart',
            '/api/auth/hr/attendance',
            '/api/auth/customer-reminders/action-list',
            '/api/auth/gps/overview',
        ];

        foreach ($protectedUris as $uri) {
            $this->getJson($uri)->assertStatus(401);
        }
    }

    /**
     * Resolve the route action explicitly so a missing controller import is
     * caught during tests instead of during route caching/deployment.
     */
    public function testReminderAndGpsRoutesResolveTheirController()
    {
        $actions = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return in_array($route->uri(), [
                    'api/auth/customer-reminders/action-list',
                    'api/auth/gps/overview',
                ], true);
            })
            ->map(function ($route) {
                return $route->getActionName();
            })
            ->values()
            ->all();

        $this->assertContains(
            'App\\Http\\Controllers\\CustomerReminderController@actionList',
            $actions
        );
        $this->assertContains(
            'App\\Http\\Controllers\\CustomerReminderController@gpsOverview',
            $actions
        );
    }
}
