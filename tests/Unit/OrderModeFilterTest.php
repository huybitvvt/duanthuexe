<?php

namespace Tests\Unit;

use App\Repositories\OrderRepositoryEloquent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderModeFilterTest extends TestCase
{
    public function testCategoryListsAreSeparateAndMasterListIncludesEveryMode(): void
    {
        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_mode')->nullable();
        });

        DB::table('orders')->insert([
            ['id' => 1, 'order_mode' => null],
            ['id' => 2, 'order_mode' => 'standard'],
            ['id' => 3, 'order_mode' => 'draft'],
            ['id' => 4, 'order_mode' => 'handover'],
        ]);

        $repository = app(OrderRepositoryEloquent::class);
        $ids = function (array $filters) use ($repository): array {
            return array_map('intval', $repository->getOrderByParams(DB::table('orders'), $filters)
                ->orderBy('orders.id')->pluck('orders.id')->all());
        };

        $this->assertSame([1, 2], $ids(['order_mode' => 'standard']));
        $this->assertSame([3], $ids(['order_mode' => 'draft']));
        $this->assertSame([4], $ids(['order_mode' => 'handover']));
        $this->assertSame([1, 2, 3, 4], $ids([]));
        $this->assertSame([1, 2, 3, 4], $ids(['order_mode' => 'unknown']));

        Schema::dropIfExists('orders');
    }
}
