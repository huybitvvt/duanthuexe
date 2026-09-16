<?php

namespace App\Http\Services;

use App\Models\ContractAmendment;
use App\Support\PilotAccess;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocationEvent;
use App\Models\VehicleTransfer;
use App\Models\VehicleTransferItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

        Store::findOrFail($fromStoreId);
        Store::findOrFail($toStoreId);
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
            PilotAccess::store($user, $returnStoreId);
            if (!in_array($order->order_status, ['completed', 'wait_payment'])) {
                throw ValidationException::withMessages(['order_id' => 'Hoàn tất trả xe qua luồng trả xe trước khi xác nhận nhập kho khác cơ sở.']);
            }
            $existing = VehicleTransfer::where('order_id', $orderId)->where('type', VehicleTransfer::TYPE_RETURN_DIFFERENT_STORE)->first();
            if ($existing) { throw ValidationException::withMessages(['order_id' => 'Đơn đã được ghi nhận trả khác cơ sở.']); }
            if (OrderVehicleDetail::where('order_id', $orderId)->count() !== 1) {
                throw ValidationException::withMessages(['order_id' => 'Đơn nhiều xe cần nhập kho theo từng xe; không thể tự chọn xe đầu tiên.']);
            }

            $orderStoreId = (int)$order->store_id;
            $odometer = data_get($details, 'odometer');
            $conditionNotes = (string)data_get($details, 'condition_notes', 'Khách trả xe tại chi nhánh khác');
            $returnedAt = data_get($details, 'returned_at') ? Carbon::parse($details['returned_at']) : Carbon::now();

            // Find vehicle from order
            $orderVehicleDetail = OrderVehicleDetail::where('order_id', $order->id)->first();
            $vehicleId = $orderVehicleDetail ? $orderVehicleDetail->vehicle_id : null;

            if (!$vehicleId) {
                throw ValidationException::withMessages([
                    'order_id' => ['Không tìm thấy thông tin xe trong đơn thuê.']
                ]);
            }

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

            PilotAccess::store($user, $order->store_id);
            if ($order->order_status !== 'renting' || !OrderVehicleDetail::where('order_id', $orderId)->where('vehicle_id', $oldVehicleId)->exists()) {
                throw ValidationException::withMessages(['order_id' => 'Xe cũ phải thuộc hợp đồng đang thuê.']);
            }
            if ((float)data_get($details, 'price_difference', 0) != 0) {
                throw ValidationException::withMessages(['price_difference' => 'Chênh lệch giá cần được hạch toán qua phụ lục trước khi đổi xe.']);
            }
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

            if ($newVehicle->status !== Vehicle::STATUS_READY) {
                throw ValidationException::withMessages([
                    'new_vehicle_id' => ["Xe thay thế {$newVehicle->license} đang ở trạng thái '{$newVehicle->status}', không sẵn sàng để bàn giao."]
                ]);
            }

            if (($newVehicle->current_store_id ?: $newVehicle->store_id) != ($oldVehicle->current_store_id ?: $order->store_id)) {
                throw ValidationException::withMessages(['new_vehicle_id' => 'Hoàn thành phiếu điều chuyển xe mới về cơ sở nhận trước khi đổi xe.']);
            }
            $reason = (string)data_get($details, 'reason', 'Đổi xe sự cố');
            $conditionNotes = (string)data_get($details, 'condition_notes', '');
            $priceDiff = (float)data_get($details, 'price_difference', 0);
            $effectiveAt = data_get($details, 'effective_at') ? Carbon::parse($details['effective_at']) : Carbon::now();
            $oldOdometer = data_get($details, 'old_vehicle_odometer');
            $newOdometer = data_get($details, 'new_vehicle_odometer');

            // 1. Update old vehicle: mark repairing
            $oldUpdates = [
                'status' => Vehicle::STATUS_REPAIRING,
            ];
            if ($oldOdometer) {
                $oldUpdates['odometer'] = (int)$oldOdometer;
            }
            $oldVehicle->update($oldUpdates);

            // 2. Update new vehicle: mark using, inherit physical store of old vehicle
            $newUpdates = [
                'status' => Vehicle::STATUS_USING,
                'current_store_id' => $oldVehicle->current_store_id ?: $order->store_id,
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

            // 5. Create immutable location events for both vehicles
            VehicleLocationEvent::create([
                'vehicle_id' => $oldVehicle->id,
                'from_store_id' => $oldVehicle->current_store_id ?: $order->store_id,
                'to_store_id' => $oldVehicle->current_store_id ?: $order->store_id,
                'event_type' => VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_OUT,
                'ref_type' => 'contract_amendments',
                'ref_id' => $amendment->id,
                'odometer' => $oldOdometer ?: $oldVehicle->odometer,
                'notes' => "Thu hồi xe do sự cố: {$reason}. Xe chuyển sang trạng thái sửa chữa. Ghi chú: {$conditionNotes}",
                'created_by' => $user->id,
            ]);

            VehicleLocationEvent::create([
                'vehicle_id' => $newVehicle->id,
                'from_store_id' => $newVehicle->current_store_id ?: $order->store_id,
                'to_store_id' => $oldVehicle->current_store_id ?: $order->store_id,
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
     * Get complete movement history ledger for a vehicle.
     */
    public function getVehicleMovementHistory(int $vehicleId): array
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $events = VehicleLocationEvent::where('vehicle_id', $vehicleId)
            ->with(['fromStore:id,store_name', 'toStore:id,store_name', 'createdByUser:id,name'])
            ->orderBy('id', 'desc')
            ->get();

        return [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license' => $vehicle->license,
                'status' => $vehicle->status,
                'odometer' => $vehicle->odometer,
                'current_store_name' => $vehicle->currentStore ? $vehicle->currentStore->store_name : ($vehicle->store ? $vehicle->store->store_name : 'Chưa gán'),
            ],
            'events' => $events->map(function ($ev) {
                return [
                    'id' => $ev->id,
                    'event_type' => $ev->event_type,
                    'from_store_name' => $ev->fromStore ? $ev->fromStore->store_name : 'N/A',
                    'to_store_name' => $ev->toStore ? $ev->toStore->store_name : 'N/A',
                    'odometer' => $ev->odometer,
                    'notes' => $ev->notes,
                    'created_by_name' => $ev->createdByUser ? $ev->createdByUser->name : 'Hệ thống',
                    'created_at' => $ev->created_at ? $ev->created_at->format('d/m/Y H:i') : null,
                ];
            }),
        ];
    }
}
