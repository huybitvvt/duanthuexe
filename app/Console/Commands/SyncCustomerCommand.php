<?php

namespace App\Console\Commands;

use App\Entities\Customer;
use App\Models\Order;
use Illuminate\Console\Command;

class SyncCustomerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:sync-customer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $orders = Order::all();
        foreach ($orders as $order) {
            $id_card = data_get($order, 'customer_idnumber', '');
            $customer = Customer::where('id_card', $id_card)->first();
            if ($customer) {
                continue;
            }
            $data = [
                'name' => data_get($order, 'customer_name', ''),
                'email' => data_get($order, 'customer_email', ''),
                'phone' => data_get($order, 'customer_phone', ''),
                'address' => data_get($order, 'customer_address', ''),
                'id_card' => $id_card,
                'status' => Customer::STATUS_ACTIVE,
            ];
            Customer::create($data);
        }
        echo "Sync customer success!";
    }

}
