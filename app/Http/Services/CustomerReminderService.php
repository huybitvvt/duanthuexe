<?php

namespace App\Http\Services;

use App\Contracts\ReminderProviderInterface;
use App\Models\CustomerReminderOutbox;
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

        $perPage = !empty($params['per_page']) ? (int)$params['per_page'] : 20;
        $page = $query->paginate($perPage);
        if (Schema::hasTable('reminder_contact_logs')) {
            $page->getCollection()->load('contactLogs');
            $today = Carbon::today('Asia/Ho_Chi_Minh')->toDateString();
            $page->getCollection()->each(function ($item) use ($today) {
                $item->contacted_today = $item->contactLogs->contains('contact_date', $today);
                $item->last_contact_note = optional($item->contactLogs->first())->note;
            });
        }
        return $page->toArray();
    }

    public function recordContact(int $id, string $note, User $user): ReminderContactLog
    {
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Cần nhập ghi chú liên hệ.']);
        }
        if (!Schema::hasTable('reminder_contact_logs')) {
            throw ValidationException::withMessages(['note' => 'Chưa tạo bảng lịch sử liên hệ.']);
        }
        $query = CustomerReminderOutbox::query();
        $this->scopeActionList($query, $user);
        $reminder = $query->findOrFail($id);
        return ReminderContactLog::create([
            'reminder_id' => $reminder->id,
            'user_id' => $user->id,
            'contact_date' => Carbon::today('Asia/Ho_Chi_Minh')->toDateString(),
            'note' => $note,
        ]);
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
