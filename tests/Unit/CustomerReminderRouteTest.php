<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CustomerReminderRouteTest extends TestCase
{
    public function testCustomerReminderMenuTargetHasARegisteredPage(): void
    {
        $root = dirname(__DIR__, 2);
        $router = file_get_contents($root . '/resources/js/src/router.js');
        $apiRoutes = file_get_contents($root . '/routes/api.php');
        $pagePath = $root . '/resources/js/src/view/pages/customer-reminders/CustomerReminderIndex.vue';

        $this->assertStringContainsString('path: "/customer-reminders"', $router);
        $this->assertStringContainsString('CustomerReminderIndex.vue', $router);
        $this->assertFileExists($pagePath);

        $page = file_get_contents($pagePath);
        $this->assertStringContainsString('/api/auth/customer-reminders/action-list', $page);
        $this->assertStringContainsString('/api/auth/customer-reminders/scan', $page);
        $this->assertStringContainsString('/api/auth/customer-reminders/dispatch', $page);
        $this->assertStringContainsString('dry_run: true', $page);
        $this->assertStringContainsString('use App\\Http\\Controllers\\CustomerReminderController;', $apiRoutes);
        $this->assertStringContainsString("Route::get('/action-list', [CustomerReminderController::class, 'actionList'])", $apiRoutes);
    }
}
