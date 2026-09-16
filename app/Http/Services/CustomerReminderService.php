<?php

namespace App\Http\Services;

use App\Models\CustomerReminderOutbox;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReminderService
{
    /**
     * Scan due and overdue contracts (both lease-to-own and rental orders)
     * and queue reminders into the outbox with strict idempotency keys.
     *
     * @return array
     */
    public function scanDueAndOverdueItems(): array
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $today = $now->toDateString();
        $createdCount = 0;
        $skippedCount = 0;

        // 1. Scan Lease-to-own installments
        $activeLeases = LeaseContract::with(['customer', 'vehicle', 'store', 'installments' => function ($q) {
            $q->where('status', '!=', LeaseInstallment::STATUS_PAID);
        }])->where('status', LeaseContract::STATUS_ACTIVE)->get();

        foreach ($activeLeases as $contract) {
            foreach ($contract->installments as $installment) {
                $dueDate = Carbon::parse($installment->due_date, 'Asia/Ho_Chi_Minh')->toDateString();
                $diffDays = Carbon::parse($today)->diffInDays(Carbon::parse($dueDate), false);

                $stage = null;
                if ($diffDays === 3) {
                    $stage = 'due_soon_3d';
                } elseif ($diffDays === 1) {
                    $stage = 'due_soon_1d';
                } elseif ($diffDays === 0) {
                    $stage = 'due_today';
                } elseif ($diffDays < 0 && $diffDays >= -7) {
                    $stage = 'overdue_1_7d';
                } elseif ($diffDays < -7 && $diffDays >= -30) {
                    $stage = 'overdue_8_30d';
                } elseif ($diffDays < -30) {
                    $stage = 'overdue_30_plus';
                }

                if (!$stage) {
                    continue;
                }

                $channel = 'call_task';
                $idempotencyKey = "lease_{$contract->id}_{$installment->id}_{$stage}_{$channel}";

                // Check if already created
                $exists = CustomerReminderOutbox::where('idempotency_key', $idempotencyKey)->exists();
                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                $remaining = $installment->remaining_amount;
                $formattedAmount = number_format($remaining, 0, ',', '.') . 'đ';
                $customerName = $contract->customer ? $contract->customer->name : 'Khách hàng';
                $phone = $contract->customer ? $contract->customer->phone : '';
                $license = $contract->vehicle ? $contract->vehicle->license : '';

                $message = "Kính gửi {$customerName}, hợp đồng thuê sở hữu {$contract->contract_code} (xe {$license}) có kỳ trả góp đến hạn {$dueDate}, số tiền {$formattedAmount}. Trạng thái: {$stage}.";

                CustomerReminderOutbox::create([
                    'contract_type' => 'lease',
                    'contract_id' => $contract->id,
                    'installment_id' => $installment->id,
                    'customer_id' => $contract->customer_id,
                    'channel' => $channel,
                    'stage' => $stage,
                    'recipient_phone' => $phone,
                    'recipient_name' => $customerName,
                    'message_content' => $message,
                    'status' => 'pending',
                    'scheduled_at' => $now,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $createdCount++;
            }
        }

        // 2. Scan Rental Orders (Order)
        $rentingOrders = Order::with(['customer', 'orderItems.vehicle', 'store'])
            ->where('order_status', OrderValidator::ORDER_RENTING)
            ->get();

        foreach ($rentingOrders as $order) {
            foreach ($order->orderItems as $item) {
                if (!$item->return_at) {
                    continue;
                }
                $returnDate = Carbon::parse($item->return_at, 'Asia/Ho_Chi_Minh')->toDateString();
                $diffDays = Carbon::parse($today)->diffInDays(Carbon::parse($returnDate), false);

                $stage = null;
                if ($diffDays === 1) {
                    $stage = 'return_tomorrow';
                } elseif ($diffDays === 0) {
                    $stage = 'return_today';
                } elseif ($diffDays < 0) {
                    $stage = 'overdue_return';
                }

                if (!$stage) {
                    continue;
                }

                $channel = 'call_task';
                $idempotencyKey = "rental_{$order->id}_{$item->id}_{$stage}_{$channel}";

                $exists = CustomerReminderOutbox::where('idempotency_key', $idempotencyKey)->exists();
                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                $customerName = $order->customer ? $order->customer->name : 'Khách hàng';
                $phone = $order->customer ? $order->customer->phone : '';
                $license = $item->vehicle ? $item->vehicle->license : '';

                $message = "Kính gửi {$customerName}, đơn thuê xe #{$order->id} (xe {$license}) có lịch trả xe vào ngày {$returnDate}. Vui lòng sắp xếp bàn giao đúng giờ.";

                CustomerReminderOutbox::create([
                    'contract_type' => 'rental',
                    'contract_id' => $order->id,
                    'installment_id' => null,
                    'customer_id' => $order->customer_id,
                    'channel' => $channel,
                    'stage' => $stage,
                    'recipient_phone' => $phone,
                    'recipient_name' => $customerName,
                    'message_content' => $message,
                    'status' => 'pending',
                    'scheduled_at' => $now,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $createdCount++;
            }
        }

        return [
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'scanned_at' => $now->toDateTimeString(),
        ];
    }

    /**
     * Get operational action list for debt & customer care staff.
     *
     * @param array $params
     * @return array
     */
    public function getStaffActionList(array $params = []): array
    {
        $query = CustomerReminderOutbox::with(['customer', 'installment'])
            ->orderBy('id', 'desc');

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['contract_type'])) {
            $query->where('contract_type', $params['contract_type']);
        }
        if (!empty($params['search'])) {
            $search = trim($params['search']);
            $query->where(function ($q) use ($search) {
                $q->where('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('message_content', 'like', "%{$search}%");
            });
        }

        $perPage = !empty($params['per_page']) ? (int)$params['per_page'] : 20;
        return $query->paginate($perPage)->toArray();
    }

    /**
     * Process outbox queue with sandbox / dry-run protection.
     *
     * @param int $limit
     * @param bool $dryRun
     * @return array
     */
    public function processOutbox(int $limit = 50, bool $dryRun = true): array
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $items = CustomerReminderOutbox::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->limit($limit)
            ->get();

        $processed = 0;
        $sent = 0;
        $failed = 0;

        foreach ($items as $item) {
            $processed++;

            // If customer already paid the installment, skip it
            if ($item->contract_type === 'lease' && $item->installment_id) {
                $inst = LeaseInstallment::find($item->installment_id);
                if ($inst && $inst->status === LeaseInstallment::STATUS_PAID) {
                    $item->update([
                        'status' => 'skipped',
                        'error_message' => 'Installment was paid before dispatch.',
                    ]);
                    continue;
                }
            }

            // Sandbox / Dry-run safe dispatch
            if ($dryRun) {
                $item->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'provider_response' => json_encode([
                        'mode' => 'sandbox_dry_run',
                        'note' => 'Dispatched to staff task queue without real SMS cost/spam.',
                    ]),
                ]);
                $sent++;
            } else {
                // Live delivery would call real SMS/Zalo provider here when credentials are provided
                $item->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'provider_response' => json_encode(['status' => 'success']),
                ]);
                $sent++;
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'mode' => $dryRun ? 'sandbox_dry_run' : 'live',
        ];
    }
}
