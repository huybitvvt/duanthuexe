<?php

namespace App\Http\Services;

use App\Models\ContractAmendment;
use App\Models\Bank;
use App\Models\Cash;
use App\Support\PilotAccess;
use App\Support\HimotoStores;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocationEvent;
use App\Models\VehicleTransfer;
use App\Models\VehicleTransferItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VehicleTransferService
{
    /**
     * Flow A: Create and dispatch a store-to-store vehicle transfer.
     */
    public function dispatchStoreTransfer(array $data, User $user): VehicleTransfer
    {
        $fromStoreId = (int)data_get($data, 'from_store_id');
        $toStoreId = (int)data_get($data, 'to_store_id');
        $vehicleIds = (array)data_get($data, 'vehicle_ids', []);
        $reason = (string)data_get($data, 'reason', 'Điều chuyển nội bộ');
        $notes = (string)data_get($data, 'notes', '');
        $idempotencyKey = data_get($data, 'idempotency_key');

        $fromStore = Store::findOrFail($fromStoreId);
        $toStore = Store::findOrFail($toStoreId);
        if (!HimotoStores::isCanonical($fromStore) || !HimotoStores::isCanonical($toStore)) {
            throw ValidationException::withMessages([
                'store_id' => ['Chỉ được điều chuyển giữa 6 kho HIMOTO đã cấu hình.'],
            ]);
        }
        if ($fromStoreId === $toStoreId) {
            throw ValidationException::withMessages([
                'to_store_id' => ['Kho nhận không được trùng với kho xuất phát.']
            ]);
        }

        if (empty($vehicleIds)) {
            throw ValidationException::withMessages([
                'vehicle_ids' => ['Vui lòng chọn ít nhất một xe để điều chuyển.']
            ]);
        }

        $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
        if (!$isAdmin && (int)$user->store_id !== $fromStoreId) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Bạn chỉ được phép điều chuyển xe từ cơ sở được phân công quản lý.'
            );
        }

        if ($idempotencyKey) {
            $existing = VehicleTransfer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                PilotAccess::store($user, $existing->from_store_id);
                $expectedIds = array_map('intval', $vehicleIds);
                sort($expectedIds);
                $actualIds = $existing->items()->orderBy('vehicle_id')->pluck('vehicle_id')->map(function ($id) { return (int)$id; })->all();
                if ((int)$existing->from_store_id !== $fromStoreId || (int)$existing->to_store_id !== $toStoreId || $actualIds !== $expectedIds) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Mã yêu cầu đã dùng cho phiếu điều chuyển khác.']);
                }
                return $existing->load(['fromStore', 'toStore', 'items.vehicle']);
            }
        }

        return DB::transaction(function () use ($fromStoreId, $toStoreId, $vehicleIds, $reason, $notes, $idempotencyKey, $user) {
            // Sort vehicle IDs to prevent database deadlocks
            sort($vehicleIds);
            $vehicles = Vehicle::whereIn('id', $vehicleIds)->lockForUpdate()->get();

            if ($vehicles->count() !== count($vehicleIds)) {
                throw ValidationException::withMessages([
                    'vehicle_ids' => ['Một hoặc nhiều xe được chọn không tồn tại trong hệ thống.']
                ]);
            }

            foreach ($vehicles as $vehicle) {
                // Must be ready
                if ($vehicle->status !== Vehicle::STATUS_READY) {
                    throw ValidationException::withMessages([
                        'vehicle_ids' => ["Xe {$vehicle->license} ({$vehicle->name}) đang ở trạng thái '{$vehicle->status}', không đủ điều kiện chuyển kho."]
                    ]);
                }

                // Must be physically at from_store
                $effectiveStore = $vehicle->current_store_id ?: $vehicle->store_id;
                if ((int)$effectiveStore !== $fromStoreId) {
                    throw ValidationException::withMessages([
                        'vehicle_ids' => ["Xe {$vehicle->license} hiện không có mặt tại kho xuất phát."]
                    ]);
                }
            }

            // Create transfer record
            $code = 'TF-' . Carbon::now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $transfer = VehicleTransfer::create([
                'transfer_code' => $code,
                'type' => VehicleTransfer::TYPE_STORE_TO_STORE,
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'status' => VehicleTransfer::STATUS_DISPATCHED,
                'dispatched_by' => $user->id,
                'dispatched_at' => Carbon::now(),
                'reason' => $reason,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($vehicles as $vehicle) {
                // Mark vehicle as in_transit
                $vehicle->update([
                    'status' => Vehicle::STATUS_IN_TRANSIT,
                ]);

                // Create transfer item
                VehicleTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'vehicle_id' => $vehicle->id,
                    'source_status' => Vehicle::STATUS_READY,
                    'target_status' => Vehicle::STATUS_READY,
                    'odometer_out' => $vehicle->odometer,
                    'condition_notes' => 'Xuất kho điều chuyển',
                ]);

                // Record immutable location event
                VehicleLocationEvent::create([
                    'vehicle_id' => $vehicle->id,
                    'from_store_id' => $fromStoreId,
                    'to_store_id' => $toStoreId,
                    'event_type' => VehicleLocationEvent::EVENT_TRANSFER_DISPATCH,
                    'ref_type' => 'vehicle_transfers',
                    'ref_id' => $transfer->id,
                    'odometer' => $vehicle->odometer,
                    'notes' => "Xuất kho điều chuyển đến kho ID #{$toStoreId}. Lý do: {$reason}",
                    'created_by' => $user->id,
                ]);
            }

            return $transfer->load(['fromStore', 'toStore', 'items.vehicle']);
        });
    }

    /**
     * Flow A: Confirm receiving transferred vehicles at destination store.
     */
    public function receiveStoreTransfer(int $transferId, array $data, User $user): VehicleTransfer
    {
        return DB::transaction(function () use ($transferId, $data, $user) {
            $transfer = VehicleTransfer::where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== VehicleTransfer::STATUS_DISPATCHED) {
                throw ValidationException::withMessages([
                    'transfer' => ["Phiếu điều chuyển #{$transfer->transfer_code} không ở trạng thái đang chuyển (trạng thái hiện tại: {$transfer->status})."]
                ]);
            }

            $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
            if (!$isAdmin && (int)$user->store_id !== (int)$transfer->to_store_id) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Chỉ nhân viên cơ sở nhận mới có quyền xác nhận nhập kho.'
                );
            }

            $items = VehicleTransferItem::where('transfer_id', $transfer->id)->get();
            $vehicleIds = $items->pluck('vehicle_id')->toArray();
            sort($vehicleIds);
            $vehicles = Vehicle::whereIn('id', $vehicleIds)->lockForUpdate()->get()->keyBy('id');

            $odometers = (array)data_get($data, 'odometers', []);
            $conditionNotes = (array)data_get($data, 'condition_notes', []);

            foreach ($items as $item) {
                $vehicle = $vehicles->get($item->vehicle_id);
                if (!$vehicle) {
                    continue;
                }

                $odoIn = isset($odometers[$vehicle->id]) ? (int)$odometers[$vehicle->id] : $vehicle->odometer;
                $noteIn = isset($conditionNotes[$vehicle->id]) ? (string)$conditionNotes[$vehicle->id] : 'Nhập kho thành công';

                // Update vehicle location and ready status
                $vehicle->update([
                    'current_store_id' => $transfer->to_store_id,
                    'status' => Vehicle::STATUS_READY,
                    'odometer' => $odoIn ?: $vehicle->odometer,
                ]);

                // Update transfer item
                $item->update([
                    'odometer_in' => $odoIn,
                    'condition_notes' => $noteIn,
                ]);

                // Record immutable location event
                VehicleLocationEvent::create([
                    'vehicle_id' => $vehicle->id,
                    'from_store_id' => $transfer->from_store_id,
                    'to_store_id' => $transfer->to_store_id,
                    'event_type' => VehicleLocationEvent::EVENT_TRANSFER_RECEIVE,
                    'ref_type' => 'vehicle_transfers',
                    'ref_id' => $transfer->id,
                    'odometer' => $odoIn,
                    'notes' => "Đã nhận xe tại kho đích ID #{$transfer->to_store_id}. {$noteIn}",
                    'created_by' => $user->id,
                ]);
            }

            $transfer->update([
                'status' => VehicleTransfer::STATUS_COMPLETED,
                'received_by' => $user->id,
                'received_at' => Carbon::now(),
            ]);

            return $transfer->load(['fromStore', 'toStore', 'items.vehicle']);
        });
    }

    /**
     * Flow A: Cancel an in-transit transfer and revert vehicles to source store.
     */
    public function cancelStoreTransfer(int $transferId, string $reason, User $user): VehicleTransfer
    {
        return DB::transaction(function () use ($transferId, $reason, $user) {
            $transfer = VehicleTransfer::where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== VehicleTransfer::STATUS_DISPATCHED) {
                throw ValidationException::withMessages([
                    'transfer' => ["Không thể hủy phiếu điều chuyển ở trạng thái '{$transfer->status}'."]
                ]);
            }

            $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
            if (!$isAdmin && (int)$user->store_id !== (int)$transfer->from_store_id) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Chỉ nhân viên cơ sở xuất hoặc Quản trị viên mới được hủy điều chuyển.'
                );
            }

            $items = VehicleTransferItem::where('transfer_id', $transfer->id)->get();
            $vehicleIds = $items->pluck('vehicle_id')->toArray();
            sort($vehicleIds);
            $vehicles = Vehicle::whereIn('id', $vehicleIds)->lockForUpdate()->get();

            foreach ($vehicles as $vehicle) {
                $vehicle->update([
                    'status' => Vehicle::STATUS_READY,
                ]);

                VehicleLocationEvent::create([
                    'vehicle_id' => $vehicle->id,
                    'from_store_id' => $transfer->from_store_id,
                    'to_store_id' => $transfer->from_store_id,
                    'event_type' => VehicleLocationEvent::EVENT_TRANSFER_CANCEL,
                    'ref_type' => 'vehicle_transfers',
                    'ref_id' => $transfer->id,
                    'odometer' => $vehicle->odometer,
                    'notes' => "Hủy điều chuyển, xe hoàn kho xuất phát. Lý do: {$reason}",
                    'created_by' => $user->id,
                ]);
            }

            $transfer->update([
                'status' => VehicleTransfer::STATUS_CANCELLED,
                'notes' => ($transfer->notes ? $transfer->notes . "\n" : "") . "[Hủy bỏ lúc " . Carbon::now()->format('d/m/Y H:i') . " bởi User #{$user->id}: {$reason}]",
            ]);

            return $transfer->load(['fromStore', 'toStore', 'items.vehicle']);
        });
    }

    /**
     * Flow B: Process customer returning vehicle at a different store branch.
     * Updates physical vehicle location to receiving store while preserving historical order revenue store.
     */
    public function processReturnDifferentStore(int $orderId, int $returnStoreId, array $details, User $user): array
    {
        return DB::transaction(function () use ($orderId, $returnStoreId, $details, $user) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $returnStore = Store::findOrFail($returnStoreId);
            if (!HimotoStores::isCanonical($returnStore)) {
                throw ValidationException::withMessages(['return_store_id' => 'Kho nhận không thuộc danh mục 6 kho HIMOTO.']);
            }
            PilotAccess::store($user, $returnStoreId);
            if (!in_array($order->order_status, ['completed', 'wait_payment'])) {
                throw ValidationException::withMessages(['order_id' => 'Hoàn tất trả xe qua luồng trả xe trước khi xác nhận nhập kho khác cơ sở.']);
            }
            $orderVehicleQuery = OrderVehicleDetail::where('order_id', $orderId);
            $totalVehiclesInOrder = $orderVehicleQuery->count();

            $specifiedVehicleId = data_get($details, 'vehicle_id');
            if ($specifiedVehicleId) {
                $orderVehicleDetail = OrderVehicleDetail::where('order_id', $orderId)->where('vehicle_id', $specifiedVehicleId)->first();
                if (!$orderVehicleDetail) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Xe được chỉ định không thuộc đơn thuê này.']);
                }
                $vehicleId = (int) $orderVehicleDetail->vehicle_id;
            } else {
                if ($totalVehiclesInOrder > 1) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Đơn thuê nhiều xe: vui lòng chỉ định cụ thể xe (vehicle_id) cần nhập kho khác cơ sở.']);
                }
                $orderVehicleDetail = $orderVehicleQuery->first();
                $vehicleId = $orderVehicleDetail ? (int) $orderVehicleDetail->vehicle_id : null;
            }

            if (!$vehicleId) {
                throw ValidationException::withMessages([
                    'order_id' => ['Không tìm thấy thông tin xe trong đơn thuê.']
                ]);
            }

            // Kiểm tra xe này đã được trả khác cơ sở chưa
            $existing = VehicleTransfer::where('order_id', $orderId)
                ->where('type', VehicleTransfer::TYPE_RETURN_DIFFERENT_STORE)
                ->whereHas('items', function ($q) use ($vehicleId) {
                    $q->where('vehicle_id', $vehicleId);
                })
                ->first();
            if ($existing) {
                throw ValidationException::withMessages(['vehicle_id' => 'Xe này trong đơn đã được ghi nhận nhập kho khác cơ sở trước đó.']);
            }

            $orderStoreId = (int)$order->store_id;
            $odometer = data_get($details, 'odometer');
            $conditionNotes = (string)data_get($details, 'condition_notes', 'Khách trả xe tại chi nhánh khác');
            $returnedAt = data_get($details, 'returned_at') ? Carbon::parse($details['returned_at']) : Carbon::now();

            $vehicle = Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();
            if ($vehicle->status !== Vehicle::STATUS_READY) {
                throw ValidationException::withMessages(['vehicle_id' => 'Xe chưa sẵn sàng nhập kho hoặc đã được giao cho đơn khác.']);
            }
            $oldLocationStoreId = $vehicle->current_store_id ?: $orderStoreId;

            // Update vehicle current store location
            $vehicleUpdates = [
                'current_store_id' => $returnStoreId,
                'status' => Vehicle::STATUS_READY,
            ];
            if ($odometer) {
                $vehicleUpdates['odometer'] = (int)$odometer;
            }
            $vehicle->update($vehicleUpdates);

            // Create transfer audit record
            $code = 'RET-' . Carbon::now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $transfer = VehicleTransfer::create([
                'transfer_code' => $code,
                'type' => VehicleTransfer::TYPE_RETURN_DIFFERENT_STORE,
                'from_store_id' => $oldLocationStoreId,
                'to_store_id' => $returnStoreId,
                'status' => VehicleTransfer::STATUS_COMPLETED,
                'dispatched_by' => $user->id,
                'dispatched_at' => $returnedAt,
                'received_by' => $user->id,
                'received_at' => $returnedAt,
                'order_id' => $order->id,
                'reason' => "Khách trả xe tại cơ sở khác (Đơn #{$order->id} - HĐ {$order->contract_number})",
                'notes' => $conditionNotes,
            ]);

            VehicleTransferItem::create([
                'transfer_id' => $transfer->id,
                'vehicle_id' => $vehicle->id,
                'source_status' => Vehicle::STATUS_USING,
                'target_status' => Vehicle::STATUS_READY,
                'odometer_in' => $odometer ?: $vehicle->odometer,
                'condition_notes' => $conditionNotes,
            ]);

            // Record location event in ledger
            VehicleLocationEvent::create([
                'vehicle_id' => $vehicle->id,
                'from_store_id' => $oldLocationStoreId,
                'to_store_id' => $returnStoreId,
                'event_type' => VehicleLocationEvent::EVENT_ORDER_RETURN_DIFFERENT,
                'ref_type' => 'orders',
                'ref_id' => $order->id,
                'odometer' => $odometer ?: $vehicle->odometer,
                'notes' => "Trả xe tại cơ sở khác: '{$returnStore->store_name}' (Kho doanh thu gốc: '{$orderStoreId}'). {$conditionNotes}",
                'created_by' => $user->id,
            ]);

            return [
                'order_id' => $order->id,
                'vehicle_id' => $vehicle->id,
                'vehicle_license' => $vehicle->license,
                'original_store_id' => $orderStoreId,
                'new_current_store_id' => $returnStoreId,
                'new_current_store_name' => $returnStore->store_name,
                'transfer_code' => $code,
            ];
        });
    }

    /**
     * Flow C: Exchange vehicle during an active rental (e.g. broken, breakdown, customer request).
     */
    public function processVehicleExchange(int $orderId, int $oldVehicleId, int $newVehicleId, array $details, User $user): array
    {
        return DB::transaction(function () use ($orderId, $oldVehicleId, $newVehicleId, $details, $user) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            if ($oldVehicleId === $newVehicleId) {
                throw ValidationException::withMessages([
                    'new_vehicle_id' => ['Xe thay thế không được trùng với xe hiện tại.']
                ]);
            }

            $allowedStores = array_unique([
                (int)$order->store_id,
                (int)($oldVehicleId ? ($lockedVehicles = Vehicle::whereIn('id', [$oldVehicleId, $newVehicleId])->get()->keyBy('id') ? 0 : 0) : 0),
            ]);
            // Lock vehicles in consistent ID order to prevent deadlocks
            $lockIds = [$oldVehicleId, $newVehicleId];
            sort($lockIds);
            $lockedVehicles = Vehicle::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

            $oldVehicle = $lockedVehicles->get($oldVehicleId);
            $newVehicle = $lockedVehicles->get($newVehicleId);

            if (!$oldVehicle || !$newVehicle) {
                throw ValidationException::withMessages([
                    'vehicle' => ['Không tìm thấy thông tin xe cũ hoặc xe mới.']
                ]);
            }

            $allowedStores = array_unique(array_filter([
                (int)$order->store_id,
                (int)($oldVehicle->current_store_id ?: $order->store_id),
                (int)($newVehicle->current_store_id ?: $newVehicle->store_id),
            ]));
            $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
            if (!$isAdmin && !in_array((int)$user->store_id, $allowedStores)) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Nhân viên không có quyền thực hiện đổi xe tại cơ sở này.');
            }

            if ($order->order_status !== 'renting' || !OrderVehicleDetail::where('order_id', $orderId)->where('vehicle_id', $oldVehicleId)->exists()) {
                throw ValidationException::withMessages(['order_id' => 'Xe cũ phải thuộc hợp đồng đang thuê.']);
            }

            if ($newVehicle->status !== Vehicle::STATUS_READY) {
                throw ValidationException::withMessages([
                    'new_vehicle_id' => ["Xe thay thế {$newVehicle->license} đang ở trạng thái '{$newVehicle->status}', không sẵn sàng để bàn giao."]
                ]);
            }

            $exchangeStoreId = (int)(data_get($details, 'exchange_store_id') ?: ($newVehicle->current_store_id ?: $newVehicle->store_id));
            $reason = (string)data_get($details, 'reason', 'Đổi xe sự cố');
            $conditionNotes = (string)data_get($details, 'condition_notes', '');
            $priceDiff = (float)data_get($details, 'price_difference', 0);
            $effectiveAt = data_get($details, 'effective_at') ? Carbon::parse($details['effective_at']) : Carbon::now();
            $oldOdometer = data_get($details, 'old_vehicle_odometer');
            $newOdometer = data_get($details, 'new_vehicle_odometer');

            // Capture origin store IDs before updating vehicle locations
            $oldOriginStoreId = (int) ($oldVehicle->current_store_id ?: ($order->store_id ?: $oldVehicle->store_id));
            $newOriginStoreId = (int) ($newVehicle->current_store_id ?: $newVehicle->store_id);

            $exchangeStore = Store::find($exchangeStoreId);
            if (!$exchangeStore || in_array(strtolower((string)$exchangeStore->status), ['closing', 'closed', 'inactive'], true)) {
                throw ValidationException::withMessages(['exchange_store_id' => 'Cơ sở bàn giao không tồn tại hoặc đã ngừng hoạt động.']);
            }
            if (!HimotoStores::isCanonical($exchangeStore)) {
                throw ValidationException::withMessages(['exchange_store_id' => 'Cơ sở bàn giao không thuộc danh mục 6 kho HIMOTO.']);
            }
            if (!in_array($exchangeStoreId, $allowedStores, true)) {
                throw ValidationException::withMessages(['exchange_store_id' => 'Cơ sở bàn giao không thuộc hợp đồng hoặc vị trí hiện tại của hai xe.']);
            }
            PilotAccess::store($user, $exchangeStoreId);

            $rawMethod = data_get($details, 'payment_method');
            $methodInt = null;
            $cashId = null;
            $bankId = null;
            $bankOwnerType = null;
            if (abs($priceDiff) > 0.00001) {
                if ($rawMethod === null || $rawMethod === '') {
                    throw ValidationException::withMessages(['payment_method' => 'Phải chọn phương thức thu hoặc hoàn chênh lệch giá đổi xe.']);
                }

                if ($rawMethod === 'TM' || $rawMethod === 'cash' || $rawMethod === 1 || $rawMethod === '1') {
                    $methodInt = 1;
                } elseif ($rawMethod === 'CK' || $rawMethod === 'bank' || $rawMethod === 2 || $rawMethod === '2') {
                    $methodInt = 2;
                } else {
                    throw ValidationException::withMessages(['payment_method' => 'Phương thức thanh toán chênh lệch không hợp lệ.']);
                }

                if ($methodInt === 1) {
                    $cashQuery = Cash::where('store_id', $exchangeStoreId)->where('status', 'Active');
                    $requestedCashId = data_get($details, 'cash_id');
                    $cash = $requestedCashId
                        ? (clone $cashQuery)->where('id', $requestedCashId)->first()
                        : $cashQuery->orderBy('id')->first();
                    if (!$cash) {
                        throw ValidationException::withMessages(['cash_id' => 'Quỹ tiền mặt phải đang hoạt động và thuộc cơ sở bàn giao.']);
                    }
                    $cashId = $cash->id;
                } else {
                    $requestedBankId = data_get($details, 'bank_id');
                    if (!$requestedBankId) {
                        throw ValidationException::withMessages(['bank_id' => 'Phải chọn tài khoản ngân hàng cho khoản chênh lệch.']);
                    }
                    $bank = Bank::where('id', $requestedBankId)
                        ->where('store_id', $exchangeStoreId)
                        ->where('status', 'Active')
                        ->first();
                    if (!$bank) {
                        throw ValidationException::withMessages(['bank_id' => 'Tài khoản ngân hàng phải đang hoạt động và thuộc cơ sở bàn giao.']);
                    }
                    $bankId = $bank->id;
                    $bankOwnerType = $bank->owner_type ?: Bank::OWNER_UNKNOWN;
                }
            }

            // 1. Update old vehicle: mark repairing at exchange store
            $oldUpdates = [
                'status' => Vehicle::STATUS_REPAIRING,
                'current_store_id' => $exchangeStoreId,
            ];
            if ($oldOdometer) {
                $oldUpdates['odometer'] = (int)$oldOdometer;
            }
            $oldVehicle->update($oldUpdates);

            // 2. Update new vehicle: mark using at exchange store
            $newUpdates = [
                'status' => Vehicle::STATUS_USING,
                'current_store_id' => $exchangeStoreId,
            ];
            if ($newOdometer) {
                $newUpdates['odometer'] = (int)$newOdometer;
            }
            $newVehicle->update($newUpdates);

            // 3. Update order vehicle detail record
            $orderDetail = OrderVehicleDetail::where('order_id', $order->id)
                ->where('vehicle_id', $oldVehicleId)
                ->first();

            if ($orderDetail) {
                $orderDetail->update([
                    'vehicle_id' => $newVehicleId,
                ]);
            }

            // 4. Create Contract Amendment
            $amendmentCode = 'AMD-' . Carbon::now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $amendment = ContractAmendment::create([
                'order_id' => $order->id,
                'amendment_code' => $amendmentCode,
                'amendment_type' => ContractAmendment::TYPE_VEHICLE_EXCHANGE,
                'old_vehicle_id' => $oldVehicleId,
                'new_vehicle_id' => $newVehicleId,
                'effective_at' => $effectiveAt,
                'price_difference' => $priceDiff,
                'reason' => $reason,
                'notes' => $conditionNotes,
                'created_by' => $user->id,
            ]);

            // 5. Post financial transaction if price difference and payment method provided
            if ($priceDiff != 0) {
                $isThu = $priceDiff > 0;
                Transaction::create([
                    'order_id' => $order->id,
                    'type' => $isThu ? Transaction::THU : Transaction::CHI,
                    'name' => ($isThu ? "Thu" : "Hoàn") . " chênh lệch giá đổi xe (Phụ lục #{$amendmentCode})",
                    'value' => abs($priceDiff),
                    'payment_method' => $methodInt,
                    'cash_id' => $cashId,
                    'bank_id' => $bankId,
                    'bank_owner_type' => $bankOwnerType,
                    'note' => ($isThu ? "Thu" : "Hoàn") . " chênh lệch giá đổi xe (Phụ lục #{$amendmentCode})",
                    'user_id' => $user->id,
                    'store_id' => $exchangeStoreId,
                    'status' => 1,
                ]);
            }

            // 6. Create immutable location events for both vehicles
            VehicleLocationEvent::create([
                'vehicle_id' => $oldVehicle->id,
                'from_store_id' => $oldOriginStoreId,
                'to_store_id' => $exchangeStoreId,
                'event_type' => VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_OUT,
                'ref_type' => 'contract_amendments',
                'ref_id' => $amendment->id,
                'odometer' => $oldOdometer ?: $oldVehicle->odometer,
                'notes' => "Thu hồi xe do sự cố: {$reason}. Xe chuyển sang trạng thái sửa chữa. Ghi chú: {$conditionNotes}",
                'created_by' => $user->id,
            ]);

            VehicleLocationEvent::create([
                'vehicle_id' => $newVehicle->id,
                'from_store_id' => $newOriginStoreId,
                'to_store_id' => $exchangeStoreId,
                'event_type' => VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_IN,
                'ref_type' => 'contract_amendments',
                'ref_id' => $amendment->id,
                'odometer' => $newOdometer ?: $newVehicle->odometer,
                'notes' => "Bàn giao thay thế cho xe {$oldVehicle->license} theo Phụ lục #{$amendmentCode} (HĐ {$order->contract_number})",
                'created_by' => $user->id,
            ]);

            return [
                'order_id' => $order->id,
                'amendment_id' => $amendment->id,
                'amendment_code' => $amendmentCode,
                'old_vehicle' => [
                    'id' => $oldVehicle->id,
                    'license' => $oldVehicle->license,
                    'new_status' => $oldVehicle->status,
                ],
                'new_vehicle' => [
                    'id' => $newVehicle->id,
                    'license' => $newVehicle->license,
                    'new_status' => $newVehicle->status,
                ],
                'price_difference' => $priceDiff,
            ];
        });
    }

    /**
     * Build the immutable vehicle exchange timeline shown on an order.
     */
    public function getOrderVehicleExchangeHistory(Order $order): array
    {
        if (!Schema::hasTable('contract_amendments') || !Schema::hasTable('vehicle_location_events')) {
            return [];
        }

        $order->loadMissing(['customer', 'orderItems']);
        $amendments = ContractAmendment::where('order_id', $order->id)
            ->where('amendment_type', ContractAmendment::TYPE_VEHICLE_EXCHANGE)
            ->with([
                'oldVehicle.store:id,store_name',
                'newVehicle.store:id,store_name',
                'oldVehicleEvent.fromStore:id,store_name',
                'oldVehicleEvent.toStore:id,store_name',
                'newVehicleEvent.fromStore:id,store_name',
                'newVehicleEvent.toStore:id,store_name',
                'createdByUser:id,name',
            ])
            ->orderBy('effective_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $snapshotVehicles = collect((array) data_get($order->contract_snapshot, 'vehicles', []));
        $defaultInitialAt = optional($order->orderItems->first())->rent_at ?: $order->created_at;
        $serializeVehicle = function ($vehicle) {
            return $vehicle ? [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license' => $vehicle->license,
            ] : null;
        };
        $serializeStore = function ($store) {
            return $store ? [
                'id' => $store->id,
                'store_name' => $store->store_name,
            ] : null;
        };

        return $amendments->map(function ($amendment) use ($order, $snapshotVehicles, $defaultInitialAt, $serializeVehicle, $serializeStore) {
            $oldVehicle = $amendment->oldVehicle;
            $newVehicle = $amendment->newVehicle;
            $oldEvent = $amendment->oldVehicleEvent;
            $newEvent = $amendment->newVehicleEvent;

            $snapshotVehicle = $snapshotVehicles->first(function ($vehicle) use ($oldVehicle) {
                if (!$oldVehicle) {
                    return false;
                }

                return (int) data_get($vehicle, 'vehicle_id') === (int) $oldVehicle->id
                    || (string) data_get($vehicle, 'license') === (string) $oldVehicle->license;
            });
            $initialAt = data_get($snapshotVehicle, 'rent_at', $defaultInitialAt);
            $oldStore = $oldEvent && $oldEvent->fromStore ? $oldEvent->fromStore : optional($oldVehicle)->store;
            $newStore = $newEvent && $newEvent->fromStore ? $newEvent->fromStore : optional($newVehicle)->store;
            $exchangeStore = $newEvent && $newEvent->toStore
                ? $newEvent->toStore
                : ($oldEvent ? $oldEvent->toStore : null);

            return [
                'id' => $amendment->id,
                'amendment_code' => $amendment->amendment_code,
                'customer_name' => optional($order->customer)->name,
                'initial_at' => $initialAt ? (string) $initialAt : null,
                'effective_at' => $amendment->effective_at ? $amendment->effective_at->format('Y-m-d H:i:s') : null,
                'reason' => $amendment->reason,
                'notes' => $amendment->notes,
                'price_difference' => (float) $amendment->price_difference,
                'old_vehicle' => $serializeVehicle($oldVehicle),
                'new_vehicle' => $serializeVehicle($newVehicle),
                'old_store' => $serializeStore($oldStore),
                'new_store' => $serializeStore($newStore),
                'exchange_store' => $serializeStore($exchangeStore),
                'created_by' => $amendment->createdByUser ? [
                    'id' => $amendment->createdByUser->id,
                    'name' => $amendment->createdByUser->name,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Get complete movement history ledger for a vehicle.
     */
    public function getVehicleMovementHistory(int $vehicleId): array
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $events = VehicleLocationEvent::where('vehicle_id', $vehicleId)
            ->with(['fromStore:id,store_name', 'toStore:id,store_name', 'createdByUser:id,name'])
            ->orderBy('id', 'desc')
            ->get();

        $amendmentIds = $events
            ->where('ref_type', 'contract_amendments')
            ->pluck('ref_id')
            ->filter()
            ->unique()
            ->values();
        $amendments = $amendmentIds->isEmpty()
            ? collect()
            : ContractAmendment::whereIn('id', $amendmentIds)
                ->with(['order.customer:id,name,phone', 'order.store:id,store_name'])
                ->get()
                ->keyBy('id');

        return [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license' => $vehicle->license,
                'status' => $vehicle->status,
                'odometer' => $vehicle->odometer,
                'current_store_name' => $vehicle->currentStore ? $vehicle->currentStore->store_name : ($vehicle->store ? $vehicle->store->store_name : 'Chưa gán'),
            ],
            'events' => $events->map(function ($ev) use ($amendments) {
                $amendment = $ev->ref_type === 'contract_amendments'
                    ? $amendments->get($ev->ref_id)
                    : null;
                $order = $amendment ? $amendment->order : null;

                return [
                    'id' => $ev->id,
                    'event_type' => $ev->event_type,
                    'ref_type' => $ev->ref_type,
                    'ref_id' => $ev->ref_id,
                    'from_store_name' => $ev->fromStore ? $ev->fromStore->store_name : 'N/A',
                    'to_store_name' => $ev->toStore ? $ev->toStore->store_name : 'N/A',
                    'odometer' => $ev->odometer,
                    'notes' => $ev->notes,
                    'created_by_name' => $ev->createdByUser ? $ev->createdByUser->name : 'Hệ thống',
                    'created_at' => $ev->created_at ? $ev->created_at->format('d/m/Y H:i') : null,
                    'contract' => $order ? [
                        'id' => $order->id,
                        'contract_number' => $order->contract_number,
                        'customer_name' => $order->customer ? $order->customer->name : null,
                        'customer_phone' => $order->customer ? $order->customer->phone : null,
                        'store_id' => $order->store_id,
                        'store_name' => $order->store ? $order->store->store_name : null,
                        'amendment_code' => $amendment->amendment_code,
                    ] : null,
                ];
            }),
        ];
    }
}
