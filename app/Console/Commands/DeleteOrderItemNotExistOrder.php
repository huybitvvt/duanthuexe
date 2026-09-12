<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderVehicleDetail;
use Illuminate\Console\Command;

class DeleteOrderItemNotExistOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:delete-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xoa items không có order';

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
     * @return mixed
     */
    public function handle()
    {
        $items = OrderVehicleDetail::all();
        foreach ($items as $item) {
            $order = Order::find($item->order_id);
            if (!$order) {
                $item->delete();
            }
        }
        dump('done activity_logs table');
    }
}
