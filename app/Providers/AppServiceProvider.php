<?php

namespace App\Providers;

use App\Repositories\ActivityLogRepository;
use App\Repositories\ActivityLogRepositoryEloquent;
use App\Repositories\AddOnOrderRepository;
use App\Repositories\AddOnOrderRepositoryEloquent;
use App\Repositories\CustomerRepository;
use App\Repositories\CustomerRepositoryEloquent;
use App\Repositories\OrderRepository;
use App\Repositories\OrderRepositoryEloquent;
use App\Repositories\OrderVehicleDetailRepository;
use App\Repositories\OrderVehicleDetailRepositoryEloquent;
use App\Repositories\PermissionRepository;
use App\Repositories\PermissionRepositoryEloquent;
use App\Repositories\PriceVehicleRepository;
use App\Repositories\PriceVehicleRepositoryEloquent;
use App\Repositories\RoleRepository;
use App\Repositories\RoleRepositoryEloquent;
use App\Repositories\SellOrderItemRepository;
use App\Repositories\SellOrderItemRepositoryEloquent;
use App\Repositories\SellOrderRepository;
use App\Repositories\SellOrderRepositoryEloquent;
use App\Repositories\StoreRepository;
use App\Repositories\StoreRepositoryEloquent;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionRepositoryEloquent;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryEloquent;
use App\Repositories\VehicleRepository;
use App\Repositories\VehicleRepositoryEloquent;
use App\Repositories\BankRepository;
use App\Repositories\BankRepositoryEloquent;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepository();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        Schema::defaultStringLength(191);
    }

    public function registerRepository()
    {
        App::bind(VehicleRepository::class, VehicleRepositoryEloquent::class);
        App::bind(CustomerRepository::class, CustomerRepositoryEloquent::class);
        App::bind(StoreRepository::class, StoreRepositoryEloquent::class);
        App::bind(OrderVehicleDetailRepository::class, OrderVehicleDetailRepositoryEloquent::class);
        App::bind(PriceVehicleRepository::class, PriceVehicleRepositoryEloquent::class);
        App::bind(OrderRepository::class, OrderRepositoryEloquent::class);
        App::bind(SellOrderRepository::class, SellOrderRepositoryEloquent::class);
        App::bind(SellOrderItemRepository::class, SellOrderItemRepositoryEloquent::class);
        App::bind(TransactionRepository::class, TransactionRepositoryEloquent::class);
        App::bind(ActivityLogRepository::class, ActivityLogRepositoryEloquent::class);
        App::bind(RoleRepository::class, RoleRepositoryEloquent::class);
        App::bind(PermissionRepository::class, PermissionRepositoryEloquent::class);
        App::bind(UserRepository::class, UserRepositoryEloquent::class);
        App::bind(AddOnOrderRepository::class, AddOnOrderRepositoryEloquent::class);
        App::bind(BankRepository::class, BankRepositoryEloquent::class);
    }
}
