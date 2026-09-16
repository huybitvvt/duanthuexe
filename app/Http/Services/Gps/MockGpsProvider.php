<?php

namespace App\Http\Services\Gps;

use App\Interfaces\GpsProviderInterface;
use Carbon\Carbon;

class MockGpsProvider implements GpsProviderInterface
{
    /**
     * @param string $deviceId
     * @return array
     */
    public function getVehicleLocation(string $deviceId): array
    {
        return [
            'device_id' => $deviceId,
            'latitude' => null,
            'longitude' => null,
            'speed' => 0,
            'ignition' => false,
            'last_ping_at' => null,
            'status' => 'never_connected',
            'provider' => 'mock_pending_credentials',
        ];
    }

    /**
     * @param array $deviceIds
     * @return array
     */
    public function getFleetLocations(array $deviceIds): array
    {
        $res = [];
        foreach ($deviceIds as $id) {
            $res[$id] = $this->getVehicleLocation((string)$id);
        }
        return $res;
    }

    /**
     * @param string|null $rawStatus
     * @param string|null $lastPingAt
     * @return string
     */
    public function normalizeStatus(?string $rawStatus, ?string $lastPingAt): string
    {
        if (empty($lastPingAt)) {
            return 'never_connected';
        }

        $ping = Carbon::parse($lastPingAt, 'Asia/Ho_Chi_Minh');
        $diffMinutes = Carbon::now('Asia/Ho_Chi_Minh')->diffInMinutes($ping);

        if ($diffMinutes > 120) {
            return 'stale_offline';
        }

        if ($rawStatus === 'moving') {
            return 'moving';
        }

        if ($rawStatus === 'stopped') {
            return 'stopped';
        }

        return 'online';
    }
}
