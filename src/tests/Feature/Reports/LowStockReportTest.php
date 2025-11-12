<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LowStockReportTest extends TestCase
{
  private function upsertStock(int $branchId, int $productId, float $qty, ?int $unitId = null): void
  {
    $attrs = ['branch_id' => $branchId, 'product_id' => $productId];
    if (Schema::hasColumn('stock_levels', 'unit_id') && $unitId) {
      $attrs['unit_id'] = $unitId;
    }

    $values = ['created_at' => now(), 'updated_at' => now()];
    if (Schema::hasColumn('stock_levels', 'on_hand')) {
      $values['on_hand'] = $qty;
    } else {
      $values['qty'] = $qty;
    }
    if (Schema::hasColumn('stock_levels', 'reserved')) {
      $values['reserved'] = 0;
    }

    DB::table('stock_levels')->updateOrInsert($attrs, $values);
  }

  public function test_low_stock_query_selects_only_below_threshold()
  {
    $branch = $this->makeBranch('Phnom Penh', 'PP');
    $pLow   = $this->makeProduct('Shampoo');
    $pOk    = $this->makeProduct('Soap');
    $unitId = $this->ensureBaseUnitId();

    $this->upsertStock($branch->id, $pLow->id, 4,  $unitId);
    $this->upsertStock($branch->id, $pOk->id,  12, $unitId);

    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand' : 'qty';

    $rows = DB::table('stock_levels as sl')
      ->join('products as p', 'p.id', '=', 'sl.product_id')
      ->selectRaw("p.name as product, sl.$qtyCol as on_hand")
      ->where('sl.branch_id', $branch->id)
      ->whereRaw("sl.$qtyCol < ?", [5])
      ->orderBy('on_hand')
      ->get();

    $names = $rows->pluck('product')->all();
    $this->assertEquals(['Shampoo'], $names);
  }
}
