<?php

namespace App\Http\Services;

use App\Contracts\ReminderProviderInterface;
use App\Models\CustomerReminderOutbox;
use App\Models\DebtNote;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\ReminderContactLog;
use App\Models\ReminderDeliveryEvent;
use App\Models\User;
use App\Services\Reminders\SandboxReminderProvider;
use App\Support\PermissionAccess;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerReminderService
{
    protected $defaultProvider;

    public function __construct(?ReminderProviderInterface $defaultProvider = null)
    {
        $this->defaultProvider = $defaultProvider ?: new SandboxReminderProvider();
    }

    public function isLiveDeliveryConfigured(): bool
    {
        $enabled = filter_var(env('REMINDER_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        return $enabled && !($this->defaultProvider instanceof SandboxReminderProvider);
    }

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
                } elseif ($diffDays < 0 && $diffDays >= -5) {
                    $stage = 'overdue_1_5d';
                } elseif ($diffDays < -5 && $diffDays >= -30) {
                    $stage = 'overdue_6_30d';
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

                $remaining = $installment->remaining_amount ?? ($installment->amount_due - $installment->amount_paid);
                $formattedAmount = number_format($remaining, 0, ',', '.') . 'đ';
                $customerName = $contract->customer ? $contract->customer->name : 'Khách hàng';
                $phone = $contract->customer ? $contract->customer->phone : '';
                $license = $contract->vehicle ? ($contract->vehicle->plate_number ?? $contract->vehicle->license) : '';

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
            // Orders have no rental_end_date column. The latest vehicle return
            // date is the actual due date for a multi-vehicle rental contract.
            $lastReturnAt = $order->orderItems->max('return_at');
            if (!$lastReturnAt) {
                continue;
            }
            $endDate = Carbon::parse($lastReturnAt, 'Asia/Ho_Chi_Minh')->toDateString();
            $diffDays = Carbon::parse($today)->diffInDays(Carbon::parse($endDate), false);

            $stage = null;
            if ($diffDays === 1) {
                $stage = 'due_soon_1d';
            } elseif ($diffDays === 0) {
                $stage = 'due_today';
            } elseif ($diffDays < 0 && $diffDays >= -5) {
                $stage = 'overdue_1_5d';
            } elseif ($diffDays < -5 && $diffDays >= -30) {
                $stage = 'overdue_6_30d';
            } elseif ($diffDays < -30) {
                $stage = 'overdue_30_plus';
            }

            if (!$stage) {
                continue;
            }

            $channel = 'call_task';
            $idempotencyKey = "order_{$order->id}_{$stage}_{$channel}";

            $exists = CustomerReminderOutbox::where('idempotency_key', $idempotencyKey)->exists();
            if ($exists) {
                $skippedCount++;
                continue;
            }

            $customerName = $order->customer ? $order->customer->name : 'Khách hàng';
            $phone = $order->customer ? $order->customer->phone : '';
            $plate = '';
            if ($order->orderItems && $order->orderItems->isNotEmpty() && $order->orderItems->first()->vehicle) {
                $plate = $order->orderItems->first()->vehicle->license ?? $order->orderItems->first()->vehicle->plate_number;
            }

            $reference = $order->contract_number ?: '#' . $order->id;
            $message = "Kính gửi {$customerName}, đơn thuê xe {$reference} (xe {$plate}) đến hạn kết thúc thuê ngày {$endDate}. Trạng thái: {$stage}.";

            CustomerReminderOutbox::create([
                'contract_type' => 'rental_order',
                'contract_id' => $order->id,
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
    public function getStaffActionList(array $params = [], ?User $user = null): array
    {
        $query = CustomerReminderOutbox::with(['customer', 'installment'])
            ->orderBy('id', 'desc');

        $this->scopeActionList($query, $user);

        // A paid lease installment or a rental order that has already been
        // closed must disappear from the operational reminder list without
        // waiting for a separate cleanup job.
        $query->where(function ($active) {
            $active->where('contract_type', '!=', 'lease')
                ->orWhereDoesntHave('installment')
                ->orWhereHas('installment', function ($installment) {
                    $installment->where('status', '!=', LeaseInstallment::STATUS_PAID);
                });
        })->where(function ($active) {
            $active->where('contract_type', '!=', 'rental_order')
                ->orWhereIn('contract_id', Order::select('id')->where('order_status', OrderValidator::ORDER_RENTING));
        });

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['contract_type'])) {
            $query->where('contract_type', $params['contract_type']);
        }
        if (!empty($params['store_id'])) {
            $storeId = (int) $params['store_id'];
            $query->where(function ($sub) use ($storeId) {
                $sub->where(function ($lease) use ($storeId) {
                    $contracts = LeaseContract::select('id');
                    if (Schema::hasColumn('lease_contracts', 'origin_store_id')) {
                        $contracts->where('origin_store_id', $storeId);
                    } else {
                        $contracts->where('store_id', $storeId);
                    }
                    $lease->where('contract_type', 'lease')->whereIn('contract_id', $contracts);
                })->orWhere(function ($rental) use ($storeId) {
                    $rental->where('contract_type', 'rental_order')
                        ->whereIn('contract_id', Order::select('id')->where('store_id', $storeId));
                });
            });
        }
        if (!empty($params['search'])) {
            $search = trim($params['search']);
            $query->where(function ($q) use ($search) {
                $q->where('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('message_content', 'like', "%{$search}%");
            });
        }

        // Stats before filtering by stage / debt_group
        $statsBase = clone $query;
        $stats = [
            'total' => (clone $statsBase)->count(),
            'overdue_1_5' => (clone $statsBase)->where('stage', 'overdue_1_5d')->count(),
            'overdue_6_30' => (clone $statsBase)->where('stage', 'overdue_6_30d')->count(),
            'overdue_30_plus' => (clone $statsBase)->where('stage', 'overdue_30_plus')->count(),
            'due_today' => (clone $statsBase)->where('stage', 'due_today')->count(),
        ];

        if (!empty($params['debt_group'])) {
            if ($params['debt_group'] === 'overdue_1_5' || $params['debt_group'] === 'early') {
                $query->where('stage', 'overdue_1_5d');
            } elseif ($params['debt_group'] === 'overdue_6_30' || $params['debt_group'] === 'late') {
                $query->where('stage', 'overdue_6_30d');
            } elseif ($params['debt_group'] === 'overdue_30_plus' || $params['debt_group'] === 'recall') {
                $query->where('stage', 'overdue_30_plus');
            }
        } elseif (!empty($params['stage'])) {
            $query->where('stage', $params['stage']);
        }

        $perPage = !empty($params['per_page']) ? (int)$params['per_page'] : 20;
        $page = $query->paginate($perPage);

        $collection = $page->getCollection();

        $leaseIds = $collection->where('contract_type', 'lease')->pluck('contract_id')->unique()->filter()->values();
        $orderIds = $collection->where('contract_type', 'rental_order')->pluck('contract_id')->unique()->filter()->values();

        $hasLeases = Schema::hasTable('lease_contracts');
        $hasOrders = Schema::hasTable('orders');
        $hasVehicles = Schema::hasTable('vehicles');

        $leaseWith = ['customer', 'installments'];
        if ($hasVehicles) {
            $leaseWith[] = 'vehicle';
        }
        if (Schema::hasTable('debt_notes')) {
            $leaseWith[] = 'debtNotes';
        }

        $orderWith = ['customer'];
        if (Schema::hasTable('order_vehicle_details')) {
            $orderWith[] = $hasVehicles ? 'orderItems.vehicle' : 'orderItems';
        }
        if (Schema::hasTable('stores')) {
            $orderWith[] = 'store';
        }

        $leases = ($hasLeases && $leaseIds->isNotEmpty())
            ? LeaseContract::with($leaseWith)->whereIn('id', $leaseIds)->get()->keyBy('id')
            : collect();

        $orders = ($hasOrders && $orderIds->isNotEmpty())
            ? Order::with($orderWith)->whereIn('id', $orderIds)->get()->keyBy('id')
            : collect();

        if (Schema::hasTable('reminder_contact_logs')) {
            $collection->load('contactLogs');
        }

        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $todayStr = $today->toDateString();

        $collection->each(function ($item) use ($leases, $orders, $today, $todayStr) {
            if (Schema::hasTable('reminder_contact_logs') && $item->relationLoaded('contactLogs')) {
                $item->contacted_today = $item->contactLogs->contains('contact_date', $todayStr);
                $latestLog = $item->contactLogs->first();
                $item->last_contact_note = optional($latestLog)->note;
                $item->last_action = optional($latestLog)->action;
            }

            if ($item->contract_type === 'lease') {
                $contract = $leases->get($item->contract_id);
                $installment = $item->installment;
                if (!$installment && $contract && $contract->installments) {
                    $installment = $contract->installments->where('id', $item->installment_id)->first() 
                        ?: $contract->installments->where('status', '!=', LeaseInstallment::STATUS_PAID)->sortBy('due_date')->first();
                }

                $customer = ($contract && $contract->relationLoaded('customer')) ? $contract->customer : null;
                $vehicle = ($contract && $contract->relationLoaded('vehicle')) ? $contract->vehicle : null;
                $debtNotes = ($contract && $contract->relationLoaded('debtNotes')) ? $contract->debtNotes : collect();

                $rentalStartDate = $contract ? ($contract->start_date ?: ($contract->created_at ? $contract->created_at->toDateString() : null)) : null;
                $customerName = $customer ? $customer->name : $item->recipient_name;
                $customerPhone = $customer ? $customer->phone : $item->recipient_phone;
                $customerRelatives = $customer ? $customer->relatives : [];
                $packageLabel = $contract ? (($contract->installment_count ?: $contract->term_months ?: 12) . ' tháng') : 'Thuê sở hữu';
                $vehicleType = $vehicle ? ($vehicle->name ?: ($vehicle->brand . ' ' . $vehicle->model_name)) : 'Xe máy';
                $plateNumber = $vehicle ? ($vehicle->license ?: $vehicle->plate_number) : null;
                
                $dueDate = $installment ? $installment->due_date : ($contract ? $contract->next_due_date : null);
                $overdueDays = 0;
                if ($dueDate) {
                    $dueCarbon = Carbon::parse($dueDate, 'Asia/Ho_Chi_Minh')->startOfDay();
                    $diff = $dueCarbon->diffInDays($today->copy()->startOfDay(), false);
                    $overdueDays = $diff > 0 ? (int)$diff : 0;
                }
                
                $remainingAmount = $installment 
                    ? ($installment->remaining_amount ?? ($installment->amount_due - $installment->amount_paid))
                    : ($contract ? $contract->outstanding_balance : 0);

                if ($overdueDays > 30 || $item->stage === 'overdue_30_plus') {
                    $autoDebtGroup = 'Cần thu hồi';
                } elseif ($overdueDays >= 6 || $item->stage === 'overdue_6_30d') {
                    $autoDebtGroup = 'Nợ muộn';
                } elseif ($overdueDays >= 1 || $item->stage === 'overdue_1_5d') {
                    $autoDebtGroup = 'Nợ sớm';
                } else {
                    $autoDebtGroup = 'Đến hạn';
                }

                $item->rental_start_date = $rentalStartDate;
                $item->customer_name = $customerName;
                $item->customer_phone = $customerPhone;
                $item->customer_relatives = $customerRelatives;
                $item->package_label = $packageLabel;
                $item->vehicle_type = trim($vehicleType) ?: 'Xe máy';
                $item->plate_number = $plateNumber ?: 'Chưa gán';
                $item->due_date = $dueDate;
                $item->overdue_days = $overdueDays;
                $item->debt_amount = (float)$remainingAmount;
                $item->auto_debt_group = $autoDebtGroup;
                $item->contract_code = ($contract && $contract->contract_code) ? $contract->contract_code : ('SH#' . $item->contract_id);
                $item->contract_details = $contract ? [
                    'id' => $contract->id,
                    'contract_code' => $contract->contract_code,
                    'overdue_days' => $overdueDays,
                    'outstanding_balance' => (float)$contract->outstanding_balance,
                    'customer' => $customer,
                    'vehicle' => $vehicle,
                    'debt_notes' => $debtNotes,
                ] : null;
            } else {
                $order = $orders->get($item->contract_id);
                $orderItems = ($order && $order->relationLoaded('orderItems')) ? $order->orderItems : collect();
                $firstItem = $orderItems->first();
                $vehicle = ($firstItem && $firstItem->relationLoaded('vehicle')) ? $firstItem->vehicle : null;
                $customer = ($order && $order->relationLoaded('customer')) ? $order->customer : null;

                $rentalStartDate = ($firstItem && $firstItem->rent_at) ? Carbon::parse($firstItem->rent_at)->toDateString() : (($order && $order->created_at) ? $order->created_at->toDateString() : null);
                $customerName = $customer ? $customer->name : $item->recipient_name;
                $customerPhone = $customer ? $customer->phone : $item->recipient_phone;
                $customerRelatives = $customer ? $customer->relatives : [];
                
                $packageLabel = ($firstItem && $firstItem->pricing_scheme) ? $firstItem->pricing_scheme : 'Thuê xe';
                if ($firstItem && $firstItem->rent_at && $firstItem->return_at) {
                    $days = Carbon::parse($firstItem->rent_at)->diffInDays(Carbon::parse($firstItem->return_at)) ?: 1;
                    $packageLabel = $days . ' ngày';
                }
                
                $vehicleType = $vehicle ? ($vehicle->name ?: ($vehicle->brand ?: 'Xe máy')) : 'Xe máy';
                $plateNumber = $vehicle ? ($vehicle->license ?: $vehicle->plate_number) : null;
                
                $lastReturnAt = $orderItems->isNotEmpty() ? $orderItems->max('return_at') : null;
                $dueDate = $lastReturnAt ? Carbon::parse($lastReturnAt)->toDateString() : null;
                $overdueDays = 0;
                if ($dueDate) {
                    $dueCarbon = Carbon::parse($dueDate, 'Asia/Ho_Chi_Minh')->startOfDay();
                    $diff = $dueCarbon->diffInDays($today->copy()->startOfDay(), false);
                    $overdueDays = $diff > 0 ? (int)$diff : 0;
                }

                $totalAmount = (float)($order ? ($order->total ?: 0) : 0);
                $totalAmount += (float)($order ? ($order->outdate_or_early_amount ?: 0) : 0);
                $paidAmount = (float)($order ? ($order->pid ?: ($order->paid ?: 0)) : 0);
                $remainingAmount = max(0, $totalAmount - $paidAmount);

                if ($overdueDays > 30 || $item->stage === 'overdue_30_plus') {
                    $autoDebtGroup = 'Cần thu hồi';
                } elseif ($overdueDays >= 6 || $item->stage === 'overdue_6_30d') {
                    $autoDebtGroup = 'Nợ muộn';
                } elseif ($overdueDays >= 1 || $item->stage === 'overdue_1_5d') {
                    $autoDebtGroup = 'Nợ sớm';
                } else {
                    $autoDebtGroup = 'Đến hạn';
                }

                $item->rental_start_date = $rentalStartDate;
                $item->customer_name = $customerName;
                $item->customer_phone = $customerPhone;
                $item->customer_relatives = $customerRelatives;
                $item->package_label = $packageLabel;
                $item->vehicle_type = trim($vehicleType) ?: 'Xe máy';
                $item->plate_number = $plateNumber ?: 'Chưa gán';
                $item->due_date = $dueDate;
                $item->overdue_days = $overdueDays;
                $item->debt_amount = (float)$remainingAmount;
                $item->auto_debt_group = $autoDebtGroup;
                $item->contract_code = ($order && $order->contract_number) ? $order->contract_number : ('ĐH#' . $item->contract_id);
                $item->contract_details = $order ? [
                    'id' => $order->id,
                    'contract_code' => $order->contract_number ?: ('ĐH#' . $order->id),
                    'overdue_days' => $overdueDays,
                    'outstanding_balance' => (float)$remainingAmount,
                    'customer' => $customer,
                    'vehicle' => $vehicle,
                ] : null;
            }
        });

        $result = $page->toArray();
        $result['stats'] = $stats;
        return $result;
    }

    public function recordContact(int $id, string $note, User $user, array $extra = []): ReminderContactLog
    {
        if ($note === '' && empty($extra['action'])) {
            throw ValidationException::withMessages(['note' => 'Cần nhập ghi chú liên hệ hoặc chọn hành động.']);
        }
        if (!Schema::hasTable('reminder_contact_logs')) {
            throw ValidationException::withMessages(['note' => 'Chưa tạo bảng lịch sử liên hệ.']);
        }
        $query = CustomerReminderOutbox::query();
        $this->scopeActionList($query, $user);
        $reminder = $query->findOrFail($id);

        $actionMap = [
            'contacted' => 'Đã liên hệ',
            'promise' => 'Hứa thanh toán',
            'no_answer' => 'Ko nghe máy',
            'lost_contact' => 'Mất liên lạc',
            'uncooperative' => 'Không hợp tác',
            'paid' => 'Đã thanh toán',
            'recall_vehicle' => 'Cần thu hồi xe',
            'check_vehicle' => 'Cần check xe',
            'collect_money' => 'Đi thu tiền',
        ];

        $actionKey = $extra['action'] ?? null;
        $actionLabel = $actionMap[$actionKey] ?? $actionKey;

        $prefix = '';
        if ($actionLabel) {
            $prefix .= "[{$actionLabel}] ";
        }
        if (!empty($extra['paid_amount']) && is_numeric($extra['paid_amount']) && (float)$extra['paid_amount'] > 0) {
            $prefix .= "[Đã thanh toán: " . number_format((float)$extra['paid_amount'], 0, ',', '.') . "đ] ";
        }
        if (!empty($extra['appointment_date'])) {
            $prefix .= "[Hẹn: {$extra['appointment_date']}] ";
        }

        $fullNote = $prefix . ($note ?: ($actionLabel ?: 'Đã liên hệ'));

        $logData = [
            'reminder_id' => $reminder->id,
            'user_id' => $user->id,
            'contact_date' => Carbon::today('Asia/Ho_Chi_Minh')->toDateString(),
            'note' => $fullNote,
        ];

        if (Schema::hasColumn('reminder_contact_logs', 'action')) {
            $logData['action'] = $actionKey;
        }
        if (Schema::hasColumn('reminder_contact_logs', 'paid_amount')) {
            $logData['paid_amount'] = !empty($extra['paid_amount']) ? (float)$extra['paid_amount'] : 0;
        }
        if (Schema::hasColumn('reminder_contact_logs', 'appointment_date')) {
            $logData['appointment_date'] = !empty($extra['appointment_date']) ? Carbon::parse($extra['appointment_date'])->toDateString() : null;
        }

        $log = ReminderContactLog::create($logData);

        if ($reminder->contract_type === 'lease' && Schema::hasTable('debt_notes')) {
            DebtNote::create([
                'lease_contract_id' => $reminder->contract_id,
                'customer_id' => $reminder->customer_id,
                'note_content' => $fullNote,
                'appointment_date' => !empty($extra['appointment_date']) ? Carbon::parse($extra['appointment_date']) : null,
                'debt_classification' => 'reminder',
                'created_by' => $user->id,
            ]);
        }

        return $log;
    }

    private function scopeActionList($query, ?User $user): void
    {

        // A store-scoped operator must not see reminder recipients from other
        // stores simply by omitting store_id from the request.
        if ($user && !PermissionAccess::isAdmin($user)
            && !PermissionAccess::allows($user, 'kpi.view_company')) {
            if (!$user->store_id) {
                $query->whereRaw('1 = 0');
                return;
            }
            $storeId = (int) $user->store_id;
            $query->where(function ($scope) use ($storeId, $user) {
                $scope->where(function ($lease) use ($storeId, $user) {
                    $contracts = LeaseContract::select('id');
                    if (Schema::hasColumn('lease_contracts', 'origin_store_id')) {
                        $contracts->where('origin_store_id', $storeId);
                    } else {
                        $contracts->where('store_id', $storeId);
                    }
                    if (PermissionAccess::getRoleSlug($user) === 'nhan-vien') {
                        $contracts->where('assigned_user_id', $user->id);
                    }
                    $lease->where('contract_type', 'lease')
                        ->whereIn('contract_id', $contracts);
                })->orWhere(function ($rental) use ($storeId) {
                    $rental->where('contract_type', 'rental_order')
                        ->whereIn('contract_id', Order::select('id')->where('store_id', $storeId));
                });
            });
        }
    }

    /**
     * Atomic batch claim and dispatch with row-level locking.
     */
    public function claimAndDispatchBatch(
        int $limit = 20,
        ?string $workerId = null,
        ?ReminderProviderInterface $provider = null,
        array $options = []
    ): array {
        $workerId = $workerId ?: ('worker-' . getmypid() . '-' . Str::random(4));
        $provider = $provider ?: $this->defaultProvider;
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        if (!$this->isLiveDeliveryConfigured() && empty($options['allow_sandbox'])) {
            throw ValidationException::withMessages([
                'provider' => 'Gửi nhắc nợ thật đang bị khóa: chưa cấu hình provider live và REMINDER_LIVE_ENABLED=true.',
            ]);
        }

        // Check Quiet Hours (08:00 to 20:30 Vietnam time)
        $hour = (int) $now->format('G');
        $minute = (int) $now->format('i');
        $isQuietHours = ($hour < 8) || ($hour > 20) || ($hour === 20 && $minute > 30);
        if ($isQuietHours && empty($options['ignore_quiet_hours'])) {
            return [
                'claimed' => 0,
                'dispatched' => 0,
                'skipped' => 0,
                'failed' => 0,
                'reason' => 'quiet_hours_active',
            ];
        }

        // 1. Atomically Claim Records with Row Lock
        $claimedItems = DB::transaction(function () use ($limit, $workerId, $now) {
            $records = CustomerReminderOutbox::where('status', 'pending')
                ->where(function ($q) use ($now) {
                    $q->whereNull('next_attempt_at')
                      ->orWhere('next_attempt_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('locked_at')
                      ->orWhere('locked_at', '<=', $now->copy()->subMinutes(5)); // stale lock recovery
                })
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($records as $rec) {
                $rec->status = 'processing';
                $rec->locked_at = $now;
                $rec->locked_by = $workerId;
                $rec->save();
            }

            return $records;
        });

        if ($claimedItems->isEmpty()) {
            return [
                'claimed' => 0,
                'dispatched' => 0,
                'skipped' => 0,
                'failed' => 0,
                'dead_letter' => 0,
            ];
        }

        $dispatched = 0;
        $skipped = 0;
        $failed = 0;
        $deadLetter = 0;

        // 2. Process each claimed item individually
        foreach ($claimedItems as $item) {
            // Guard A: Check if customer paid before dispatch
            if ($item->contract_type === 'lease' && $item->installment_id) {
                $inst = LeaseInstallment::find($item->installment_id);
                if ($inst && $inst->status === LeaseInstallment::STATUS_PAID) {
                    $item->update([
                        'status' => 'skipped',
                        'locked_at' => null,
                        'locked_by' => null,
                        'cancel_reason' => 'Installment was paid before dispatch.',
                    ]);

                    ReminderDeliveryEvent::create([
                        'outbox_id' => $item->id,
                        'provider' => 'system',
                        'provider_message_id' => null,
                        'event_type' => 'skipped',
                        'http_status' => null,
                        'payload_json' => ['reason' => 'paid_before_dispatch'],
                    ]);

                    $skipped++;
                    continue;
                }
            }

            // Guard B: Check max retries (>= 3 attempts -> dead_letter)
            if ($item->retry_count >= 3) {
                $item->update([
                    'status' => 'dead_letter',
                    'failed_at' => $now,
                    'locked_at' => null,
                    'locked_by' => null,
                    'cancel_reason' => 'Exceeded max retry limit (3).',
                ]);

                ReminderDeliveryEvent::create([
                    'outbox_id' => $item->id,
                    'provider' => 'system',
                    'provider_message_id' => null,
                    'event_type' => 'dead_letter',
                    'http_status' => $item->last_http_status,
                    'payload_json' => ['reason' => 'max_retries_exceeded'],
                ]);

                $deadLetter++;
                continue;
            }

            // Record dispatched event
            ReminderDeliveryEvent::create([
                'outbox_id' => $item->id,
                'provider' => class_basename($provider),
                'provider_message_id' => null,
                'event_type' => 'dispatched',
                'http_status' => null,
                'payload_json' => [
                    'recipient' => AuditService::maskPhone((string) $item->recipient_phone),
                    'attempt' => $item->retry_count + 1,
                    'worker_id' => $workerId,
                ],
            ]);

            // Call Provider
            $resp = $provider->send($item, $options);
            $item->attempted_at = $now;
            $item->last_http_status = $resp['http_status'] ?? null;

            if ($resp['success']) {
                $item->status = 'sent';
                $item->provider = class_basename($provider);
                $item->provider_message_id = $resp['provider_message_id'] ?? null;
                $item->sent_at = $now;
                $item->locked_at = null;
                $item->locked_by = null;
                $item->error_message = null;
                $item->save();

                ReminderDeliveryEvent::create([
                    'outbox_id' => $item->id,
                    'provider' => class_basename($provider),
                    'provider_message_id' => $resp['provider_message_id'] ?? null,
                    'event_type' => 'accepted',
                    'http_status' => $resp['http_status'] ?? 200,
                    'payload_json' => AuditService::sanitizeData($resp),
                ]);

                $dispatched++;
            } else {
                $isRetryable = !empty($resp['retryable']);
                $newRetryCount = $item->retry_count + 1;
                $item->retry_count = $newRetryCount;
                $item->error_message = $resp['error'] ?? 'Provider error';

                if ($isRetryable && $newRetryCount < 3) {
                    $backoffMinutes = (int) pow(2, $newRetryCount) * 5;
                    $item->status = 'pending';
                    $item->next_attempt_at = $now->copy()->addMinutes($backoffMinutes);
                    $item->locked_at = null;
                    $item->locked_by = null;
                    $item->save();
                    $failed++;
                } else {
                    $item->status = $isRetryable ? 'dead_letter' : 'failed';
                    $item->failed_at = $now;
                    $item->locked_at = null;
                    $item->locked_by = null;
                    $item->cancel_reason = $isRetryable ? 'Exceeded max retry limit (3).' : ($resp['error'] ?? 'Non-retryable provider failure.');
                    $item->save();

                    if ($isRetryable) {
                        $deadLetter++;
                    } else {
                        $failed++;
                    }
                }

                ReminderDeliveryEvent::create([
                    'outbox_id' => $item->id,
                    'provider' => class_basename($provider),
                    'provider_message_id' => $resp['provider_message_id'] ?? null,
                    'event_type' => $resp['retryable'] ? 'failed_retryable' : 'rejected',
                    'http_status' => $resp['http_status'] ?? 500,
                    'payload_json' => AuditService::sanitizeData($resp),
                ]);
            }
        }

        return [
            'claimed' => $claimedItems->count(),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'failed' => $failed,
            'dead_letter' => $deadLetter,
        ];
    }

    /**
     * Handle incoming delivery webhook from provider.
     */
    public function handleWebhook(
        string $providerName,
        array $payload,
        array $headers,
        ?ReminderProviderInterface $provider = null
    ): array {
        $provider = $provider ?: $this->defaultProvider;

        if (!$provider->verifyWebhook($payload, $headers)) {
            throw ValidationException::withMessages([
                'webhook' => 'Chữ ký webhook không hợp lệ hoặc đã hết hạn.'
            ]);
        }

        $providerMsgId = $payload['provider_message_id'] ?? ($payload['message_id'] ?? null);
        if (!$providerMsgId) {
            throw ValidationException::withMessages([
                'provider_message_id' => 'Thiếu provider_message_id trong webhook payload.'
            ]);
        }

        $item = CustomerReminderOutbox::where('provider_message_id', $providerMsgId)->first();
        if (!$item) {
            return [
                'status' => 'ignored',
                'message' => 'No matching outbox item for message id ' . $providerMsgId,
            ];
        }

        $statusStr = strtolower((string) ($payload['status'] ?? 'delivered'));
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        // Idempotency: If already delivered, don't duplicate
        if ($item->status === 'delivered' && $statusStr === 'delivered') {
            return [
                'status' => 'already_delivered',
                'outbox_id' => $item->id,
            ];
        }

        if ($statusStr === 'delivered' || $statusStr === 'delivrd') {
            $item->status = 'delivered';
            $item->delivered_at = $now;
            $item->save();

            ReminderDeliveryEvent::create([
                'outbox_id' => $item->id,
                'provider' => $providerName,
                'provider_message_id' => $providerMsgId,
                'event_type' => 'delivered',
                'http_status' => 200,
                'payload_json' => AuditService::sanitizeData($payload),
            ]);
        } else {
            $item->status = 'failed';
            $item->failed_at = $now;
            $item->error_message = $payload['error_description'] ?? 'Delivery failed by provider';
            $item->save();

            ReminderDeliveryEvent::create([
                'outbox_id' => $item->id,
                'provider' => $providerName,
                'provider_message_id' => $providerMsgId,
                'event_type' => 'failed',
                'http_status' => 200,
                'payload_json' => AuditService::sanitizeData($payload),
            ]);
        }

        return [
            'status' => 'updated',
            'outbox_id' => $item->id,
            'current_status' => $item->status,
        ];
    }

    /**
     * Backward-compatible processOutbox.
     */
    public function processOutbox(int $limit = 50, bool $dryRun = true): array
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $items = CustomerReminderOutbox::where('status', 'pending')
            ->where(function ($q) use ($now) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', $now);
            })
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

            // In dryRun mode, never consume pending notification or claim delivery
            if (!$dryRun) {
                $item->update([
                    'error_message' => 'No live delivery provider configured.',
                    'status' => 'pending',
                ]);
                $failed++;
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
