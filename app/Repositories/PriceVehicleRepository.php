<?php


namespace App\Repositories;


use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface OrderVehicleDetailRepository.
 *
 * @package namespace App\Repositories;
 */
interface PriceVehicleRepository extends RepositoryInterface
{
    public function index(Request $request);
}
