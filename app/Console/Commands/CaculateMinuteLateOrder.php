<?php

namespace App\Console\Commands;

use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;
use App\Models\Order;
use App\Models\Transaction;
use App\Validators\OrderValidator;
use Illuminate\Console\Command;

class CaculateMinuteLateOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feature:calculate-minute-late-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tính số phút muộn của order';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        info('Quét đơn hàng quá hạn');
        // Chỉ lấy các đơn đang thuê, hoàn thành rồi thì thôi
        $orders = Order::query()->where('order_status', OrderValidator::ORDER_RENTING);
        $total = (clone($orders))->get()->count();
        $this->output->progressStart($total / 500);
        $orders->chunkById(500, function ($items) {
            foreach ($items as $order) {
                CarRentalHelper::writeMoneyOutDateAndTotal($order);
            }
            $this->output->progressAdvance();
        }, 'id');
        dump('done orders table');
    }

  

     
}
