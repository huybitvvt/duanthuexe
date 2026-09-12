<?php

use App\Http\Controllers\LeadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Order;
use App\Http\Controllers\Vehicle\PriceVehicleController;
use App\Http\Controllers\Order\OrderSellController;
use App\Http\Controllers\Vehicle\VehicleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Store\StoreController;
use App\Http\Controllers\Transaction\TransactionController;
use App\Http\Controllers\Report\ReportController;
use \App\Http\Controllers\Users\RoleController;
use \App\Http\Controllers\DashboardController;
use \App\Http\Controllers\FileController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CashController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Controllers\ExportsController;
use App\Http\Controllers\MaintenanceVehicleController;
use App\Http\Controllers\MaintenanceRuleController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\MaintenanceTypeController;
use App\Http\Controllers\MaintenanceScheduleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/health', function () {
	try {
		DB::select('SELECT 1');

		return response()->json([
			'status' => 'ok',
			'service' => 'himoto-api',
			'database' => 'ok',
		]);
	} catch (\Throwable $exception) {
		return response()->json([
			'status' => 'error',
			'service' => 'himoto-api',
			'database' => 'unavailable',
		], 503);
	}
});

Route::get('/check-timezone', [Order\OrderController::class, 'check_timezone']);

Route::group(['middleware' => 'api'], function ($router) {
    Route::group(['middleware' => 'check.status'],function () {
        Route::get('/verify-token', [AuthController::class, 'verifyToken'])->middleware('auth.jwt');
        Route::group(['prefix' => 'auth'], function ($router) {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-pass', [AuthController::class, 'changePassWord']);
            Route::group(['prefix' => 'stores'], function () {
                Route::get('/all', [StoreController::class, 'all']);
                Route::get('/{store}', [StoreController::class, 'show']);
            });
            Route::group(['prefix' => 'vehicle'], function ($router) {
                Route::get('/vehicles', [VehicleController::class, 'index']);
            });
            Route::group(['prefix' => 'leads'], function () {     
                Route::get('/', [LeadController::class, 'index']);
                Route::get('/unique-users', [LeadController::class, 'uniqueUsers']);
                Route::get('/{id}', [LeadController::class, 'show']);
                Route::post('/', [LeadController::class, 'create']);
                Route::put('/{lead}', [LeadController::class, 'update']);
                Route::post('/{lead}', [LeadController::class, 'destroy']); 
            });  
        });
        Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
        Route::post('/reset-password', [UserController::class, 'resetPassword']);
});
});
Route::group(['middleware' => 'api'], function ($router) {
    Route::group(['middleware' => 'non.sale'],function () {
        Route::get('/get-list-user', [AuthController::class, 'getListUser']);
        Route::group(['prefix' => 'auth'], function ($router) {
                  
            Route::group(['prefix' => 'order'], function ($router) {
            
                Route::put('/car-rental/deposit/{order}', [Order\OrderController::class, 'deposit']);
                Route::put('/car-rental/complete/{order}', [Order\OrderController::class, 'complete']);
                Route::get('/car-rental', [Order\OrderController::class, 'index']);
                Route::get('/car-rental/{order}', [Order\OrderController::class, 'show']);
                Route::put('/car-rental/{order}', [Order\OrderController::class, 'update']);
                Route::post('/car-rental', [Order\OrderController::class, 'store']);
                Route::delete('/car-rental/{data}', [Order\OrderController::class, 'destroy']);
                Route::post('/add-on-price', [Order\OrderController::class, 'addOnPrice']);
				Route::post('/start-contract', [Order\OrderController::class, 'startContract']);
				Route::post('/close-deposit-order', [Order\OrderController::class, 'closeDeposit']);
				Route::post('/calc_return_early_amount', [Order\OrderController::class, 'calc_return_early_amount']);
				Route::post('/calc_order_before_complete', [Order\OrderController::class, 'calc_order_before_complete']);
            });
        
            Route::group(['prefix' => 'dashboard'], function ($router) {
                Route::get('/report', [DashboardController::class, 'report']);
                Route::get('/report-chart', [DashboardController::class, 'reportChart']);
            });
            Route::group(['prefix' => 'report'], function () {
                Route::get('/quick-report', [ReportController::class, 'quickReport']);
                Route::get('/detail-report', [ReportController::class, 'detailReport']);
                Route::get('/detail-report-new', [ReportController::class, 'detailReportNew']);
                Route::get('/detail-report-day-by-day', [ReportController::class, 'detailReportDayByDay']);
            });
            Route::group(['prefix' => 'vehicle'], function ($router) {
                
                Route::get('/vehicles_with_revenue', [VehicleController::class, 'indexWithRevenue']);
                Route::get('/vehicles/report', [VehicleController::class, 'report']);
                Route::post('/vehicles/store', [VehicleController::class, 'store']);
                Route::post('/vehicles/update', [VehicleController::class, 'update']);
                Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
            });
            Route::group(['prefix' => 'order-sell'], function ($router) {
                Route::get('/', [OrderSellController::class, 'index']);
                Route::get('/report', [OrderSellController::class, 'report']);
                Route::post('/store', [OrderSellController::class, 'store']);
                Route::post('/update', [OrderSellController::class, 'update']);
                Route::delete('/{sellOrder}', [OrderSellController::class, 'destroy']);
            });
            Route::group(['prefix' => 'stores'], function () {
            
                Route::get('/', [StoreController::class, 'index']);
                Route::post('/', [StoreController::class, 'store']);
            
                Route::put('/{store}', [StoreController::class, 'update']);
                Route::delete('/{store}', [StoreController::class, 'destroy']);
            });
            Route::group(['prefix' => 'customers'], function () {
                Route::get('/', [CustomerController::class, 'index']);
                Route::post('/', [CustomerController::class, 'store']);
                Route::get('/search-by-id-card', [CustomerController::class, 'searchCustomerByIdCard']);
                Route::get('/{customer}', [CustomerController::class, 'show']);
                Route::put('/{customer}', [CustomerController::class, 'update']);
                Route::delete('/{customer}', [CustomerController::class, 'destroy']);
            });
            Route::group(['prefix' => 'transactions'], function () {
				Route::post('/update', [TransactionController::class, 'updateNew']);
                Route::get('/', [TransactionController::class, 'index']);
                Route::get('/stats', [TransactionController::class, 'stats']);
                Route::delete('/{transaction}', [TransactionController::class, 'destroy']);
				Route::match(['put', 'post'],'/{transaction?}', [TransactionController::class, 'putOrPost']);
            });
            Route::group(['prefix' => 'priceVehicles'], function () {
                Route::get('/', [PriceVehicleController::class, 'index']);
                Route::post('/', [PriceVehicleController::class, 'storeOrUpdate']);
                Route::delete('/{priceVehicle}', [PriceVehicleController::class, 'destroy']);
            });
            Route::group(['prefix' => 'role'], function () {
                Route::get('/all', [RoleController::class, 'all']);
            });
            Route::group(['prefix' => 'users'], function () {
                Route::get('/', [UserController::class, 'index']);
                Route::get('/get-staff-by-store', [UserController::class, 'getStaffByStore']);
                Route::group(['middleware' => ['admin']], function () {
                    Route::post('/store', [UserController::class, 'store']);
                    Route::post('/update', [UserController::class, 'update']);
                    Route::post('/change-password', [UserController::class, 'changePassword']);
                    Route::delete('/{user}', [UserController::class, 'destroy']);
                });

				Route::post('/change-my-password', [UserController::class, 'changeMyPassword']);
            });
        
            Route::group(['prefix' => 'banks'], function () {
                Route::get('/all', [BankController::class, 'all']);
                Route::get('/', [BankController::class, 'index']);
                Route::get('/{bank}', [BankController::class, 'show']);                 
            });
            Route::group(['prefix' => 'cash'], function () {
                Route::get('/all', [CashController::class, 'all']);
                Route::get('/', [CashController::class, 'index']);
                Route::get('/{cash}', [CashController::class, 'show']); 
                            
            });
            Route::group(['prefix' => 'receipt'], function () {
                Route::get('/', [ReceiptController::class, 'index']);
                Route::get('/{transaction}', [ReceiptController::class, 'show']);   
                Route::match(['put', 'post'],'/{receipt?}', [ReceiptController::class, 'putOrPost']);  
                Route::delete('/{transaction}', [ReceiptController::class, 'destroy']);         
            });   
            Route::group(['prefix' => 'export'], function () {
            Route::get('/customers', [ExportsController::class, 'customers']);
            Route::get('/vehicles', [ExportsController::class, 'vehicles']);
            Route::get('/transactions', [ExportsController::class, 'transactions']);
            Route::get('/orders', [ExportsController::class, 'orders']);
            Route::get('/general_reports', [ExportsController::class, 'generalReports']);
            Route::get('/vehicle_revenue', [ExportsController::class, 'vehicleRevenue']);
            Route::get('/banks', [ExportsController::class, 'banks']);
            Route::get('/cash', [ExportsController::class, 'cash']);
            }); 
            Route::group(['prefix' => 'maintenance-rules'], function () {
                Route::get('/', [MaintenanceRuleController::class, 'index']);
                Route::post('/', [MaintenanceRuleController::class, 'putOrPost']);
                Route::put('/', [MaintenanceRuleController::class, 'putOrPost']);
                Route::delete('/{maintenanceRule}', [MaintenanceRuleController::class, 'destroy']); 
            });      
            Route::group(['prefix' => 'maintenance-vehicle'], function () {
                Route::get('/', [MaintenanceVehicleController::class, 'index']);
                Route::match(['put', 'post'],'/{maintenanceVehicle?}', [MaintenanceVehicleController::class, 'putOrPost'])   ;  
                Route::delete('/{maintenanceVehicle}', [MaintenanceVehicleController::class, 'destroy']); 
            });     
            Route::group(['prefix' => 'maintenance-schedules'], function () {
                Route::get('/', [MaintenanceScheduleController::class, 'index']);
                Route::post('/', [MaintenanceScheduleController::class, 'putOrPost']);
                Route::delete('/{maintenanceSchedule}', [MaintenanceScheduleController::class, 'destroy']); 
            });   
            Route::group(['prefix' => 'maintenance-log'], function () {
                Route::get('/', [MaintenanceLogController::class, 'index']);
                Route::match(['put', 'post'],'/{maintenanceLogs?}', [MaintenanceLogController::class, 'putOrPost'])   ;  
                Route::delete('/{maintenanceLog}', [MaintenanceLogController::class, 'destroy']); 
            });   
            Route::group(['prefix' => 'maintenance-types'], function () {
                Route::get('/', [MaintenanceTypeController::class, 'index']);
                Route::post('/', [MaintenanceTypeController::class, 'putOrPost']);
                Route::put('/', [MaintenanceTypeController::class, 'putOrPost']);
                Route::delete('/{maintenanceType}', [MaintenanceTypeController::class, 'destroy']); 
            }); 

			Route::group(['prefix' => 'file'], function ($router) {
                Route::post('/upload-images', [FileController::class, 'uploadImages']);
				Route::delete('/{file_id}', [FileController::class, 'destroy']); 
            });

        });
  

    });
});


Route::group(['middleware' => 'api'], function () {
    Route::group(['middleware' => 'admin'],function () {
      Route::group(['prefix' => 'auth'], function () {
        Route::group(['prefix' => 'banks'], function () {
            Route::post('/', [BankController::class, 'store']);
            Route::put('/update/{bank}', [BankController::class, 'update']);
            Route::delete('/{bank}', [BankController::class, 'destroy']);    
        });
        Route::group(['prefix' => 'cash'], function () {
            Route::post('/', [CashController::class, 'store']);
            Route::put('/update/{cash}', [CashController::class, 'update']); 
            Route::delete('/{cash}', [CashController::class, 'destroy']);
        });
       
    }); 
});
});
 
