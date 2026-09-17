<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureHimotoWarehouses extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('stores') || !Schema::hasColumn('stores', 'code')) {
            return;
        }

        $stores = [
            ['code' => 'LANG', 'store_name' => 'Láng', 'kind' => 'physical'],
            ['code' => 'NGUYEN_HOANG', 'store_name' => 'Nguyễn Hoàng', 'kind' => 'physical'],
            ['code' => 'QUANG_TRUNG_HA_DONG', 'store_name' => 'Quang Trung Hà Đông', 'kind' => 'physical'],
            ['code' => 'HANG_BUT', 'store_name' => 'Hàng Bút', 'kind' => 'physical'],
            ['code' => 'GIAP_BAT', 'store_name' => 'Giáp Bát', 'kind' => 'physical'],
            ['code' => 'ONLINE_OWNERSHIP', 'store_name' => 'Kho sở hữu online', 'kind' => 'lease_to_own'],
        ];

        foreach ($stores as $store) {
            $existing = DB::table('stores')->where('code', $store['code'])->first();
            if ($existing) {
                DB::table('stores')->where('id', $existing->id)->update([
                    'store_name' => $store['store_name'],
                    'kind' => $store['kind'],
                    'status' => 'opening',
                    'updated_at' => now(),
                ]);
                continue;
            }

            $sameName = DB::table('stores')->where('store_name', $store['store_name'])->first();
            if ($sameName) {
                DB::table('stores')->where('id', $sameName->id)->update([
                    'code' => $store['code'],
                    'kind' => $store['kind'],
                    'status' => 'opening',
                    'updated_at' => now(),
                ]);
                continue;
            }

            DB::table('stores')->insert(array_merge($store, [
                'status' => 'opening',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down()
    {
        // Không xóa kho nghiệp vụ vì có thể đã phát sinh xe và giao dịch.
    }
}
