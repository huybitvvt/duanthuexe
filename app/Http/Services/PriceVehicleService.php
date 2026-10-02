<?php


namespace App\Http\Services;


use App\Models\PriceVehicle;
use App\Repositories\PriceVehicleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $data = $request->validate([
            'priceVehicles' => 'present|array',
            'priceVehicles.*' => 'required|array',
            'priceVehicles.*.id' => 'nullable|integer|distinct|exists:pricing,id',
            'priceVehicles.*.type' => 'required|string|max:50',
            'priceVehicles.*.from_year' => 'required|integer|min:1900|max:9999',
            'priceVehicles.*.to_year' => 'required|integer|gte:priceVehicles.*.from_year|max:9999',
            'priceVehicles.*.from_date' => 'required|integer|min:1',
            'priceVehicles.*.to_date' => 'required|integer|gte:priceVehicles.*.from_date',
            'priceVehicles.*.price' => 'required|numeric|min:0',
            'priceVehicles.*.price_type' => 'required|in:day,total',
        ]);
        DB::transaction(function () use ($data) {
            foreach ($data['priceVehicles'] as $priceVehicle) {
                if (isset($priceVehicle['id'])) {
                    $priceVehicleModel = PriceVehicle::query()->lockForUpdate()->findOrFail($priceVehicle['id']);
                    $priceVehicleModel->update($priceVehicle);
                } else {
                    PriceVehicle::query()->create($priceVehicle);
                }
            }
        });
    }
}
