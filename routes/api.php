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
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\DailyCashRegisterController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\KpiReportController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\CustomerReminderController;
use App\Http\Controllers\LeaseOwnershipController;
use App\Http\Controllers\NotificationController;

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
	$commit = getenv('RENDER_GIT_COMMIT') ?: null;
	try {
		DB::select('SELECT 1');

		return response()->json([
			'status' => 'ok',
			'service' => 'himoto-api',
			'database' => 'ok',
			'commit' => $commit,
		]);
	} catch (\Throwable $exception) {
		return response()->json([
			'status' => 'error',
			'service' => 'himoto-api',
			'database' => 'unavailable',
			'commit' => $commit,
		], 503);
	}
});

Route::get('/check-timezone', [Order\OrderController::class, 'check_timezone'])->middleware('auth.jwt');
Route::post('/customer-reminders/webhook/{provider}', [CustomerReminderController::class, 'webhook'])
    ->middleware(['schema.ready:reminder', 'schema.ready:audit']);

Route::group(['middleware' => 'api'], function ($router) {
    Route::group(['middleware' => 'check.status'],function () {
        Route::get('/verify-token', [AuthController::class, 'verifyToken'])->middleware('auth.jwt');
        Route::group(['prefix' => 'auth'], function ($router) {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register'])->middleware(['auth.jwt', 'admin']);
            Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.jwt');
            Route::post('/change-pass', [AuthController::class, 'changePassWord'])->middleware('auth.jwt');
            Route::group(['prefix' => 'stores', 'middleware' => 'auth.jwt'], function () {
                Route::get('/all', [StoreController::class, 'all']);
                Route::get('/{store}', [StoreController::class, 'show']);
            });
            Route::group(['prefix' => 'vehicle', 'middleware' => 'auth.jwt'], function ($router) {
                Route::get('/vehicles', [VehicleController::class, 'index']);
            });
            Route::group(['prefix' => 'leads', 'middleware' => 'auth.jwt'], function () {
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
Route::group(['middleware' => ['api', 'auth.jwt']], function ($router) {
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
				Route::post('/car-rental/preview', [Order\OrderController::class, 'preview']);
				Route::get('/car-rental/{order}/document', [Order\OrderController::class, 'document']);
				Route::post('/car-rental/lock-contract/{order}', [Order\OrderController::class, 'lockContract']);
            });
        
            Route::group(['prefix' => 'dashboard'], function ($router) {
                Route::get('/overview', [DashboardController::class, 'overview']);
                Route::get('/report', [DashboardController::class, 'report']);
                Route::get('/report-chart', [DashboardController::class, 'reportChart']);
            });
            Route::group(['prefix' => 'report'], function () {
                Route::get('/quick-report', [ReportController::class, 'quickReport']);
                Route::get('/detail-report', [ReportController::class, 'detailReport']);
                Route::get('/detail-report-new', [ReportController::class, 'detailReportNew']);
                Route::get('/detail-report-day-by-day', [ReportController::class, 'detailReportDayByDay']);
                Route::get('/kpi', [KpiReportController::class, 'index'])->middleware(['schema.ready:kpi', 'schema.ready:rbac']);
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
                Route::get('/upcoming', [MaintenanceScheduleController::class, 'upcoming']);
                Route::get('/', [MaintenanceScheduleController::class, 'index']);
                Route::post('/', [MaintenanceScheduleController::class, 'putOrPost']);
                Route::get('/{maintenanceSchedule}', [MaintenanceScheduleController::class, 'show']);
                Route::delete('/{maintenanceSchedule}', [MaintenanceScheduleController::class, 'destroy']); 
            });   
            Route::group(['prefix' => 'notifications'], function () {
                Route::get('/summary', [NotificationController::class, 'summary']);
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

            Route::group(['prefix' => 'warehouses'], function () {
                Route::get('/summary', [WarehouseController::class, 'summary']);
                Route::get('/transfers', [WarehouseController::class, 'transfers']);
                Route::get('/return-lookup', [WarehouseController::class, 'lookupReturnByLicense']);
                Route::get('/{storeId}/vehicles', [WarehouseController::class, 'vehicles']);
                Route::post('/transfers', [WarehouseController::class, 'dispatchTransfer']);
                Route::post('/transfers/{id}/receive', [WarehouseController::class, 'receiveTransfer']);
                Route::post('/transfers/{id}/cancel', [WarehouseController::class, 'cancelTransfer']);
                Route::post('/return-different-store', [WarehouseController::class, 'returnDifferentStore']);
                Route::post('/vehicle-exchange', [WarehouseController::class, 'exchangeVehicle']);
            });
            Route::get('/vehicles/{vehicleId}/movement-history', [WarehouseController::class, 'movementHistory']);

            Route::group(['prefix' => 'lease-contracts', 'middleware' => ['schema.ready:lease', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/', [LeaseContractController::class, 'index'])->middleware('permission:lease.view');
                Route::get('/stats', [LeaseContractController::class, 'stats'])->middleware('permission:lease.view');
                Route::get('/export', [LeaseContractController::class, 'export'])->middleware('permission:lease.export');
                Route::get('/{id}', [LeaseContractController::class, 'show'])->middleware('permission:lease.view');
                Route::get('/{id}/pdf', [LeaseContractController::class, 'pdf'])->middleware(['schema.ready:lease_document', 'permission:lease.view']);
                Route::get('/{id}/debt-statement.pdf', [LeaseContractController::class, 'debtStatementPdf'])->middleware(['schema.ready:lease_document', 'permission:lease.view']);
                Route::post('/', [LeaseContractController::class, 'store'])->middleware(['schema.ready:lease_document', 'permission:lease.view']);
                Route::post('/{id}/payments', [LeaseContractController::class, 'allocatePayment'])->middleware('permission:lease.collect');
                Route::post('/{id}/settle', [LeaseContractController::class, 'settle'])->middleware('permission:lease.collect');
                Route::post('/reverse-allocation/{allocationId}', [LeaseContractController::class, 'reverse'])->middleware('permission:lease.reverse_payment');
                Route::post('/{id}/notes', [LeaseContractController::class, 'addNote'])->middleware('permission:lease.view');
                Route::post('/{id}/ownership-requests', [LeaseOwnershipController::class, 'createDraft'])->middleware(['schema.ready:ownership', 'permission:lease.ownership_request']);
            });

            Route::group(['prefix' => 'lease-ownership-requests', 'middleware' => ['schema.ready:ownership', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/', [LeaseOwnershipController::class, 'index'])->middleware('permission:lease.view');
                Route::get('/{id}', [LeaseOwnershipController::class, 'show'])->middleware('permission:lease.view');
                Route::post('/{id}/submit', [LeaseOwnershipController::class, 'submit'])->middleware('permission:lease.ownership_request');
                Route::post('/{id}/approve', [LeaseOwnershipController::class, 'approve'])->middleware('permission:lease.ownership_approve');
                Route::post('/{id}/reject', [LeaseOwnershipController::class, 'reject'])->middleware('permission:lease.ownership_approve');
                Route::post('/{id}/execute', [LeaseOwnershipController::class, 'executeTransfer'])->middleware('permission:lease.ownership_execute');
            });

            Route::group(['prefix' => 'daily-cash-registers', 'middleware' => 'schema.ready:cash_register'], function () {
                Route::get('/summary', [DailyCashRegisterController::class, 'summary']);
                Route::get('/transactions', [DailyCashRegisterController::class, 'transactions']);
                Route::get('/sources', [DailyCashRegisterController::class, 'sources']);
                Route::post('/entries', [DailyCashRegisterController::class, 'storeEntry']);
                Route::post('/exchanges', [DailyCashRegisterController::class, 'storeExchange']);
                Route::post('/close', [DailyCashRegisterController::class, 'close']);
                Route::post('/reopen', [DailyCashRegisterController::class, 'reopen']);
                Route::get('/history', [DailyCashRegisterController::class, 'history']);
            });

            Route::group(['prefix' => 'hr', 'middleware' => ['schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/staff', [HrController::class, 'staffIndex'])->middleware('permission:hr.view');
                Route::post('/staff', [HrController::class, 'staffStore'])->middleware('permission:hr.manage_staff');
                Route::get('/organization-chart', [HrController::class, 'organizationChart'])->middleware('permission:hr.view');
                Route::get('/attendance', [HrController::class, 'attendanceIndex'])->middleware(['schema.ready:attendance', 'permission:hr.view']);
                Route::post('/attendance', [HrController::class, 'attendanceStore'])->middleware(['schema.ready:attendance', 'permission:hr.manage_attendance']);
                Route::get('/duty-schedules', [HrController::class, 'dutySchedules'])->middleware('permission:hr.view');
                Route::post('/duty-schedules', [HrController::class, 'saveDutySchedule'])->middleware('permission:hr.manage_schedule');
                Route::delete('/duty-schedules/{id}', [HrController::class, 'deleteDutySchedule'])->middleware('permission:hr.manage_schedule');
            });

            Route::group(['prefix' => 'accounting', 'middleware' => ['schema.ready:accounting', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/', [AccountingController::class, 'index'])->middleware('permission:accounting.view');
                Route::get('/accounts', [AccountingController::class, 'getAccounts'])->middleware('permission:accounting.view');
                Route::get('/journal-entries', [AccountingController::class, 'getJournalEntries'])->middleware('permission:accounting.view');
                Route::get('/journal-entries/{id}', [AccountingController::class, 'getJournalEntry'])->middleware('permission:accounting.view');
                Route::post('/journal-entries', [AccountingController::class, 'postJournalEntry'])->middleware('permission:accounting.post');
                Route::post('/journal-entries/{id}/reverse', [AccountingController::class, 'reverseJournalEntry'])->middleware('permission:accounting.reverse');
                Route::get('/periods', [AccountingController::class, 'getPeriods'])->middleware('permission:accounting.view');
                Route::post('/periods/close', [AccountingController::class, 'closePeriod'])->middleware('permission:accounting.close_period');
                Route::post('/periods/reopen', [AccountingController::class, 'reopenPeriod'])->middleware('permission:accounting.close_period');
                Route::get('/reconciliations', [AccountingController::class, 'getReconciliations'])->middleware('permission:accounting.view');
                Route::post('/reconciliations/cash', [AccountingController::class, 'reconcileCash'])->middleware('permission:accounting.reconcile');
                Route::post('/reconciliations/bank', [AccountingController::class, 'reconcileBank'])->middleware('permission:accounting.reconcile');
                Route::post('/reconciliations/{id}/approve', [AccountingController::class, 'approveReconciliation'])->middleware('permission:accounting.reconcile');
                Route::get('/trial-balance', [AccountingController::class, 'getTrialBalance'])->middleware('permission:accounting.view');
                Route::get('/general-ledger/{accountId}', [AccountingController::class, 'getGeneralLedger'])->middleware('permission:accounting.view');
                Route::get('/legacy-shadow-analysis', [AccountingController::class, 'legacyShadowAnalysis'])->middleware('permission:accounting.view');
                Route::post('/vat-documents', [AccountingController::class, 'saveVatDocument'])->middleware('permission:accounting.post');
                Route::delete('/vat-documents/{id}', [AccountingController::class, 'deleteVatDocument'])->middleware('permission:accounting.reverse');
                Route::post('/assets', [AccountingController::class, 'saveAsset'])->middleware('permission:accounting.post');
                Route::delete('/assets/{id}', [AccountingController::class, 'deleteAsset'])->middleware('permission:accounting.reverse');
            });

            Route::group(['prefix' => 'customer-reminders', 'middleware' => ['schema.ready:reminder', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/action-list', [CustomerReminderController::class, 'actionList'])->middleware('permission:reminder.view');
                Route::post('/scan', [CustomerReminderController::class, 'scan'])->middleware('permission:reminder.manage');
                Route::post('/dispatch', [CustomerReminderController::class, 'dispatchOutbox'])->middleware('permission:reminder.view');
            });

            Route::group(['prefix' => 'gps', 'middleware' => ['schema.ready:gps', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/overview', [CustomerReminderController::class, 'gpsOverview'])->middleware('permission:gps.view');
                Route::get('/devices/{deviceId}/history', [CustomerReminderController::class, 'gpsDeviceHistory'])->middleware('permission:gps.view');
                Route::post('/devices/{deviceId}/sync', [CustomerReminderController::class, 'gpsSyncDevice'])->middleware('permission:gps.manage_devices');
                Route::post('/recovery-actions', [CustomerReminderController::class, 'gpsRecoveryAction'])->middleware('permission:gps.recovery_action');
            });

        });
  

    });
});


Route::group(['middleware' => ['api', 'auth.jwt']], function () {
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
 
