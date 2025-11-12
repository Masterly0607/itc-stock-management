<?php
// tests/Feature/StockControls/PreventNegativeOnAdjustOutTest.php

namespace Tests\Feature\StockControls;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\{AdjustmentService, LedgerWriter};
use DomainException;

class PreventNegativeOnAdjustOutTest extends TestCase
{
  public function test_adjust_out_blocks_when_would_go_negative()
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
      $qtyCol      => 2,
      'created_at' => now(),
      'updated_at' => now(),
    ];
    if (Schema::hasColumn('stock_levels', 'reserved')) {
      $payload['reserved'] = 0;
    }

    DB::table('stock_levels')->updateOrInsert($where, $payload);

    $adjId = DB::table('adjustments')->insertGetId([
      'branch_id'  => $branch->id,
      'reason'     => 'MANUAL',
      'status'     => 'DRAFT',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    DB::table('adjustment_items')->insert([
      'adjustment_id' => $adjId,
      'product_id'    => $product->id,
      'unit_id'       => $unitId,
      'qty_delta'     => -3,
      'created_at'    => now(),
      'updated_at'    => now(),
    ]);

    $svc = new AdjustmentService(app(LedgerWriter::class));

    $this->expectException(DomainException::class);
    $svc->post(\App\Models\Adjustment::findOrFail($adjId));
  }
}
