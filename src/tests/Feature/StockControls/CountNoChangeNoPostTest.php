<?php
// tests/Feature/StockControls/CountNoChangeNoPostTest.php

namespace Tests\Feature\StockControls;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\{StockCountService, AdjustmentService, LedgerWriter};

class CountNoChangeNoPostTest extends TestCase
{
  public function test_count_equal_to_system_posts_nothing()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty' : 'quantity');

    $where = ['branch_id' => $branch->id, 'product_id' => $product->id];
    if (Schema::hasColumn('stock_levels', 'unit_id')) {
      $where['unit_id'] = $unitId;
    }

    $payload = [
      $qtyCol     => 50,
      'created_at' => now(),
      'updated_at' => now(),
    ];
    if (Schema::hasColumn('stock_levels', 'reserved')) {
      $payload['reserved'] = 0;
    }

    DB::table('stock_levels')->updateOrInsert($where, $payload);

    $svc = new StockCountService(new AdjustmentService(app(LedgerWriter::class)));
    $res = $svc->countAndAdjust($branch->id, [[
      'product_id'  => $product->id,
      'unit_id'     => $unitId,
      'counted_qty' => 50,
    ]]);

    $this->assertFalse($res['posted']);

    $ledger = DB::table('inventory_ledger')->where([
      'branch_id'  => $branch->id,
      'product_id' => $product->id,
    ])->count();

    $this->assertEquals(0, $ledger);
  }
}
