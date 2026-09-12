<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class UpdateTransactionTableData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_table:transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update data of transactions table';

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
        $transactionsAll = Transaction::query();
        $total = (clone($transactionsAll))->get()->count();
        $this->output->progressStart($total / 500);
        $transactionsAll->chunkById(500, function ($transactions) {
            foreach ($transactions as $transaction) {
                $order = $transaction->order;
                $transaction->update([
                    'order_id' => (int)filter_var($transaction->name, FILTER_SANITIZE_NUMBER_INT),
                    'store_id' => $order ? $order->store_id : 0
                ]);
            }
            $this->output->progressAdvance();
        }, 'id');
        dump('done transactions table');
    }
}
