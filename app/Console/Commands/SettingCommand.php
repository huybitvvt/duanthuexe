<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SettingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:setting';

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
        $data = [
            [
                'name' => 'Quản trị viên',
                'slug' => 'quan-tri-vien',
            ],
            [
                'name' => 'Quản lý cửa hàng',
                'slug' => 'quan-ly-cua-hang',
            ],
            [
                'name' => 'Nhân viên',
                'slug' => 'nhan-vien',
            ],
        ];
        \App\Entities\Role::truncate();
        \Illuminate\Support\Facades\DB::table('roles')->insert($data);
        $data_mapping = [
            'admin' => 1,
            'store_manager' => 2,
            'staff' => 3,
        ];
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            if ($user->role == 'customer') {
                $user->delete();
                continue;
            }
            $role_id = $data_mapping[$user->role];
            $user->update(['role_id' => $role_id]);
        }
    }
}
