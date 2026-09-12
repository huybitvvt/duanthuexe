<?php


namespace App\Http\Services;


use App\Models\PriceVehicle;
use App\Repositories\PriceVehicleRepository;
use Illuminate\Http\Request;

class PriceVehicleService
{
    protected $priceVehicleRepository;

    public function __construct(PriceVehicleRepository $priceVehicleRepository)
    {
        $this->priceVehicleRepository = $priceVehicleRepository;
    }

    public function index(Request $request)
    {
        return $this->priceVehicleRepository->index($request);
    }

    public function storeOrUpdate(Request $request)
    {
        $priceVehicles = $request->get('priceVehicles', []);
        foreach ($priceVehicles as $priceVehicle) {
            if (isset($priceVehicle['id'])) {
                $priceVehicleModel = PriceVehicle::query()->find($priceVehicle['id']);
                $priceVehicleModel->update($priceVehicle);
            } else {
                PriceVehicle::query()->create($priceVehicle);
            }
        }
    }
}
