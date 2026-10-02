<?php

namespace Tests\Feature\Himoto;

use App\Models\User;
use Tests\TestCase;

class ApiInputBoundaryTest extends TestCase
{
    public function testMalformedQueriesAreRejectedBeforeDatabaseQueries(): void
    {
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs(new User(['id' => 1, 'role_id' => 1, 'status' => 'active']), 'api');
        foreach ([
            '/api/auth/accounting/journal-entries?per_page=nonsense',
            '/api/auth/daily-cash-registers/history?from_date=not-a-date',
            '/api/auth/daily-cash-registers/history?from_date=2026-10-02&to_date=2026-10-01',
            '/api/auth/hr/duty-schedules?date=not-a-date',
            '/api/auth/report/detail-report-new?start_date=not-a-date&end_date=2026-10-02',
            '/api/auth/report/detail-report-day-by-day?start_date=2026-10-02&end_date=2026-10-01',
            '/api/auth/report/detail-report?dates[]=2026-10-02',
            '/api/auth/transactions?start_date=not-a-date',
            '/api/auth/vehicle/vehicles_with_revenue?start_date=not-a-date',
            '/api/auth/customers?limit=-1',
            '/api/auth/vehicle/vehicles?page=0',
            '/api/auth/vehicle/vehicles?store_id=not-an-id',
            '/api/auth/leads?source=123',
            '/api/auth/report/detail-report-new?source[]=not-an-id',
        ] as $url) {
            $this->getJson($url)->assertStatus(422)->assertJsonStructure(['errors']);
        }
    }

    public function testMalformedResourceIdentifiersCannotReachControllers(): void
    {
        foreach (['accounting/general-ledger', 'accounting/journal-entries', 'banks', 'cash', 'customers',
            'leads', 'order/car-rental', 'lease-contracts', 'maintenance-schedules'] as $path) {
            $this->getJson('/api/auth/'.$path.'/not-an-id')->assertStatus(404);
        }
        $this->getJson('/api/auth/gps/devices/not-an-id/history')->assertStatus(404);
    }

    public function testUnauthenticatedRequestsAreDeniedBeforeBindingsAndQueryValidation(): void
    {
        foreach (['/api/auth/customers/999', '/api/auth/banks/999', '/api/auth/order/car-rental/999',
            '/api/auth/customers?limit=nonsense'] as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    }
}
