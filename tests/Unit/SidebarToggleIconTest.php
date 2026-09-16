<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SidebarToggleIconTest extends TestCase
{
    public function testDesktopSidebarToggleUsesAnAccessibleDirectionalIcon(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/resources/js/src/view/layout/himoto/HimotoHeader.vue');
        $layout = file_get_contents($root . '/resources/js/src/view/layout/Layout.vue');

        $this->assertStringContainsString('class="sidebar-toggle-icon"', $header);
        $this->assertStringContainsString("sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'", $header);
        $this->assertStringNotContainsString('<span class="btn-text-label">Thu gọn</span>', $header);
        $this->assertStringContainsString(':sidebar-collapsed="sidebarCollapsed"', $layout);
    }
}
