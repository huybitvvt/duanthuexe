<?php

namespace App\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface VehicleRepository.
 *
 * @package namespace App\Repositories\Vehicle;
 */
interface VehicleRepository extends RepositoryInterface
{
    public function applyFilters($query, array $params);
}
