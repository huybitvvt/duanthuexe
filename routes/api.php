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
use App\Http\Controllers\SepayPaymentController;
use App\Http\Controllers\BusinessApprovalController;

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

// Resource identifiers are numeric. Reject malformed paths before model binding
// reaches PostgreSQL or a controller parameter with an integer type.
foreach (['id', 'store', 'storeId', 'vehicle', 'vehicleId', 'order', 'lead', 'customer',
    'user', 'bank', 'cash', 'transaction', 'receipt', 'priceVehicle', 'sellOrder', 'file_id',
    'maintenanceRule', 'maintenanceVehicle', 'maintenanceSchedule', 'maintenanceLogs',
    'maintenanceLog', 'maintenanceType', 'deviceId', 'accountId', 'allocationId'] as $parameter) {
    Route::pattern($parameter, '[0-9]+');
}
Route::pattern('data', '[0-9]+(?:,[0-9]+)*');

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
Route::post('/sepay/webhook', [SepayPaymentController::class, 'webhook']);

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
                Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('permission:vehicle.view_all');
                Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->where('vehicle', '[0-9]+')->middleware('permission:vehicle.view_all');
            });
            Route::group(['prefix' => 'leads', 'middleware' => ['auth.jwt', 'permission:lead.manage']], function () {
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
        Route::get('/auth/sepay/payment-requests', [SepayPaymentController::class, 'index'])->middleware('permission:finance.transaction.view');
        Route::post('/auth/sepay/payment-requests', [SepayPaymentController::class, 'store'])->middleware('permission:finance.transaction.manage');
        Route::get('/auth/sepay/payment-requests/unmatched', [SepayPaymentController::class, 'unmatched'])->middleware('permission:finance.transaction.view');
        Route::get('/auth/sepay/payment-requests/{id}', [SepayPaymentController::class, 'show'])->middleware('permission:finance.transaction.view');
        Route::get('/auth/sepay/orders/{id}/options', [SepayPaymentController::class, 'orderOptions'])->middleware('permission:finance.transaction.view');
        Route::get('/get-list-user', [AuthController::class, 'getListUser'])->middleware('admin');
        Route::group(['prefix' => 'auth'], function ($router) {
                  
            Route::group(['prefix' => 'order'], function ($router) {
            
                Route::put('/car-rental/deposit/{order}', [Order\OrderController::class, 'deposit'])->middleware('permission:order.update');
                Route::post('/car-rental/check-in/{order}', [Order\OrderController::class, 'checkIn'])->middleware('permission:order.return');
                Route::put('/car-rental/complete/{order}', [Order\OrderController::class, 'complete'])->middleware('permission:order.settle_return');
                Route::get('/car-rental', [Order\OrderController::class, 'index'])->middleware('permission:order.view_store');
                Route::get('/car-rental/{order}', [Order\OrderController::class, 'show'])->middleware('permission:order.view_store');
                Route::put('/car-rental/{order}', [Order\OrderController::class, 'update'])->middleware('permission:order.update');
                Route::post('/car-rental', [Order\OrderController::class, 'store'])->middleware('permission:order.create');
                Route::delete('/car-rental/{data}', [Order\OrderController::class, 'destroy'])->middleware('permission:order.delete');
                Route::post('/add-on-price', [Order\OrderController::class, 'addOnPrice'])->middleware('permission:order.renewal_fee');
				Route::post('/close-deposit-order', [Order\OrderController::class, 'closeDeposit'])->middleware('permission:order.close_deposit');
				Route::post('/calc_return_early_amount', [Order\OrderController::class, 'calc_return_early_amount'])->middleware('permission:order.settle_return');
				Route::post('/calc_order_before_complete', [Order\OrderController::class, 'calc_order_before_complete'])->middleware('permission:order.settle_return');
				Route::post('/car-rental/preview', [Order\OrderController::class, 'preview'])->middleware('permission:order.create');
				Route::get('/car-rental/{order}/document', [Order\OrderController::class, 'document'])->middleware('permission:order.view_store');
				Route::get('/car-rental/{order}/handover', [Order\OrderController::class, 'handover'])->middleware('permission:order.view_store');
				Route::post('/car-rental/lock-contract/{order}', [Order\OrderController::class, 'lockContract'])->middleware('permission:order.handover');
            });
        
            Route::group(['prefix' => 'dashboard', 'middleware' => 'permission:dashboard.view_store'], function ($router) {
                Route::get('/overview', [DashboardController::class, 'overview']);
                Route::get('/report', [DashboardController::class, 'report']);
                Route::get('/report-chart', [DashboardController::class, 'reportChart']);
            });
            Route::group(['prefix' => 'report'], function () {
                Route::get('/quick-report', [ReportController::class, 'quickReport'])->middleware('permission:order.count');
                Route::get('/detail-report', [ReportController::class, 'detailReport'])->middleware('permission:accounting.view');
                Route::get('/detail-report-new', [ReportController::class, 'detailReportNew'])->middleware('permission:order.report_store,accounting.view');
                Route::get('/detail-report-day-by-day', [ReportController::class, 'detailReportDayByDay'])->middleware('permission:accounting.view');
                Route::get('/kpi', [KpiReportController::class, 'index'])->middleware(['permission:accounting.view', 'schema.ready:kpi', 'schema.ready:rbac']);
            });
            Route::group(['prefix' => 'vehicle'], function ($router) {
                
                Route::get('/vehicles_with_revenue', [VehicleController::class, 'indexWithRevenue'])->middleware('permission:accounting.view');
                Route::get('/vehicles/report', [VehicleController::class, 'report'])->middleware('permission:vehicle.view_all');
                Route::post('/vehicles/store', [VehicleController::class, 'store'])->middleware('permission:vehicle.manage');
                Route::post('/vehicles/update', [VehicleController::class, 'update'])->middleware('permission:vehicle.manage');
                Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicle.manage');
            });
            Route::group(['prefix' => 'order-sell', 'middleware' => 'permission:order.manage'], function ($router) {
                Route::get('/', [OrderSellController::class, 'index']);
                Route::get('/report', [OrderSellController::class, 'report']);
                Route::post('/store', [OrderSellController::class, 'store']);
                Route::post('/update', [OrderSellController::class, 'update']);
                Route::delete('/{sellOrder}', [OrderSellController::class, 'destroy']);
            });
            Route::group(['prefix' => 'stores'], function () {
            
                Route::get('/', [StoreController::class, 'index']);
                Route::post('/', [StoreController::class, 'store'])->middleware('permission:store.manage');
            
                Route::put('/{store}', [StoreController::class, 'update'])->middleware('permission:store.manage');
                Route::delete('/{store}', [StoreController::class, 'destroy'])->middleware('permission:store.manage');
            });
            Route::group(['prefix' => 'customers', 'middleware' => 'permission:customer.manage'], function () {
                Route::get('/', [CustomerController::class, 'index']);
                Route::post('/', [CustomerController::class, 'store']);
                Route::get('/search-by-id-card', [CustomerController::class, 'searchCustomerByIdCard']);
                Route::get('/{customer}', [CustomerController::class, 'show']);
                Route::put('/{customer}', [CustomerController::class, 'update']);
                Route::delete('/{customer}', [CustomerController::class, 'destroy']);
            });
            Route::group(['prefix' => 'transactions'], function () {
                Route::post('/update', [TransactionController::class, 'updateNew'])->middleware('permission:finance.transaction.manage');
                Route::get('/', [TransactionController::class, 'index'])->middleware('permission:finance.transaction.view');
                Route::get('/stats', [TransactionController::class, 'stats'])->middleware('permission:finance.transaction.view');
                Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->middleware('permission:finance.transaction.manage');
                Route::match(['put', 'post'],'/{transaction?}', [TransactionController::class, 'putOrPost'])->middleware('permission:finance.transaction.manage');
            });
            Route::group(['prefix' => 'priceVehicles'], function () {
                Route::get('/', [PriceVehicleController::class, 'index'])->middleware('permission:order.create');
                Route::post('/', [PriceVehicleController::class, 'storeOrUpdate'])->middleware('permission:vehicle.manage');
                Route::delete('/{priceVehicle}', [PriceVehicleController::class, 'destroy'])->middleware('permission:vehicle.manage');
            });
            Route::group(['prefix' => 'role', 'middleware' => 'admin'], function () {
                Route::get('/all', [RoleController::class, 'all']);
            });
            Route::group(['prefix' => 'users'], function () {
                Route::get('/', [UserController::class, 'index'])->middleware('admin');
                Route::get('/get-staff-by-store', [UserController::class, 'getStaffByStore'])->middleware('permission:hr.view,order.create');
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
            Route::group(['prefix' => 'cash', 'middleware' => 'permission:finance.cash.view_all'], function () {
                Route::get('/all', [CashController::class, 'all']);
                Route::get('/', [CashController::class, 'index']);
                Route::get('/{cash}', [CashController::class, 'show']); 
                            
            });
            Route::group(['prefix' => 'receipt', 'middleware' => 'permission:finance.transaction.view'], function () {
                Route::get('/', [ReceiptController::class, 'index']);
                Route::get('/{transaction}', [ReceiptController::class, 'show']);   
                Route::match(['put', 'post'],'/{receipt?}', [ReceiptController::class, 'putOrPost'])->middleware('permission:finance.transaction.manage');
                Route::delete('/{transaction}', [ReceiptController::class, 'destroy'])->middleware('permission:finance.transaction.manage');
            });   
            Route::group(['prefix' => 'export'], function () {
            Route::get('/customers', [ExportsController::class, 'customers'])->middleware('permission:customer.manage');
            Route::get('/vehicles', [ExportsController::class, 'vehicles'])->middleware('permission:vehicle.manage');
            Route::get('/transactions', [ExportsController::class, 'transactions'])->middleware('permission:accounting.export');
            Route::get('/orders', [ExportsController::class, 'orders'])->middleware('permission:order.manage');
            Route::get('/general_reports', [ExportsController::class, 'generalReports'])->middleware('permission:accounting.export');
            Route::get('/vehicle_revenue', [ExportsController::class, 'vehicleRevenue'])->middleware('permission:accounting.export');
            Route::get('/banks', [ExportsController::class, 'banks'])->middleware('permission:accounting.export');
            Route::get('/cash', [ExportsController::class, 'cash'])->middleware('permission:accounting.export');
            }); 
            Route::group(['prefix' => 'maintenance-rules', 'middleware' => 'permission:vehicle.manage'], function () {
                Route::get('/', [MaintenanceRuleController::class, 'index']);
                Route::post('/', [MaintenanceRuleController::class, 'putOrPost']);
                Route::put('/', [MaintenanceRuleController::class, 'putOrPost']);
                Route::delete('/{maintenanceRule}', [MaintenanceRuleController::class, 'destroy']); 
            });      
            Route::group(['prefix' => 'maintenance-vehicle', 'middleware' => 'permission:vehicle.manage'], function () {
                Route::get('/', [MaintenanceVehicleController::class, 'index']);
                Route::match(['put', 'post'],'/{maintenanceVehicle?}', [MaintenanceVehicleController::class, 'putOrPost'])   ;  
                Route::delete('/{maintenanceVehicle}', [MaintenanceVehicleController::class, 'destroy']); 
            });     
            Route::group(['prefix' => 'maintenance-schedules', 'middleware' => 'permission:vehicle.manage'], function () {
                Route::get('/upcoming', [MaintenanceScheduleController::class, 'upcoming']);
                Route::get('/', [MaintenanceScheduleController::class, 'index']);
                Route::post('/', [MaintenanceScheduleController::class, 'putOrPost']);
                Route::get('/{maintenanceSchedule}', [MaintenanceScheduleController::class, 'show']);
                Route::delete('/{maintenanceSchedule}', [MaintenanceScheduleController::class, 'destroy']); 
            });   
            Route::group(['prefix' => 'notifications'], function () {
                Route::get('/summary', [NotificationController::class, 'summary']);
            });   
            Route::group(['prefix' => 'maintenance-log', 'middleware' => 'permission:vehicle.manage'], function () {
                Route::get('/', [MaintenanceLogController::class, 'index']);
                Route::match(['put', 'post'],'/{maintenanceLogs?}', [MaintenanceLogController::class, 'putOrPost'])   ;  
                Route::delete('/{maintenanceLog}', [MaintenanceLogController::class, 'destroy']); 
            });   
            Route::group(['prefix' => 'maintenance-types', 'middleware' => 'permission:vehicle.manage'], function () {
                Route::get('/', [MaintenanceTypeController::class, 'index']);
                Route::post('/', [MaintenanceTypeController::class, 'putOrPost']);
                Route::put('/', [MaintenanceTypeController::class, 'putOrPost']);
                Route::delete('/{maintenanceType}', [MaintenanceTypeController::class, 'destroy']); 
            }); 

			Route::group(['prefix' => 'file'], function ($router) {
                Route::post('/upload-images', [FileController::class, 'uploadImages'])->middleware('permission:file.upload');
				Route::delete('/{file_id}', [FileController::class, 'destroy'])->middleware('permission:business.manage');
            });

            Route::group(['prefix' => 'warehouses'], function () {
                Route::get('/summary', [WarehouseController::class, 'summary']);
                Route::get('/transfers', [WarehouseController::class, 'transfers'])->middleware('permission:vehicle.manage');
                Route::get('/return-lookup', [WarehouseController::class, 'lookupReturnByLicense'])->middleware('permission:vehicle.manage');
                Route::get('/{storeId}/vehicles', [WarehouseController::class, 'vehicles']);
                Route::post('/transfers', [WarehouseController::class, 'dispatchTransfer'])->middleware('permission:vehicle.manage');
                Route::post('/transfers/{id}/receive', [WarehouseController::class, 'receiveTransfer'])->middleware('permission:vehicle.manage');
                Route::post('/transfers/{id}/cancel', [WarehouseController::class, 'cancelTransfer'])->middleware('permission:vehicle.manage');
                Route::post('/return-different-store', [WarehouseController::class, 'returnDifferentStore'])->middleware('permission:vehicle.manage');
                Route::post('/vehicle-exchange', [WarehouseController::class, 'exchangeVehicle'])->middleware('permission:vehicle.manage');
            });
            Route::get('/vehicles/{vehicleId}/movement-history', [WarehouseController::class, 'movementHistory'])->middleware('permission:vehicle.view_all');

            Route::group(['prefix' => 'business-approvals', 'middleware' => ['schema.ready:rbac', 'schema.ready:business_approvals']], function () {
                Route::get('/', [BusinessApprovalController::class, 'index'])->middleware('permission:approval.view');
                Route::post('/order/{id}', [BusinessApprovalController::class, 'submitOrder'])->middleware('permission:approval.view');
                Route::post('/lease/{id}', [BusinessApprovalController::class, 'submitLease'])->middleware('permission:approval.view');
                Route::post('/{id}/decide', [BusinessApprovalController::class, 'decide'])->middleware('permission:approval.decide');
                Route::post('/{id}/settle-cancellation', [BusinessApprovalController::class, 'settleCancellation'])->middleware('permission:order.cancel_settle');
            });

            Route::group(['prefix' => 'lease-contracts', 'middleware' => ['schema.ready:lease', 'schema.ready:audit', 'schema.ready:rbac']], function () {
                Route::get('/', [LeaseContractController::class, 'index'])->middleware('permission:lease.view');
                Route::get('/stats', [LeaseContractController::class, 'stats'])->middleware('permission:lease.view');
                Route::get('/export', [LeaseContractController::class, 'export'])->middleware('permission:lease.export');
                Route::get('/{id}', [LeaseContractController::class, 'show'])->middleware('permission:lease.view');
                Route::get('/{id}/pdf', [LeaseContractController::class, 'pdf'])->middleware(['schema.ready:lease_document', 'permission:lease.view']);
                Route::get('/{id}/handover', [LeaseContractController::class, 'handover'])->middleware('permission:lease.view');
                Route::get('/{id}/annex', [LeaseContractController::class, 'annex'])->middleware('permission:lease.view');
                Route::get('/{id}/debt-statement.pdf', [LeaseContractController::class, 'debtStatementPdf'])->middleware(['schema.ready:lease_document', 'permission:lease.view']);
                Route::post('/', [LeaseContractController::class, 'store'])->middleware(['schema.ready:lease_document', 'permission:lease.create_draft']);
                Route::post('/{id}/approve', [LeaseContractController::class, 'approve'])->middleware('permission:lease.approve');
                Route::post('/{id}/payments', [LeaseContractController::class, 'allocatePayment'])->middleware('permission:lease.collect_initial');
                Route::post('/{id}/settle', [LeaseContractController::class, 'settle'])->middleware('permission:lease.collect');
                Route::post('/reverse-allocation/{allocationId}', [LeaseContractController::class, 'reverse'])->middleware('permission:lease.reverse_payment');
                Route::post('/{id}/notes', [LeaseContractController::class, 'addNote'])->middleware('permission:lease.note');
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
                Route::get('/summary', [DailyCashRegisterController::class, 'summary'])->middleware('permission:cash_register.view');
                Route::get('/transactions', [DailyCashRegisterController::class, 'transactions'])->middleware('permission:cash_register.view');
                Route::get('/sources', [DailyCashRegisterController::class, 'sources'])->middleware('permission:cash_register.view');
                Route::post('/entries', [DailyCashRegisterController::class, 'storeEntry'])->middleware('permission:cash_register.manage');
                Route::post('/exchanges', [DailyCashRegisterController::class, 'storeExchange'])->middleware('permission:cash_register.manage');
                Route::post('/submit', [DailyCashRegisterController::class, 'submit'])->middleware('permission:cash_register.submit');
                Route::post('/close', [DailyCashRegisterController::class, 'close'])->middleware('permission:cash_register.approve');
                Route::post('/reopen', [DailyCashRegisterController::class, 'reopen'])->middleware('admin');
                Route::get('/history', [DailyCashRegisterController::class, 'history'])->middleware('permission:cash_register.view');
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
                Route::post('/{id}/contact', [CustomerReminderController::class, 'contact'])->middleware('permission:reminder.view');
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
 
