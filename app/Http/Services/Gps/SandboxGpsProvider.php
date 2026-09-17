<?php

namespace App\Http\Services\Gps;

use App\Interfaces\GpsProviderInterface;
use Carbon\Carbon;

class SandboxGpsProvider implements GpsProviderInterface
{
    protected $secretKey;
    public $simulatedDevices = [];

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?: (env('GPS_WEBHOOK_SECRET') ?: 'himoto-gps-sandbox-key-2026');
        $this->seedSimulatedFixtures();
    }

    /**
     * Pre-populate realistic device fixtures in Hanoi, Vietnam.
     */
    protected function seedSimulatedFixtures(): void
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        $this->simulatedDevices = [
            'DEV-ONLINE-01' => [
                'device_id' => 'DEV-ONLINE-01',
                'latitude' => 21.028511,
                'longitude' => 105.854222,
                'speed' => 35.5,
                'ignition' => true,
                'heading' => 90.0,
                'last_ping_at' => $now->copy()->subMinutes(2)->toDateTimeString(),
                'status' => 'moving',
                'provider' => 'sandbox_gps',
            ],
            'DEV-STOPPED-01' => [
                'device_id' => 'DEV-STOPPED-01',
                'latitude' => 21.033333,
                'longitude' => 105.800000,
                'speed' => 0.0,
                'ignition' => false,
                'heading' => 0.0,
                'last_ping_at' => $now->copy()->subMinutes(15)->toDateTimeString(),
                'status' => 'stopped',
                'provider' => 'sandbox_gps',
            ],
            'DEV-STALE-01' => [
                'device_id' => 'DEV-STALE-01',
                'latitude' => 20.999999,
                'longitude' => 105.788888,
                'speed' => 0.0,
                'ignition' => false,
                'heading' => 0.0,
                'last_ping_at' => $now->copy()->subHours(5)->toDateTimeString(),
                'status' => 'stale_offline',
                'provider' => 'sandbox_gps',
            ],
            'DEV-NEVER-01' => [
                'device_id' => 'DEV-NEVER-01',
                'latitude' => null,
                'longitude' => null,
                'speed' => 0.0,
                'ignition' => false,
                'heading' => null,
                'last_ping_at' => null,
                'status' => 'never_connected',
                'provider' => 'sandbox_gps',
            ],
        ];
    }

    /**
     * Get location for a specific GPS device.
     */
    public function getVehicleLocation(string $deviceId): array
    {
        if (isset($this->simulatedDevices[$deviceId])) {
            $data = $this->simulatedDevices[$deviceId];
            $data['normalized_status'] = $this->normalizeStatus($data['status'] ?? null, $data['last_ping_at'] ?? null);
            return $data;
        }

        return [
            'device_id' => $deviceId,
            'latitude' => null,
            'longitude' => null,
            'speed' => 0,
            'ignition' => false,
            'heading' => null,
            'last_ping_at' => null,
            'status' => 'never_connected',
            'normalized_status' => 'never_connected',
            'provider' => 'sandbox_gps',
        ];
    }

    /**
     * Get locations for a fleet of devices.
     */
    public function getFleetLocations(array $deviceIds): array
    {
        $res = [];
        foreach ($deviceIds as $id) {
            $res[$id] = $this->getVehicleLocation((string) $id);
        }
        return $res;
    }

    /**
     * Normalize status into standard domain status.
     */
    public function normalizeStatus(?string $rawStatus, ?string $lastPingAt): string
    {
        if (empty($lastPingAt)) {
            return 'never_connected';
        }

        $ping = Carbon::parse($lastPingAt, 'Asia/Ho_Chi_Minh');
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $diffMinutes = $now->diffInMinutes($ping);

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

    /**
     * Verify incoming webhook signature.
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        $sig = $headers['x-gps-signature'] ?? ($headers['X-Gps-Signature'] ?? null);
        $timestamp = $headers['x-gps-timestamp'] ?? ($headers['X-Gps-Timestamp'] ?? null);

        if (!$sig || !$timestamp) {
            return false;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', (string)$timestamp . '.' . json_encode($payload), $this->secretKey);
        return hash_equals($expected, (string)$sig);
    }
}
