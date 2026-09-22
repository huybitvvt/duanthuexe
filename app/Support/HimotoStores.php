<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The Himoto warehouse catalogue is deliberately fixed.  Keeping this list
 * in one place prevents the warehouse dashboard, contract form and lease-
 * to-own flow from silently drifting apart when an old store row remains in
 * the database.
 */
final class HimotoStores
{
    public const CS1 = 'CS1';
    public const CS2 = 'CS2';
    public const CS3 = 'CS3';
    public const CS4 = 'CS4';
    public const CS5 = 'CS5';
    public const CS6 = 'CS6';

    /** @var array<string,array{code:string,store_name:string,store_address:string,kind:string}> */
    public const DEFINITIONS = [
        self::CS1 => [
            'code' => self::CS1,
            'store_name' => 'CS 1',
            'store_address' => '264 đường Láng, Đống Đa',
            'kind' => Store::KIND_PHYSICAL,
        ],
        self::CS2 => [
            'code' => self::CS2,
            'store_name' => 'CS 2',
            'store_address' => 'Số 30, ngõ 66 Nguyễn Hoàng',
            'kind' => Store::KIND_PHYSICAL,
        ],
        self::CS3 => [
            'code' => self::CS3,
            'store_name' => 'CS 3',
            'store_address' => '02 Hàng Bút, Hoàn Kiếm',
            'kind' => Store::KIND_PHYSICAL,
        ],
        self::CS4 => [
            'code' => self::CS4,
            'store_name' => 'CS 4',
            'store_address' => 'Số 33, ngõ 286 Giáp Bát',
            'kind' => Store::KIND_PHYSICAL,
        ],
        self::CS5 => [
            'code' => self::CS5,
            'store_name' => 'CS 5',
            'store_address' => '476 Quang Trung, Hà Đông',
            'kind' => Store::KIND_PHYSICAL,
        ],
        self::CS6 => [
            'code' => self::CS6,
            'store_name' => 'Kho sở hữu',
            'store_address' => 'Kho sở hữu',
            'kind' => Store::KIND_LEASE_TO_OWN,
        ],
    ];

    public static function codes(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function definition(string $code): array
    {
        return self::DEFINITIONS[$code] ?? [];
    }

    /**
     * Apply the fixed catalogue to a Store query.  The column guard keeps
     * older installations bootable until the catalogue migration has run.
     */
    public static function scope(Builder $query): Builder
    {
        if (Schema::hasColumn('stores', 'code')) {
            return $query->whereIn('code', self::codes())->where(function (Builder $active) {
                $active->whereNull('status')->orWhere('status', '!=', 'inactive');
            });
        }

        return $query->whereIn('store_name', array_column(self::DEFINITIONS, 'store_name'));
    }

    public static function query(): Builder
    {
        return self::scope(Store::query());
    }

    public static function isCanonical(?Store $store): bool
    {
        if (!$store) {
            return false;
        }

        if ($store->status === 'inactive') {
            return false;
        }

        if (Schema::hasColumn('stores', 'code')) {
            return in_array($store->code, self::codes(), true);
        }

        return in_array($store->store_name, array_column(self::DEFINITIONS, 'store_name'), true);
    }
}
