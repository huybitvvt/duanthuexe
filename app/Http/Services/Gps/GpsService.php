<?php

namespace App\Http\Services\Gps;

use App\Http\Services\AuditService;
use App\Interfaces\GpsProviderInterface;
use App\Models\GpsAlert;
use App\Models\GpsDevice;
use App\Models\GpsPosition;
use App\Models\GpsRecoveryAction;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\PermissionAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GpsService
{
    private $provider;

    public function __construct(GpsProviderInterface $provider = null)
    {
        $this->provider = $provider ?: new SandboxGpsProvider();
    }

    public function getProvider(): GpsProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get GPS fleet status summary with store-level scoping.
     */
    public function getFleetOverview(?User $actor = null, ?int $storeId = null): array
    {
        $vehicleQuery = Vehicle::select(['id', 'name', 'license', 'status', 'store_id'])
            ->with(['store:id,store_name']);

        if ($actor && !PermissionAccess::isSuperAdmin($actor) && !PermissionAccess::allows($actor, 'kpi.view_company')) {
            // Do not assume the optional store_user pivot exists. The base
            // account scope is the authoritative store_id on this legacy app.
            $userStores = $actor->store_id ? [(int) $actor->store_id] : [];
            if (empty($userStores)) {
                $vehicleQuery->whereRaw('1 = 0');
            } else {
                $vehicleQuery->whereIn('store_id', $userStores);
            }
        }

        if ($storeId) {
            $vehicleQuery->where('store_id', $storeId);
        }

        $vehicles = $vehicleQuery->get();

        $devices = collect([]);
        $openAlertsCount = 0;

        if (\Illuminate\Support\Facades\Schema::hasTable('gps_devices')) {
            $devices = GpsDevice::with(['latestPosition', 'alerts' => function ($q) {
                $q->where('status', 'opened');
            }])->whereIn('vehicle_id', $vehicles->pluck('id')->toArray())
              ->get();
        }

        $activeCount = 0;
        $staleCount = 0;
        $movingCount = 0;
        $stoppedCount = 0;

        foreach ($devices as $d) {
            $latest = $d->latestPosition;
            $st = $latest ? $latest->normalized_status : 'never_connected';
            if ($st === 'moving') {
                $movingCount++;
                $activeCount++;
            } elseif ($st === 'stopped') {
                $stoppedCount++;
                $activeCount++;
            } elseif ($st === 'stale_offline' || $st === 'never_connected') {
                $staleCount++;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('gps_alerts')) {
            $openAlertsCount = GpsAlert::whereIn('vehicle_id', $vehicles->pluck('id')->toArray())
                ->where('status', 'opened')
                ->count();
        }

        return [
            'provider' => 'pending',
            'integration_status' => 'BLOCKED_PENDING_EXTERNAL_PROVIDER_CREDENTIALS',
            'note' => 'Chưa cấu hình nhà cung cấp GPS thực tế (Bình Anh, Vietmap, Định vị Bách Khoa...). Hệ thống đang chạy ở chế độ adapter chờ thông tin tài khoản API từ chủ cơ sở.',
            'total_vehicles' => $vehicles->count(),
            'total_devices' => $devices->count(),
            'active_devices' => $activeCount,
            'moving_count' => $movingCount,
            'stopped_count' => $stoppedCount,
            'stale_offline_count' => $staleCount,
            'open_alerts' => $openAlertsCount,
            'vehicles' => $vehicles,
        ];
    }

    /**
     * Sync single device location from provider and update telemetry & alerts.
     */
    public function syncDeviceLocation(GpsDevice $device): ?GpsPosition
    {
        $raw = $this->provider->getVehicleLocation($device->external_device_id);

        $lat = isset($raw['latitude']) ? (float) $raw['latitude'] : null;
        $lng = isset($raw['longitude']) ? (float) $raw['longitude'] : null;

        // Rule 7.2: Do not record (0,0) or missing coordinates as valid positions
        if ($lat === null || $lng === null || (abs($lat) < 0.0001 && abs($lng) < 0.0001)) {
            $normStatus = 'never_connected';
            $this->manageAlertsForDevice($device, $normStatus);
            return null;
        }

        $recordedAt = !empty($raw['last_ping_at'])
            ? Carbon::parse($raw['last_ping_at'], 'Asia/Ho_Chi_Minh')
            : Carbon::now('Asia/Ho_Chi_Minh');

        $normStatus = $this->provider->normalizeStatus($raw['status'] ?? null, $raw['last_ping_at'] ?? null);

        // Check for duplicate position based on (gps_device_id, provider_recorded_at)
        $existing = GpsPosition::where('gps_device_id', $device->id)
            ->where('provider_recorded_at', $recordedAt->toDateTimeString())
            ->first();

        if ($existing) {
            $this->manageAlertsForDevice($device, $normStatus);
            return $existing;
        }

        $pos = GpsPosition::create([
            'gps_device_id' => $device->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => (float) ($raw['speed'] ?? 0),
            'ignition' => (bool) ($raw['ignition'] ?? false),
            'heading' => isset($raw['heading']) ? (float) $raw['heading'] : null,
            'provider_recorded_at' => $recordedAt,
            'received_at' => Carbon::now('Asia/Ho_Chi_Minh'),
            'normalized_status' => $normStatus,
            'raw_payload' => $raw,
        ]);

        $device->last_sync_at = Carbon::now('Asia/Ho_Chi_Minh');
        $device->save();

        $this->manageAlertsForDevice($device, $normStatus);

        return $pos;
    }

    /**
     * Idempotently manage alerts: open stale alerts or auto-resolve when connection restored.
     */
    public function manageAlertsForDevice(GpsDevice $device, string $normalizedStatus): void
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        if ($normalizedStatus === 'stale_offline' || $normalizedStatus === 'never_connected') {
            // Open alert if not already open
            $openAlert = GpsAlert::where('gps_device_id', $device->id)
                ->where('alert_type', 'stale')
                ->where('status', 'opened')
                ->first();

            if (!$openAlert) {
                GpsAlert::create([
                    'gps_device_id' => $device->id,
                    'vehicle_id' => $device->vehicle_id,
                    'alert_type' => 'stale',
                    'severity' => 'warning',
                    'status' => 'opened',
                    'opened_at' => $now,
                    'notes' => 'Thiết bị mất tín hiệu GPS quá 120 phút (Trạng thái: ' . $normalizedStatus . ').',
                ]);
            }
        } elseif ($normalizedStatus === 'moving' || $normalizedStatus === 'stopped' || $normalizedStatus === 'online') {
            // Auto-resolve any open stale alerts
            $openAlerts = GpsAlert::where('gps_device_id', $device->id)
                ->where('alert_type', 'stale')
                ->where('status', 'opened')
                ->get();

            foreach ($openAlerts as $alert) {
                $alert->status = 'resolved';
                $alert->resolved_at = $now;
                $alert->notes = trim($alert->notes . " [Tự động đóng: Thiết bị đã kết nối lại lúc {$now->format('d/m/Y H:i')}].");
                $alert->save();
            }
        }
    }

    /**
     * Get location history for a device within authorized store scope.
     */
    public function getDeviceHistory(int $deviceId, User $actor, ?string $from = null, ?string $to = null, int $limit = 100): Collection
    {
        $device = GpsDevice::with('vehicle')->findOrFail($deviceId);

        if ($device->vehicle && $device->vehicle->store_id) {
            PermissionAccess::can($actor, 'gps.view', $device->vehicle->store_id);
        } else {
            PermissionAccess::can($actor, 'gps.view');
        }

        $query = GpsPosition::where('gps_device_id', $deviceId);

        if ($from) {
            $query->where('provider_recorded_at', '>=', $from);
        }
        if ($to) {
            $query->where('provider_recorded_at', '<=', $to);
        }

        return $query->orderBy('provider_recorded_at', 'desc')->limit($limit)->get();
    }

    /**
     * Create vehicle recovery action.
     */
    public function createRecoveryAction(array $data, User $actor): GpsRecoveryAction
    {
        PermissionAccess::can($actor, 'gps.recovery_action');

        $action = GpsRecoveryAction::create([
            'vehicle_id' => $data['vehicle_id'],
            'gps_device_id' => $data['gps_device_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? $actor->id,
            'recovery_plan' => $data['recovery_plan'] ?? 'Kế hoạch thu hồi xe quá hạn/mất tín hiệu',
            'deadline' => $data['deadline'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        AuditService::log('gps.recovery.create', $action, null, $action->toArray(), 'Khởi tạo kế hoạch thu hồi xe');

        return $action;
    }
}
