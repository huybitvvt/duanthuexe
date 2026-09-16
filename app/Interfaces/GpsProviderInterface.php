<?php

namespace App\Interfaces;

interface GpsProviderInterface
{
    /**
     * Get real-time or latest known location for a specific GPS device.
     *
     * @param string $deviceId
     * @return array
     */
    public function getVehicleLocation(string $deviceId): array;

    /**
     * Get locations for a fleet of devices.
     *
     * @param array $deviceIds
     * @return array
     */
    public function getFleetLocations(array $deviceIds): array;

    /**
     * Standardize GPS status into approved domain statuses:
     * - 'never_connected'
     * - 'online'
     * - 'stale_offline'
     * - 'moving'
     * - 'stopped'
     * - 'unknown'
     *
     * @param string|null $rawStatus
     * @param string|null $lastPingAt
     * @return string
     */
    public function normalizeStatus(?string $rawStatus, ?string $lastPingAt): string;
}
