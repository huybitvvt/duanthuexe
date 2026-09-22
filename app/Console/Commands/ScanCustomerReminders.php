<?php

namespace App\Console\Commands;

use App\Http\Services\CustomerReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ScanCustomerReminders extends Command
{
    protected $signature = 'himoto:scan-reminders';
    protected $description = 'Tạo tác vụ nhắc nợ/ngày trả xe trong phạm vi nội bộ, không gửi tin cho khách';

    public function handle(CustomerReminderService $service): int
    {
        if (!Schema::hasTable('customer_reminder_outbox') || !Schema::hasTable('lease_contracts')) {
            $this->warn('Chưa chạy migration cho hệ thống nhắc nợ.');
            return 1;
        }

        $result = $service->scanDueAndOverdueItems();
        $this->info('Tác vụ mới: ' . ($result['created'] ?? 0));
        return 0;
    }
}
