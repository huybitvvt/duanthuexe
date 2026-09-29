<?php

namespace App\Http\Services;

use App\Models\BusinessApprovalRequest;
use App\Models\DebtNote;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Cash;
use App\Models\Bank;
use App\Models\DailyCashRegister;
use App\Models\Vehicle;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\PermissionAccess;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessApprovalService
{
    private const ORDER_PROPOSE = [
        'order_cancel' => 'order.cancel_request',
        'order_discount' => 'order.discount_request',
    ];
    private const ORDER_APPROVE = [
        'order_cancel' => 'order.cancel_approve',
        'order_discount' => 'order.discount_approve',
    ];

    public function submitOrder(int $orderId, string $action, array $data, User $user): BusinessApprovalRequest
    {
        if (!isset(self::ORDER_PROPOSE[$action])) {
            throw ValidationException::withMessages(['action' => 'Nghiệp vụ đề nghị không hợp lệ.']);
        }
        return DB::transaction(function () use ($orderId, $action, $data, $user) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            PermissionAccess::can($user, self::ORDER_PROPOSE[$action], (int) $order->store_id);
            $this->assertOrderOpen($order);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'Cần ghi rõ lý do đề nghị.']);
            }
            if (BusinessApprovalRequest::where('subject_type', 'order')->where('subject_id', $orderId)
                ->where('status', 'submitted')->exists()) {
                throw ValidationException::withMessages(['action' => 'Đơn đã có đề nghị chờ duyệt.']);
            }
            $payload = [];
            if ($action === 'order_discount') {
                $this->assertDiscountReady($order);
                $amount = (int) ($data['amount'] ?? 0);
                if ($amount <= 0 || $amount > (int) $order->total) {
                    throw ValidationException::withMessages(['amount' => 'Số tiền giảm phải lớn hơn 0 và không vượt tổng đơn.']);
                }
                $payload = ['amount' => $amount, 'old_total' => (int) $order->total];
            } else {
                $this->assertCancellationReady($order);
            }
            $request = BusinessApprovalRequest::create([
                'subject_type' => 'order', 'subject_id' => $orderId,
                'store_id' => $order->store_id, 'action' => $action,
                'status' => 'submitted', 'reason' => $reason, 'payload' => $payload,
                'requested_by' => $user->id,
            ])->load('requester');
            $this->logOrder($order->id, $user->id, 'order:approval-request',
                'Đã gửi đề nghị #' . $request->id . ': ' . $action);
            return $request;
        });
    }
    private const LEASE_PROPOSE = [
        'lease_schedule' => 'lease.schedule_propose',
        'lease_recovery' => 'lease.recovery_propose',
        'lease_liquidation' => 'lease.liquidation_propose',
    ];

    private const LEASE_APPROVE = [
        'lease_schedule' => 'lease.schedule_approve',
        'lease_recovery' => 'lease.recovery_approve',
        'lease_liquidation' => 'lease.liquidation_approve',
    ];

    public function submitLease(int $contractId, string $action, array $data, User $user): BusinessApprovalRequest
    {
        if (!isset(self::LEASE_PROPOSE[$action])) {
            throw ValidationException::withMessages(['action' => 'Nghiệp vụ đề nghị không hợp lệ.']);
        }
        return DB::transaction(function () use ($contractId, $action, $data, $user) {
            $contract = LeaseContract::whereKey($contractId)->lockForUpdate()->firstOrFail();
            PermissionAccess::can($user, self::LEASE_PROPOSE[$action], (int) $contract->store_id);
            if (!in_array($contract->status, [LeaseContract::STATUS_ACTIVE, LeaseContract::STATUS_DEFAULTED], true)) {
                throw ValidationException::withMessages(['contract' => 'Hợp đồng chưa có hiệu lực hoặc đã kết thúc.']);
            }
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'Cần ghi rõ lý do đề nghị.']);
            }
            if (BusinessApprovalRequest::where('subject_type', 'lease')->where('subject_id', $contractId)
                ->where('action', $action)->where('status', 'submitted')->exists()) {
                throw ValidationException::withMessages(['action' => 'Đã có đề nghị đang chờ duyệt cho nghiệp vụ này.']);
            }

            $payload = [];
            if ($action === 'lease_schedule') {
                $installmentQuery = $contract->installments();
                $installment = isset($data['period_number'])
                    ? $installmentQuery->where('period_number', (int) $data['period_number'])->firstOrFail()
                    : $installmentQuery->whereKey((int) ($data['installment_id'] ?? 0))->firstOrFail();
                if ($installment->remaining_amount <= 0) {
                    throw ValidationException::withMessages(['installment_id' => 'Kỳ đã thanh toán không được đổi ngày đến hạn.']);
                }
                $newDueDate = $this->date($data['new_due_date'] ?? null);
                if ($newDueDate === $installment->due_date->toDateString()) {
                    throw ValidationException::withMessages(['new_due_date' => 'Ngày đến hạn mới phải khác ngày hiện tại.']);
                }
                $payload = [
                    'installment_id' => $installment->id,
                    'old_due_date' => $installment->due_date->toDateString(),
                    'new_due_date' => $newDueDate,
                ];
            } elseif ($action === 'lease_liquidation') {
                if ($contract->status !== LeaseContract::STATUS_DEFAULTED
                    || !BusinessApprovalRequest::where('subject_type', 'lease')->where('subject_id', $contractId)
                        ->where('action', 'lease_recovery')->where('status', 'approved')->exists()) {
                    throw ValidationException::withMessages(['action' => 'Thanh lý chỉ được đề nghị sau khi lệnh thu hồi nợ xấu đã được duyệt.']);
                }
                $payload = ['plan' => trim((string) ($data['plan'] ?? ''))];
                if ($payload['plan'] === '') {
                    throw ValidationException::withMessages(['plan' => 'Cần mô tả phương án thanh lý.']);
                }
            } else {
                $payload = ['plan' => trim((string) ($data['plan'] ?? ''))];
                if ($payload['plan'] === '') {
                    throw ValidationException::withMessages(['plan' => 'Cần mô tả phương án thu hồi.']);
                }
            }

            return BusinessApprovalRequest::create([
                'subject_type' => 'lease', 'subject_id' => $contractId,
                'store_id' => $contract->store_id, 'action' => $action,
                'status' => 'submitted', 'reason' => $reason, 'payload' => $payload,
                'requested_by' => $user->id,
            ])->load(['requester']);
        });
    }

    public function decide(int $requestId, bool $approve, string $note, User $user): BusinessApprovalRequest
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['decision_note' => 'Cần ghi lý do quyết định.']);
        }
        return DB::transaction(function () use ($requestId, $approve, $note, $user) {
            $request = BusinessApprovalRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Đề nghị này đã được xử lý.']);
            }
            if ((int) $request->requested_by === (int) $user->id) {
                throw new AuthorizationException('Người đề nghị không được tự duyệt.');
            }
            $permission = $request->subject_type === 'order'
                ? (self::ORDER_APPROVE[$request->action] ?? null)
                : ($request->subject_type === 'lease' ? (self::LEASE_APPROVE[$request->action] ?? null) : null);
            if (!$permission) {
                throw ValidationException::withMessages(['action' => 'Loại phê duyệt chưa được hỗ trợ.']);
            }
            PermissionAccess::can($user, $permission, (int) $request->store_id);
            if ($request->subject_type === 'order') {
                $order = Order::whereKey($request->subject_id)->lockForUpdate()->firstOrFail();
                if ((int) $order->store_id !== (int) $request->store_id) {
                    throw new AuthorizationException('Đơn đã đổi cơ sở; cần lập lại đề nghị.');
                }
                if ($approve) {
                    $this->applyOrder($request, $order);
                }
            } else {
                $contract = LeaseContract::whereKey($request->subject_id)->lockForUpdate()->firstOrFail();
                if ((int) $contract->store_id !== (int) $request->store_id) {
                    throw new AuthorizationException('Hợp đồng đã đổi cơ sở; cần lập lại đề nghị.');
                }
                if ($approve) {
                    $this->applyLease($request, $contract, $user);
                }
            }
            $request->update([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decided_at' => Carbon::now(),
                'decision_note' => trim($note),
            ]);
            if ($request->subject_type === 'order') {
                $this->logOrder($request->subject_id, $user->id, 'order:approval-decision',
                    ($approve ? 'Đã duyệt' : 'Đã từ chối') . ' đề nghị #' . $request->id . ': ' . $request->action);
            }
            return $request->fresh()->load(['requester', 'decider']);
        });
    }

    public function index(array $filters, User $user)
    {
        $query = BusinessApprovalRequest::with(['requester:id,name', 'decider:id,name'])->orderBy('id', 'desc');
        if (isset($filters['subject_type']) && in_array($filters['subject_type'], ['order', 'lease'], true)) {
            $query->where('subject_type', $filters['subject_type']);
        }
        if (isset($filters['status']) && in_array($filters['status'], ['submitted', 'approved', 'rejected', 'settled'], true)) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['subject_id'])) {
            $query->where('subject_id', (int) $filters['subject_id']);
        }
        $companyWide = in_array(PermissionAccess::getRoleSlug($user), ['ban-giam-doc', 'van-hanh'], true);
        if (!PermissionAccess::isAdmin($user) && !$companyWide) {
            $approver = PermissionAccess::allows($user, 'approval.decide');
            $query->where(function ($q) use ($user, $approver) {
                $q->where('requested_by', $user->id);
                if ($approver && $user->store_id) {
                    $q->orWhere('store_id', (int) $user->store_id);
                }
            });
        }
        return $query->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function settleCancellation(int $requestId, array $data, User $user): BusinessApprovalRequest
    {
        return DB::transaction(function () use ($requestId, $data, $user) {
            $request = BusinessApprovalRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->subject_type !== 'order' || $request->action !== 'order_cancel'
                || $request->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Đề nghị hủy chưa được duyệt hoặc đã quyết toán.']);
            }
            PermissionAccess::can($user, 'order.cancel_settle', (int) $request->store_id);
            if ((int) $request->requested_by === (int) $user->id) {
                throw new AuthorizationException('Người đề nghị không được tự quyết toán hủy đơn.');
            }
            $order = Order::whereKey($request->subject_id)->lockForUpdate()->firstOrFail();
            if ($order->order_status !== 'cancel_pending_settlement'
                || (int) $order->store_id !== (int) $request->store_id) {
                throw ValidationException::withMessages(['order' => 'Trạng thái đơn hoặc cơ sở không còn khớp quyết định hủy.']);
            }
            $received = (int) Transaction::where('order_id', $order->id)->where('status', 'approved')
                ->whereIn('type', [Transaction::THU, Transaction::ADDON])->sum('value');
            $paidOut = (int) Transaction::where('order_id', $order->id)->where('status', 'approved')
                ->where('type', Transaction::CHI)->sum('value');
            $refund = $received - $paidOut;
            if ($refund < 0) {
                throw ValidationException::withMessages(['refund' => 'Sổ giao dịch âm; cần kế toán đối soát trước khi quyết toán.']);
            }
            $payload = $request->payload ?: [];
            if ($refund !== (int) ($payload['expected_refund'] ?? -1)) {
                throw ValidationException::withMessages(['refund' => 'Số tiền đã thu thay đổi sau khi duyệt; cần đối soát trước khi hoàn.']);
            }
            if ($refund > 0) {
                $registerClosed = DailyCashRegister::where('store_id', $order->store_id)
                    ->where('register_date', Carbon::now('Asia/Ho_Chi_Minh')->toDateString())
                    ->whereIn('status', ['submitted', 'closed'])->exists();
                if ($registerClosed) {
                    throw ValidationException::withMessages(['refund' => 'Sổ két hôm nay đã gửi duyệt hoặc đóng.']);
                }
                $channel = $data['channel'] ?? null;
                $sourceId = (int) ($data['source_id'] ?? 0);
                if ($channel === 'cash') {
                    $source = Cash::whereKey($sourceId)->where('store_id', $order->store_id)
                        ->where(function ($q) { $q->where('status', 'Active')->orWhereNull('status'); })->first();
                } elseif ($channel === 'bank') {
                    $bankQuery = Bank::whereKey($sourceId)->where('store_id', $order->store_id);
                    if (Schema::hasColumn('banks', 'status')) {
                        $bankQuery->where(function ($q) { $q->where('status', 'Active')->orWhereNull('status'); });
                    }
                    $source = $bankQuery->first();
                } else {
                    $source = null;
                }
                if (!$source) {
                    throw ValidationException::withMessages(['source_id' => 'Chọn két hoặc tài khoản ngân hàng hợp lệ.']);
                }
                $transaction = Transaction::create([
                    'order_id' => $order->id,
                    'name' => 'order:cancel_refund:' . $request->id,
                    'type' => Transaction::CHI,
                    'value' => $refund,
                    'note' => 'Hoàn tiền hủy đơn theo duyệt #' . $request->id . ': ' . trim((string) ($data['note'] ?? '')),
                    'status' => 'approved', 'user_id' => $user->id,
                    'store_id' => $order->store_id,
                    'payment_method' => $channel === 'bank' ? 2 : 1,
                    'bank_id' => $channel === 'bank' ? $source->id : null,
                    'cash_id' => $channel === 'cash' ? $source->id : null,
                ]);
                $payload['refund_transaction_id'] = $transaction->id;
            }
            $payload['refund_amount'] = $refund;
            $payload['settled_by'] = $user->id;
            $payload['settled_at'] = Carbon::now()->toDateTimeString();
            $request->update(['status' => 'settled', 'payload' => $payload]);
            $order->update(['order_status' => 'cancelled']);
            $this->logOrder($order->id, $user->id, 'order:cancellation-settled',
                'Đã quyết toán hủy theo duyệt #' . $request->id . '; hoàn ' . $refund . ' đ.');
            $vehicleIds = $order->orderItems()->pluck('vehicle_id')->all();
            foreach ($vehicleIds as $vehicleId) {
                if (!Order::where('id', '!=', $order->id)->whereIn('order_status', ['renting', 'deposit_contract', 'bad_debt'])
                    ->whereHas('orderItems', function ($q) use ($vehicleId) { $q->where('vehicle_id', $vehicleId); })->exists()) {
                    Vehicle::whereKey($vehicleId)->where('status', Vehicle::STATUS_USING)
                        ->update(['status' => Vehicle::STATUS_READY]);
                }
            }
            return $request->fresh()->load(['requester', 'decider']);
        });
    }

    private function assertOrderOpen(Order $order): void
    {
        if (!in_array($order->order_status, ['renting', 'deposit_contract', 'draft'], true)) {
            throw ValidationException::withMessages(['order' => 'Đơn không ở trạng thái được đề nghị.']);
        }
    }

    private function assertCancellationReady(Order $order): void
    {
        if ($order->contract_is_locked && $order->orderItems()->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['order' => 'Xe đã bàn giao; cần ghi nhận nhận lại xe trước khi hủy.']);
        }
    }

    private function assertDiscountReady(Order $order): void
    {
        if ($order->contract_is_locked || data_get($order->contract_snapshot, 'is_locked')) {
            throw ValidationException::withMessages(['order' => 'Hợp đồng đã chốt; không thể đổi giá.']);
        }
    }

    private function applyOrder(BusinessApprovalRequest $request, Order $order): void
    {
        $this->assertOrderOpen($order);
        if ($request->action === 'order_cancel') {
            $this->assertCancellationReady($order);
            if (Schema::hasTable('sepay_payment_requests')
                && DB::table('sepay_payment_requests')->where('order_id', $order->id)
                    ->whereIn('status', ['pending', 'partial'])->exists()) {
                throw ValidationException::withMessages(['order' => 'Đang có yêu cầu thanh toán SePay; cần đóng yêu cầu trước khi duyệt hủy.']);
            }
            $received = (int) Transaction::where('order_id', $order->id)->where('status', 'approved')
                ->whereIn('type', [Transaction::THU, Transaction::ADDON])->sum('value');
            $paidOut = (int) Transaction::where('order_id', $order->id)->where('status', 'approved')
                ->where('type', Transaction::CHI)->sum('value');
            if ($received < $paidOut) {
                throw ValidationException::withMessages(['order' => 'Sổ giao dịch âm; cần đối soát trước khi duyệt hủy.']);
            }
            $request->payload = ['expected_refund' => $received - $paidOut];
            $order->update(['order_status' => 'cancel_pending_settlement']);
            return;
        }
        $this->assertDiscountReady($order);
        $payload = $request->payload ?: [];
        if ((int) $order->total !== (int) ($payload['old_total'] ?? -1)) {
            throw ValidationException::withMessages(['total' => 'Giá trị đơn đã thay đổi; cần lập lại đề nghị giảm giá.']);
        }
        $amount = (int) ($payload['amount'] ?? 0);
        if ($amount <= 0 || $amount > (int) $order->total) {
            throw ValidationException::withMessages(['amount' => 'Số tiền giảm không còn hợp lệ.']);
        }
        $order->update([
            'total' => (int) $order->total - $amount,
            'approved_discount_amount' => (int) $order->approved_discount_amount + $amount,
        ]);
    }

    private function logOrder(int $orderId, int $userId, string $name, string $content): void
    {
        if (Schema::hasTable('activity_logs')) {
            ActivityLog::create([
                'order_id' => $orderId, 'user_id' => $userId,
                'name' => $name, 'action' => 'create', 'content' => $content,
            ]);
        }
    }

    private function applyLease(BusinessApprovalRequest $request, LeaseContract $contract, User $user): void
    {
        if (!in_array($contract->status, [LeaseContract::STATUS_ACTIVE, LeaseContract::STATUS_DEFAULTED], true)) {
            throw ValidationException::withMessages(['contract' => 'Hợp đồng đã kết thúc; không thể duyệt đề nghị.']);
        }
        if ($request->action === 'lease_schedule') {
            $payload = $request->payload;
            $installment = $contract->installments()->whereKey((int) $payload['installment_id'])
                ->lockForUpdate()->firstOrFail();
            if ($installment->remaining_amount <= 0
                || $installment->due_date->toDateString() !== $payload['old_due_date']) {
                throw ValidationException::withMessages(['installment_id' => 'Kỳ đã thanh toán hoặc lịch đã thay đổi; cần lập lại đề nghị.']);
            }
            $date = $this->date($payload['new_due_date']);
            $previous = $contract->installments()->where('period_number', '<', $installment->period_number)
                ->orderBy('period_number', 'desc')->first();
            $next = $contract->installments()->where('period_number', '>', $installment->period_number)
                ->orderBy('period_number')->first();
            if (($previous && $date < $previous->due_date->toDateString())
                || ($next && $date > $next->due_date->toDateString())) {
                throw ValidationException::withMessages(['new_due_date' => 'Ngày mới phải nằm giữa hai kỳ liền kề.']);
            }
            $installment->update(['due_date' => $date,
                'notes' => trim(($installment->notes ? $installment->notes . "\n" : '')
                    . 'Đổi ngày đến hạn theo duyệt #' . $request->id . ' bởi ' . $user->name),
            ]);
            if (!$next) {
                $contract->update(['end_date' => $date]);
            }
            return;
        }
        $label = $request->action === 'lease_recovery' ? 'Lệnh thu hồi xe được duyệt' : 'Phương án thanh lý được duyệt';
        DebtNote::create([
            'lease_contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'note_content' => $label . ' #' . $request->id . '. '
                . ($request->payload['plan'] ?? '') . ' Lý do: ' . $request->reason,
            'debt_classification' => DebtNote::CLASSIFICATION_BAD_DEBT,
            'created_by' => $user->id,
        ]);
    }

    private function date($value): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages(['new_due_date' => 'Ngày phải có dạng YYYY-MM-DD.']);
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->toDateString() !== $value) {
                throw new \InvalidArgumentException();
            }
            return $date->toDateString();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['new_due_date' => 'Ngày không hợp lệ.']);
        }
    }
}
