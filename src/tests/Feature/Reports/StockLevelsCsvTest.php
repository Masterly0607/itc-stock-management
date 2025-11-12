<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockLevelsCsvTest extends TestCase
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

  public function test_csv_export_returns_csv_with_headers()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    $this->upsertStock($branch->id, $product->id, 12, $unitId);

    // Hit the CSV route we added earlier: GET /reports/stock-levels?export=csv
    $res = $this->get('/reports/stock-levels?export=csv');

    $res->assertOk();
    $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $res->assertSee('Branch,Product', false); // CSV headers
  }
}
