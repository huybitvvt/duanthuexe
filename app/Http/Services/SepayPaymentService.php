<?php

namespace App\Http\Services;

use App\Helpers\CarRentalHelper;
use App\Models\Bank;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\SepayPaymentRequest;
use App\Models\SepayWebhookEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PilotAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SepayPaymentService
{
    private const CODE_PATTERN = '/^HMT[A-Z0-9]{10}$/';

    private function bankDefinition(?string $bankCode = null): array
    {
        $code = strtoupper(trim((string) ($bankCode ?: config('services.sepay.bank_code', 'MB'))));
        $banks = [
            'MB' => [
                'name' => 'MBBank',
                'name_pattern' => '/\b(mb|mbbank|quan doi|military bank)\b/',
                'gateways' => ['mb', 'mbbank', 'nganhangquandoi'],
            ],
            'VPB' => [
                'name' => 'VPBank',
                'name_pattern' => '/\b(vpb|vpbank|vp bank|viet nam thinh vuong)\b/',
                'gateways' => ['vpb', 'vpbank', 'nganhangvietnamthinhvuong'],
            ],
        ];
        if (!isset($banks[$code])) {
            throw new ServiceUnavailableHttpException(null, 'Mã ngân hàng SePay không được hỗ trợ.');
        }

        return $banks[$code];
    }

    public function configuredBank(): Bank
    {
        $bankId = config('services.sepay.bank_id');
        $definition = $this->bankDefinition();
        $account = trim((string) config('services.sepay.account_number'));
        $key = (string) config('services.sepay.webhook_api_key');

        if (!$bankId || !preg_match('/^[0-9]{6,19}$/', $account) || $key === '') {
            throw new ServiceUnavailableHttpException(null, 'SePay chưa được cấu hình đầy đủ.');
        }

        $bank = Bank::query()->whereKey($bankId)->where('account_number', $account)->first();
        if (!$bank || ($bank->status && $bank->status !== 'Active')) {
            throw new ServiceUnavailableHttpException(null, 'Tài khoản ngân hàng SePay chưa có hoặc không hoạt động trong hệ thống.');
        }
        $bankName = strtolower(Str::ascii((string) $bank->bank_name));
        if (!preg_match($definition['name_pattern'], $bankName)) {
            throw new ServiceUnavailableHttpException(null, 'Tài khoản được chọn không phải ' . $definition['name'] . '.');
        }

        return $bank;
    }

    public function ensureSchema(): void
    {
        if (!Schema::hasTable('sepay_payment_requests') || !Schema::hasTable('sepay_webhook_events')) {
            throw new ServiceUnavailableHttpException(null, 'Chưa chạy migration SePay.');
        }
    }

    public function createRequest(array $data, User $user): array
    {
        $this->ensureSchema();
        $bank = $this->configuredBank();
        PilotAccess::store($user, $data['store_id']);
        if (!in_array((int) $bank->store_id, [0, (int) $data['store_id']], true)) {
            throw ValidationException::withMessages(['store_id' => 'Tài khoản ngân hàng không thuộc cơ sở này hoặc ngân hàng dùng chung.']);
        }

        $purpose = $data['purpose'] ?? 'general';
        if ($purpose !== 'general' && empty($data['order_id'])) {
            throw ValidationException::withMessages(['order_id' => 'Khoản thu hợp đồng cần ID hợp đồng.']);
        }
        if ($purpose === 'general' && !empty($data['order_id'])) {
            throw ValidationException::withMessages(['order_id' => 'Chọn loại khoản thu hợp đồng để tự phân bổ.']);
        }

        $request = DB::transaction(function () use ($data, $bank, $user, $purpose) {
            $order = !empty($data['order_id']) ? Order::query()->whereKey($data['order_id'])->lockForUpdate()->first() : null;
            if (!empty($data['order_id']) && (!$order || (int) $order->store_id !== (int) $data['store_id'])) {
                throw ValidationException::withMessages(['order_id' => 'Hợp đồng không thuộc cơ sở đã chọn.']);
            }
            $lineItem = $order && $purpose === 'extension'
                ? OrderVehicleDetail::query()->whereKey($data['line_item_id'] ?? 0)->where('order_id', $order->id)->lockForUpdate()->first()
                : null;
            if ($order && !$this->canAllocate($order, $purpose, $lineItem, null)) {
                throw ValidationException::withMessages(['purpose' => 'Khoản này đã ghi thu, hợp đồng đã đóng hoặc đã có mã QR đang chờ.']);
            }
            if ($purpose === 'rental' && ((int) $order->total < 1 || (int) $data['amount'] !== (int) $order->total)) {
                throw ValidationException::withMessages(['amount' => 'Mã QR phí thuê phải bằng tổng phí thuê chưa thu của hợp đồng.']);
            }
            if ($purpose === 'extension' && (!$lineItem || empty($data['extension_return_at'])
                || !Carbon::parse($data['extension_return_at'])->gt(Carbon::parse($lineItem->return_at)))) {
                throw ValidationException::withMessages(['extension_return_at' => 'Ngày trả mới phải sau ngày trả hiện tại của xe trong hợp đồng.']);
            }

            return SepayPaymentRequest::create([
                'code' => $this->newPaymentCode(),
                'store_id' => $data['store_id'],
                'bank_id' => $bank->id,
                'bank_code' => strtoupper(trim((string) config('services.sepay.bank_code', 'MB'))),
                'account_number' => $bank->account_number,
                'account_holder' => $bank->owner_name,
                'order_id' => $data['order_id'] ?? null,
                'purpose' => $purpose,
                'line_item_id' => $lineItem ? $lineItem->id : null,
                'extension_return_at' => $lineItem ? Carbon::parse($data['extension_return_at'])->format('Y-m-d H:i:s') : null,
                'extension_from_at' => $lineItem ? Carbon::parse($lineItem->return_at)->format('Y-m-d H:i:s') : null,
                'created_by' => $user->id,
                'expected_amount' => $data['amount'],
                'note' => trim($data['note']),
            ]);
        });

        return $this->present($request, $bank);
    }

    public function listRequests(User $user, ?int $storeId = null): array
    {
        $this->ensureSchema();
        $bank = $this->configuredBank();
        $storeId = $storeId ?: (int) $user->store_id;
        PilotAccess::store($user, $storeId);

        return SepayPaymentRequest::query()
            ->where('store_id', $storeId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($request) use ($bank) { return $this->present($request, $bank); })
            ->all();
    }

    public function getRequest(int $id, User $user): array
    {
        $this->ensureSchema();
        $request = SepayPaymentRequest::findOrFail($id);
        PilotAccess::store($user, $request->store_id);
        return $this->present($request, $this->configuredBank());
    }

    public function orderOptions(int $id, User $user): array
    {
        $order = Order::query()->findOrFail($id);
        PilotAccess::store($user, $order->store_id);
        return [
            'id' => $order->id,
            'store_id' => $order->store_id,
            'total' => (int) $order->total,
            'created_without_collect_deposit' => (bool) $order->created_without_collect_deposit,
            'created_without_collect_rental_fees' => (bool) $order->created_without_collect_rental_fees,
            'items' => $order->orderItems()->with('vehicle:id,name')->get()
                ->map(function ($item) {
                    return ['id' => $item->id, 'vehicle' => $item->vehicle ? $item->vehicle->name : null,
                        'return_at' => $item->return_at];
                })->all(),
        ];
    }

    public function unmatched(User $user): array
    {
        PilotAccess::admin($user);
        $this->ensureSchema();

        return SepayWebhookEvent::query()->whereIn('status', ['unmatched', 'review'])
            ->orderBy('id', 'desc')->limit(50)
            ->get(['sepay_transaction_id', 'payment_code', 'account_number', 'reference_code', 'amount', 'status', 'content', 'created_at'])
            ->all();
    }

    public function verifyWebhookKey(?string $header): bool
    {
        $key = (string) config('services.sepay.webhook_api_key');
        if ($key === '' || !$header || !preg_match('/^Apikey\s+(.+)$/i', trim($header), $matches)) {
            return false;
        }

        return hash_equals($key, trim($matches[1]));
    }

    public function processWebhook(array $payload): array
    {
        $this->ensureSchema();
        $bank = $this->configuredBank();
        $definition = $this->bankDefinition();
        $gateway = preg_replace('/[^a-z0-9]/', '', strtolower((string) $payload['gateway']));
        if (!in_array($gateway, $definition['gateways'], true)
            || (string) $payload['accountNumber'] !== (string) $bank->account_number) {
            throw ValidationException::withMessages(['accountNumber' => 'Tài khoản hoặc ngân hàng không khớp cấu hình SePay.']);
        }

        $code = $this->paymentCode($payload);

        return DB::transaction(function () use ($payload, $bank, $code) {
            $inserted = DB::table('sepay_webhook_events')->insertOrIgnore([
                'sepay_transaction_id' => $payload['id'],
                'payment_code' => $code,
                'account_number' => $payload['accountNumber'],
                'reference_code' => $payload['referenceCode'] ?? null,
                'amount' => $payload['transferAmount'],
                'status' => 'received',
                'content' => substr((string) ($payload['content'] ?? ''), 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!$inserted) {
                return ['status' => 'duplicate'];
            }

            $event = SepayWebhookEvent::where('sepay_transaction_id', $payload['id'])->firstOrFail();
            if ($payload['transferType'] !== 'in') {
                $event->update(['status' => 'ignored']);
                return ['status' => 'ignored'];
            }

            $payment = $code ? SepayPaymentRequest::where('code', $code)->lockForUpdate()->first() : null;
            if (!$payment) {
                $event->update(['status' => 'unmatched']);
                return ['status' => 'unmatched'];
            }

            $amount = (int) $payload['transferAmount'];
            $purpose = $payment->purpose ?: 'general';
            $order = $purpose !== 'general'
                ? Order::query()->whereKey($payment->order_id)->lockForUpdate()->first() : null;
            $lineItem = $purpose === 'extension' && $order
                ? OrderVehicleDetail::query()->whereKey($payment->line_item_id)->where('order_id', $order->id)->lockForUpdate()->first()
                : null;
            $remaining = max(0, (int) $payment->expected_amount - (int) $payment->received_amount);
            $review = $purpose !== 'general' && ($payment->status === 'review'
                || ($remaining > 0 && (!$order || !$this->canAllocate($order, $purpose, $lineItem, $payment))));
            $allocated = $review ? 0 : ($purpose === 'general' ? $amount : min($amount, $remaining));
            $base = [
                'note' => 'SePay ' . $payment->code . ': ' . $payment->note,
                'user_id' => $payment->created_by,
                'store_id' => $payment->store_id,
                'bank_id' => $payment->bank_id,
                'cash_id' => null,
                'payment_method' => 2,
            ];
            $transaction = null;
            if ($allocated > 0) {
                $details = [
                    'name' => $this->ledgerName($purpose, $order),
                    'type' => $purpose === 'extension' ? Transaction::ADDON : Transaction::THU,
                    'value' => $allocated,
                    'order_id' => $payment->order_id,
                ];
                if ($purpose !== 'general') $details['object_name'] = $this->objectName($purpose);
                if ($purpose === 'extension') {
                    $details['object_type'] = 'line_item_id';
                    $details['object_id'] = $payment->line_item_id;
                    $details['status'] = 'approved';
                }
                $transaction = Transaction::create(array_merge($base, $details));
                if ($order) {
                    $this->applyAllocation($order, $payment, $lineItem, $transaction, $allocated);
                }
            }
            $excess = $amount - $allocated;
            $extraTransaction = $excess > 0 ? Transaction::create(array_merge($base, [
                'name' => 'receipt',
                'type' => Transaction::THU,
                'value' => $excess,
                'order_id' => null,
                'note' => $base['note'] . ($review ? ' (cần đối chiếu hợp đồng)' : ' (tiền chuyển dư)'),
            ])) : null;

            $received = (int) $payment->received_amount + $amount;
            $payment->update([
                'received_amount' => $received,
                'status' => $review ? 'review' : ($received < $payment->expected_amount ? 'partial'
                    : ($received === (int) $payment->expected_amount ? 'paid' : 'overpaid')),
            ]);
            $event->update([
                'status' => $review ? 'review' : 'matched',
                'payment_request_id' => $payment->id,
                'transaction_id' => $transaction ? $transaction->id : $extraTransaction->id,
                'excess_transaction_id' => $transaction && $extraTransaction ? $extraTransaction->id : null,
            ]);

            return ['status' => $review ? 'review' : 'matched', 'payment_request_id' => $payment->id];
        });
    }

    private function canAllocate(Order $order, string $purpose, ?OrderVehicleDetail $lineItem, ?SepayPaymentRequest $payment): bool
    {
        if (in_array($order->order_status, ['completed', 'cancel_pending_settlement', 'cancelled'], true) || $order->deposit_closed) return false;
        if ($purpose === 'general') return true;

        if ($payment === null) {
            $active = SepayPaymentRequest::query()->where('order_id', $order->id)->where('purpose', $purpose)
                ->whereIn('status', ['pending', 'partial']);
            if ($purpose === 'extension') $active->where('line_item_id', $lineItem ? $lineItem->id : 0);
            if ($active->exists()) return false;
        } elseif ($payment->status === 'review') {
            return false;
        }

        if ($purpose === 'extension') {
            return $lineItem && !$lineItem->completed_at && ($payment === null
                || Carbon::parse($lineItem->return_at)->format('Y-m-d H:i:s') === $payment->extension_from_at);
        }

        $field = ['deposit' => 'first_deposit_amount', 'rental' => 'total_rental_fees',
            'additional_deposit' => 'additional_deposit_amount'][$purpose] ?? null;
        if (!$field || (int) $order->{$field} !== (int) DB::table('sepay_webhook_events as events')
            ->join('sepay_payment_requests as requests', 'requests.id', '=', 'events.payment_request_id')
            ->join('transactions as ledger', 'ledger.id', '=', 'events.transaction_id')
            ->where('requests.order_id', $order->id)->where('requests.purpose', $purpose)
            ->sum('ledger.value')) return false;

        $existing = Transaction::query()->where('order_id', $order->id);
        if ($purpose === 'deposit') {
            if (!$order->created_without_collect_deposit) return false;
            $existing->where(function ($query) {
                $query->where('object_name', 'first_deposit')->orWhere('name', 'like', 'order:deposit:%');
            });
        } elseif ($purpose === 'rental') {
            if (!$order->created_without_collect_rental_fees) return false;
            if ($payment && (int) $order->total !== (int) $payment->expected_amount) return false;
            $existing->where(function ($query) {
                $query->where('object_name', 'rental_fees')->orWhere('name', 'order:rental_fees');
            });
        } elseif ($purpose === 'additional_deposit') {
            $existing->where(function ($query) {
                $query->where('object_name', 'additional_deposit')->orWhere('name', 'order:additional_deposit');
            });
            $existing->whereNotIn('id', DB::table('sepay_webhook_events as events')
                ->join('sepay_payment_requests as requests', 'requests.id', '=', 'events.payment_request_id')
                ->where('requests.order_id', $order->id)->where('requests.purpose', 'additional_deposit')
                ->whereNotNull('events.transaction_id')->select('events.transaction_id'));
        } else {
            return false;
        }
        if ($payment) {
            $existing->whereNotIn('id', SepayWebhookEvent::query()->where('payment_request_id', $payment->id)
                ->whereNotNull('transaction_id')->select('transaction_id'));
        }
        return !$existing->exists();
    }

    private function ledgerName(string $purpose, ?Order $order): string
    {
        if ($purpose === 'deposit') return $order->deposit_contract_created_at ? 'order:deposit:keep_vehicle' : 'order:deposit:' . $order->id;
        if ($purpose === 'rental') return 'order:rental_fees';
        if ($purpose === 'additional_deposit') return 'order:additional_deposit';
        if ($purpose === 'extension') return 'addon';
        return 'receipt';
    }

    private function objectName(string $purpose): ?string
    {
        return ['deposit' => 'first_deposit', 'rental' => 'rental_fees', 'additional_deposit' => 'additional_deposit'][$purpose] ?? null;
    }

    private function applyAllocation(Order $order, SepayPaymentRequest $payment, ?OrderVehicleDetail $lineItem, Transaction $transaction, int $amount): void
    {
        $purpose = $payment->purpose;
        $updates = ['pid' => (int) $order->pid + $amount];
        $field = ['deposit' => 'first_deposit_amount', 'rental' => 'total_rental_fees',
            'additional_deposit' => 'additional_deposit_amount'][$purpose] ?? null;
        if ($field) {
            $total = (int) $order->{$field} + $amount;
            $methodField = ['deposit' => 'first_deposit_payment_method', 'rental' => 'total_rental_payment_method',
                'additional_deposit' => 'additional_deposit_payment_method'][$purpose];
            $previous = @unserialize((string) $order->{$methodField}, ['allowed_classes' => false]);
            $rowIds = is_array($previous) && isset($previous['row_ids']) ? (array) $previous['row_ids'] : [];
            $rowIds[] = $transaction->id;
            $updates[$field] = $total;
            $updates[$methodField] = serialize(['payment_method' => [
                'payment_method' => 2, 'bank_id' => $payment->bank_id,
                'bank_transfer_amount' => $total, 'cash_amount' => 0,
            ], 'row_ids' => $rowIds]);
            if ($purpose === 'deposit' && (int) $payment->received_amount + $amount >= (int) $payment->expected_amount) {
                $updates['created_without_collect_deposit'] = false;
            }
            if ($purpose === 'rental' && (int) $payment->received_amount + $amount >= (int) $payment->expected_amount) {
                $updates['created_without_collect_rental_fees'] = false;
            }
        }
        $order->update($updates);
        if ($purpose === 'extension' && (int) $payment->received_amount + $amount >= (int) $payment->expected_amount) {
            OrderVehicleDetail::query()->whereKey($lineItem->id)->update([
                'return_at' => $payment->extension_return_at,
                'total_renewal_amount' => (int) $lineItem->total_renewal_amount + (int) $payment->expected_amount,
            ]);
            CarRentalHelper::writeMoneyOutDateAndTotal($order);
        }
    }

    private function paymentCode(array $payload): ?string
    {
        $providerCode = strtoupper(trim((string) ($payload['code'] ?? '')));
        if (preg_match(self::CODE_PATTERN, $providerCode)) {
            return $providerCode;
        }
        if ($providerCode !== '') {
            return null;
        }

        preg_match_all('/(?:^|[^A-Z0-9])(HMT[A-Z0-9]{10})(?=$|[^A-Z0-9])/i', (string) ($payload['content'] ?? ''), $matches);
        $codes = array_unique(array_map('strtoupper', $matches[1]));
        return count($codes) === 1 ? reset($codes) : null;
    }

    private function newPaymentCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bytes = random_bytes(10);
        $suffix = '';
        for ($index = 0; $index < 10; $index++) {
            $suffix .= $alphabet[ord($bytes[$index]) & 31];
        }
        return 'HMT' . $suffix;
    }

    private function present(SepayPaymentRequest $request, Bank $bank): array
    {
        $currentAccount = (int) $request->bank_id === (int) $bank->id
            && (string) $request->account_number === (string) $bank->account_number;
        $query = http_build_query([
            'acc' => $request->account_number,
            'bank' => $this->bankDefinition($request->bank_code)['name'],
            'amount' => $request->expected_amount,
            'des' => $request->code,
            'template' => 'compact',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'id' => $request->id,
            'code' => $request->code,
            'store_id' => $request->store_id,
            'order_id' => $request->order_id,
            'purpose' => $request->purpose ?: 'general',
            'line_item_id' => $request->line_item_id,
            'extension_return_at' => $request->extension_return_at,
            'expected_amount' => (int) $request->expected_amount,
            'received_amount' => (int) $request->received_amount,
            'status' => $request->status,
            'note' => $request->note,
            'account_number' => $request->account_number,
            'account_holder' => $request->account_holder,
            'bank_name' => $this->bankDefinition($request->bank_code)['name'],
            'qr_url' => $currentAccount ? 'https://vietqr.app/img?' . $query : null,
            'created_at' => $request->created_at,
        ];
    }
}
