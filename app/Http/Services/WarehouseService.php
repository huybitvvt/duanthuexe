<?php

namespace App\Http\Services;

use App\Models\Store;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\VehicleTransfer;
use App\Models\OrderVehicleDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\AuthorizationException;

class WarehouseService
{
    /**
     * Get aggregate cards for all stores including physical stores and Lease-to-own store.
     */
    public function getSummary(?User $user = null): array
    {
        $stores = Store::where('status', '!=', 'inactive')
            ->orWhereNull('status')
            ->orderBy('id', 'asc')
            ->get(['id', 'store_name', 'store_address', 'store_phone', 'kind', 'code']);

        $isAdmin = $user && ($user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien'));

        // Aggregate once for every store. The previous implementation ran 11
        // count queries per store, making the summary progressively slower as
        // branches were added.
        $managedByStore = Vehicle::query()
            ->select('store_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN status != ? THEN 1 ELSE 0 END), 0) as total_managed', [Vehicle::STATUS_SOLD])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_using', [Vehicle::STATUS_USING])
            ->whereNotNull('store_id')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        $effectiveStore = DB::raw('COALESCE(current_store_id, store_id)');
        $presentByStore = Vehicle::query()
            ->selectRaw('COALESCE(current_store_id, store_id) as effective_store_id')
            ->selectRaw('COUNT(*) as total_present')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_ready', [Vehicle::STATUS_READY])
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as total_repairing', [Vehicle::STATUS_REPAIRING, Vehicle::STATUS_BROKEN])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as type_xega', [Vehicle::TYPE_XEGA])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as type_xeso', [Vehicle::TYPE_XESO])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as type_xecon', [Vehicle::TYPE_XECON])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as type_xesh', [Vehicle::TYPE_XE_SH])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as type_xedien', ['xe_dien'])
            ->whereNotIn('status', [Vehicle::STATUS_IN_TRANSIT, Vehicle::STATUS_SOLD])
            ->where(function (Builder $query) {
                $query->whereNotNull('current_store_id')->orWhereNotNull('store_id');
            })
            ->groupBy($effectiveStore)
            ->get()
            ->keyBy('effective_store_id');

        $buildTransitQuery = function (string $storeColumn) {
            return DB::table('vehicle_transfer_items as transit_items')
                ->join('vehicle_transfers as transit_transfers', 'transit_transfers.id', '=', 'transit_items.transfer_id')
                ->join('vehicles as transit_vehicles', 'transit_vehicles.id', '=', 'transit_items.vehicle_id')
                ->where('transit_transfers.status', VehicleTransfer::STATUS_DISPATCHED)
                ->where('transit_vehicles.status', Vehicle::STATUS_IN_TRANSIT)
                ->whereNotNull($storeColumn)
                ->selectRaw("{$storeColumn} as store_id, COUNT(DISTINCT transit_items.vehicle_id) as total")
                ->groupBy($storeColumn);
        };
        $transitRows = $buildTransitQuery('transit_transfers.from_store_id')
            ->unionAll($buildTransitQuery('transit_transfers.to_store_id'))
            ->get();
        $transitByStore = [];
        foreach ($transitRows as $row) {
            $storeId = (int) $row->store_id;
            $transitByStore[$storeId] = ($transitByStore[$storeId] ?? 0) + (int) $row->total;
        }

        return $stores->map(function (Store $store) use ($isAdmin, $user, $managedByStore, $presentByStore, $transitByStore) {
            $managed = $managedByStore->get($store->id);
            $present = $presentByStore->get($store->id);

            return [
                'id' => $store->id,
                'store_name' => $store->store_name,
                'store_address' => $store->store_address,
                'store_phone' => $store->store_phone,
                'kind' => $store->kind ?: Store::KIND_PHYSICAL,
                'code' => $store->code,
                'total_managed' => (int) ($managed->total_managed ?? 0),
                'total_present' => (int) ($present->total_present ?? 0),
                'ready' => (int) ($present->total_ready ?? 0),
                'using' => (int) ($managed->total_using ?? 0),
                'repairing' => (int) ($present->total_repairing ?? 0),
                'in_transit' => (int) ($transitByStore[$store->id] ?? 0),
                'types' => [
                    'xega' => (int) ($present->type_xega ?? 0),
                    'xeso' => (int) ($present->type_xeso ?? 0),
                    'xecon' => (int) ($present->type_xecon ?? 0),
                    'xesh' => (int) ($present->type_xesh ?? 0),
                    'xe_dien' => (int) ($present->type_xedien ?? 0),
                ],
                'can_view_details' => $isAdmin || ($user && (int) $user->store_id === (int) $store->id),
            ];
        })->values()->all();
    }

    /**
     * Get vehicles of a specific store with server-side role enforcement.
     */
    public function getStoreVehicles(int $storeId, array $params, User $user)
    {
        $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
        
        // Strict role permission check
        if (!$isAdmin && (int)$user->store_id !== $storeId) {
            throw new AuthorizationException(
                'Bạn chỉ được xem chi tiết xe của cơ sở được phân công.'
            );
        }

        $store = Store::findOrFail($storeId);

        // Query vehicles belonging to or present at this store
        $with = [
            'store:id,store_name,store_address',
            'currentStore:id,store_name,store_address',
            'orders' => function ($q) {
                $q->select(['orders.id', 'orders.contract_number', 'orders.customer_id', 'orders.order_status'])
                  ->whereIn('order_status', ['renting'])
                  ->orderBy('orders.id', 'desc')
                  ->with('customer:id,name,phone');
            }
        ];
        if (Schema::hasTable('gps_devices') && Schema::hasTable('gps_positions') && Schema::hasTable('gps_alerts')) {
            $with[] = 'gpsDevice.latestPosition';
            $with['gpsDevice.alerts'] = function ($q) {
                $q->whereIn('status', ['opened', 'acknowledged'])->orderBy('opened_at', 'desc');
            };
        }
        $query = Vehicle::query()
            ->select([
                'id', 'name', 'license', 'type', 'brand', 'year', 'color',
                'status', 'odometer', 'store_id', 'current_store_id',
            ])
            ->with($with);

        $locationMode = data_get($params, 'location_mode', 'all'); // 'present', 'managed', 'all'

        if ($locationMode === 'present') {
            // Physically at this store
            $query->where(function (Builder $q) use ($storeId) {
                $q->where('current_store_id', $storeId)
                  ->orWhere(function (Builder $sub) use ($storeId) {
                      $sub->whereNull('current_store_id')
                          ->where('store_id', $storeId);
                  });
            })->where('status', '!=', Vehicle::STATUS_IN_TRANSIT);
        } elseif ($locationMode === 'managed') {
            // Managed by this store
            $query->where('store_id', $storeId);
        } else {
            // Either managed by or physically at this store
            $query->where(function (Builder $q) use ($storeId) {
                $q->where('store_id', $storeId)
                  ->orWhere('current_store_id', $storeId);
            });
        }

        // Search & Filter
        $keyword = data_get($params, 'keyword', data_get($params, 'name', ''));
        if ($keyword) {
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('license', 'LIKE', "%{$keyword}%")
                  ->orWhere('chassis', 'LIKE', "%{$keyword}%");
            });
        }

        $status = data_get($params, 'status', '');
        if ($status) {
            $query->where('status', $status);
        }

        $type = data_get($params, 'type', '');
        if ($type) {
            $query->where('type', $type);
        }

        $limit = (int)data_get($params, 'limit', 15);
        $vehicles = $query->orderBy('id', 'desc')->paginate($limit);

        // Transform items to include effective location name & active order info
        $vehicles->getCollection()->transform(function ($vehicle) {
            $effectiveStoreId = $vehicle->current_store_id ?: $vehicle->store_id;
            $effectiveStoreName = $vehicle->currentStore ? $vehicle->currentStore->store_name : ($vehicle->store ? $vehicle->store->store_name : 'Chưa gán');
            
            $activeOrder = $vehicle->orders->first();
            $gpsDevice = $vehicle->relationLoaded('gpsDevice') ? $vehicle->gpsDevice : null;
            $gpsPosition = $gpsDevice && $gpsDevice->relationLoaded('latestPosition') ? $gpsDevice->latestPosition : null;
            $gpsAlerts = $gpsDevice && $gpsDevice->relationLoaded('alerts') ? $gpsDevice->alerts : collect();

            return [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license' => $vehicle->license,
                'type' => $vehicle->type,
                'brand' => $vehicle->brand,
                'year' => $vehicle->year,
                'color' => $vehicle->color,
                'status' => $vehicle->status,
                'odometer' => $vehicle->odometer,
                'managed_store_id' => $vehicle->store_id,
                'managed_store_name' => $vehicle->store ? $vehicle->store->store_name : null,
                'current_store_id' => $vehicle->current_store_id,
                'current_store_name' => $effectiveStoreName,
                'is_in_transit' => $vehicle->status === Vehicle::STATUS_IN_TRANSIT,
                'active_order' => $activeOrder ? [
                    'id' => $activeOrder->id,
                    'contract_number' => $activeOrder->contract_number,
                    'customer_name' => $activeOrder->customer ? $activeOrder->customer->name : null,
                    'customer_phone' => $activeOrder->customer ? $activeOrder->customer->phone : null,
                    'order_status' => $activeOrder->order_status,
                ] : null,
                'gps' => $gpsDevice ? [
                    'mapping_status' => $gpsDevice->mapping_status,
                    'last_sync_at' => $gpsDevice->last_sync_at ? $gpsDevice->last_sync_at->toDateTimeString() : null,
                    'latitude' => $gpsPosition ? (float)$gpsPosition->latitude : null,
                    'longitude' => $gpsPosition ? (float)$gpsPosition->longitude : null,
                    'position_status' => $gpsPosition ? $gpsPosition->normalized_status : 'unknown',
                    'recorded_at' => $gpsPosition && $gpsPosition->provider_recorded_at ? $gpsPosition->provider_recorded_at->toDateTimeString() : null,
                    'open_alerts' => $gpsAlerts->count(),
                    'has_lost_signal' => $gpsAlerts->contains(function ($alert) {
                        return in_array($alert->alert_type, ['offline', 'stale', 'tamper']);
                    }),
                ] : null,
            ];
        });

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->store_name,
                'address' => $store->store_address,
                'kind' => $store->kind,
            ],
            'vehicles' => $vehicles,
        ];
    }

    public function getTransfers(array $params, User $user)
    {
        $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
        $storeId = data_get($params, 'store_id');
        if (!$isAdmin) {
            if (!$user->store_id) {
                throw new AuthorizationException('Tài khoản chưa được gán cơ sở.');
            }
            if ($storeId && (int)$storeId !== (int)$user->store_id) {
                throw new AuthorizationException('Bạn chỉ được xem biến động kho của cơ sở được phân công.');
            }
            $storeId = (int)$user->store_id;
        }

        $query = VehicleTransfer::with([
            'fromStore:id,store_name',
            'toStore:id,store_name',
            'items.vehicle:id,name,license',
            'dispatchedByUser:id,name',
            'receivedByUser:id,name',
        ]);
        if ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)->orWhere('to_store_id', $storeId);
            });
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        $date = data_get($params, 'date', date('Y-m-d'));
        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('dispatched_at', $date)->orWhereDate('received_at', $date);
            });
        }
        return $query->orderBy('id', 'desc')->paginate((int)data_get($params, 'limit', 25));
    }

    public function lookupReturnByLicense(string $license, User $user): array
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $license));
        if ($normalized === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['license' => 'Biển số không hợp lệ.']);
        }

        $vehicle = Vehicle::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(license), ' ', ''), '-', ''), '.', ''), '/', '') = ?",
            [$normalized]
        )->first();
        if (!$vehicle) {
            throw \Illuminate\Validation\ValidationException::withMessages(['license' => 'Không tìm thấy xe theo biển số đã nhập.']);
        }

        $detail = OrderVehicleDetail::where('vehicle_id', $vehicle->id)
            ->whereHas('order', function ($q) {
                $q->whereIn('order_status', ['completed', 'wait_payment']);
            })
            ->with(['order.customer', 'order.store'])
            ->orderBy('order_id', 'desc')
            ->first();
        if (!$detail || !$detail->order) {
            throw \Illuminate\Validation\ValidationException::withMessages(['license' => 'Xe chưa có đơn đã hoàn tất/chờ đối soát để nhập kho khác cơ sở.']);
        }

        $order = $detail->order;
        return [
            'order_id' => $order->id,
            'contract_number' => $order->contract_number,
            'order_status' => $order->order_status,
            'customer_name' => $order->customer ? $order->customer->name : null,
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license' => $vehicle->license,
            ],
            'source_store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->store_name,
            ] : null,
        ];
    }
}
