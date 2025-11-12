<?php
// tests/Feature/StockControls/StockCountCreatesAdjustmentTest.php

namespace Tests\Feature\StockControls;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\{StockCountService, AdjustmentService, LedgerWriter};

class StockCountCreatesAdjustmentTest extends TestCase
{
  public function test_count_95_vs_system_100_creates_adj_out_5()
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
      $qtyCol      => 100,
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
      'counted_qty' => 95,
    ]]);

    $this->assertTrue($res['posted']);

    $after = (float) DB::table('stock_levels')->where($where)->value($qtyCol);
    $this->assertEquals(95.0, $after);

    $hasAdjOut = DB::table('inventory_ledger')->where([
      'branch_id'   => $branch->id,
      'product_id'  => $product->id,
      'movement'    => 'ADJ_OUT',
      'source_type' => 'adjustments',
      'source_id'   => $res['adjustment_id'],
    ])->count();

    $this->assertEquals(1, $hasAdjOut);
  }
}
