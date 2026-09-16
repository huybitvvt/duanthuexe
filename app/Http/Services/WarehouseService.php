<?php

namespace App\Http\Services;

use App\Models\Store;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            ->get();

        // Check if there is a lease_to_own store, if not, find or create one or ensure one is represented
        $hasLeaseToOwn = $stores->contains(function ($s) {
            return $s->kind === Store::KIND_LEASE_TO_OWN;
        });

        if (!$hasLeaseToOwn) {
            // Find if there is a store named 'Thuê sở hữu' or create one
            $lto = Store::firstOrCreate(
                ['kind' => Store::KIND_LEASE_TO_OWN],
                [
                    'store_name' => 'Kho Thuê sở hữu',
                    'store_address' => 'Hệ thống Himoto - Toàn quốc',
                    'status' => 'active',
                    'code' => 'KHO-TSH'
                ]
            );
            if (!$stores->contains('id', $lto->id)) {
                $stores->push($lto);
            }
        }

        $cards = [];
        $isAdmin = $user && ($user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien'));

        foreach ($stores as $store) {
            // Managed vehicles (store_id = store.id)
            $managedQuery = Vehicle::where('store_id', $store->id);
            $totalManaged = (clone $managedQuery)->where('status', '!=', Vehicle::STATUS_SOLD)->count();

            // Physically present vehicles:
            // Either current_store_id == store.id OR (current_store_id is null AND store_id == store.id)
            // AND vehicle is not in_transit or sold
            $presentQuery = Vehicle::where(function (Builder $q) use ($store) {
                $q->where('current_store_id', $store->id)
                  ->orWhere(function (Builder $sub) use ($store) {
                      $sub->whereNull('current_store_id')
                          ->where('store_id', $store->id);
                  });
            })->where('status', '!=', Vehicle::STATUS_IN_TRANSIT)
              ->where('status', '!=', Vehicle::STATUS_SOLD);

            $totalPresent = (clone $presentQuery)->count();
            $readyCount = (clone $presentQuery)->where('status', Vehicle::STATUS_READY)->count();
            $usingCount = (clone $managedQuery)->where('status', Vehicle::STATUS_USING)->count();
            $repairingCount = (clone $presentQuery)->whereIn('status', [Vehicle::STATUS_REPAIRING, Vehicle::STATUS_BROKEN])->count();
            
            // In transit vehicles related to this store (either from or to this store)
            $inTransitCount = Vehicle::where('status', Vehicle::STATUS_IN_TRANSIT)
                ->where(function (Builder $q) use ($store) {
                    $q->where('store_id', $store->id)
                      ->orWhere('current_store_id', $store->id);
                })->count();

            // Vehicle types present
            $gaCount = (clone $presentQuery)->where('type', Vehicle::TYPE_XEGA)->count();
            $soCount = (clone $presentQuery)->where('type', Vehicle::TYPE_XESO)->count();
            $conCount = (clone $presentQuery)->where('type', Vehicle::TYPE_XECON)->count();
            $shCount = (clone $presentQuery)->where('type', Vehicle::TYPE_XE_SH)->count();

            $canViewDetails = $isAdmin || ($user && (int)$user->store_id === (int)$store->id);

            $cards[] = [
                'id' => $store->id,
                'store_name' => $store->store_name,
                'store_address' => $store->store_address,
                'store_phone' => $store->store_phone,
                'kind' => $store->kind ?: Store::KIND_PHYSICAL,
                'code' => $store->code,
                'total_managed' => $totalManaged,
                'total_present' => $totalPresent,
                'ready' => $readyCount,
                'using' => $usingCount,
                'repairing' => $repairingCount,
                'in_transit' => $inTransitCount,
                'types' => [
                    'xega' => $gaCount,
                    'xeso' => $soCount,
                    'xecon' => $conCount,
                    'xesh' => $shCount,
                ],
                'can_view_details' => $canViewDetails,
            ];
        }

        return $cards;
    }

    /**
     * Get vehicles of a specific store with server-side role enforcement.
     */
    public function getStoreVehicles(int $storeId, array $params, User $user)
    {
        $isAdmin = $user->role_id === 1 || ($user->role_rel && $user->role_rel->slug === 'quan-tri-vien');
        
        // Strict role permission check
        if (!$isAdmin && (int)$user->store_id !== (int)$storeId) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Bạn không có quyền truy cập danh sách xe của cơ sở khác. Chỉ có Quản trị viên mới được xem toàn bộ chi nhánh.'
            );
        }

        $store = Store::findOrFail($storeId);

        // Query vehicles belonging to or present at this store
        $query = Vehicle::with([
            'store:id,store_name,store_address',
            'currentStore:id,store_name,store_address',
            'orders' => function ($q) {
                $q->whereIn('order_status', ['pending', 'delivering', 'active', 'using', 'late'])
                  ->orderBy('orders.id', 'desc')
                  ->with('customer:id,name,phone');
            }
        ]);

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
}
