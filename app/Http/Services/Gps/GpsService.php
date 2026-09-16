<?php

namespace App\Http\Services\Gps;

use App\Interfaces\GpsProviderInterface;
use App\Models\Vehicle;

class GpsService
{
    private $provider;

    public function __construct(GpsProviderInterface $provider = null)
    {
        $this->provider = $provider ?: new MockGpsProvider();
    }

    /**
     * Get GPS fleet status summary.
     * Marks clearly whether external provider integration is ready or BLOCKED.
     *
     * @return array
     */
    public function getFleetOverview(): array
    {
        $vehicles = Vehicle::select(['id', 'name', 'license', 'status', 'store_id'])
            ->with(['store:id,store_name'])
            ->get();

        return [
            'provider' => 'pending',
            'integration_status' => 'BLOCKED_PENDING_EXTERNAL_PROVIDER_CREDENTIALS',
            'note' => 'Chưa cấu hình nhà cung cấp GPS thực tế (Bình Anh, Vietmap, Định vị Bách Khoa...). Hệ thống đang chạy ở chế độ adapter chờ thông tin tài khoản API từ chủ cơ sở.',
            'total_vehicles' => $vehicles->count(),
            'vehicles' => $vehicles,
        ];
    }
}
