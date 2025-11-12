<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockCountService
{
  public function __construct(
    protected AdjustmentService $adjustments
  ) {}

  /**
   * items: array of rows with keys:
   *   product_id (int), unit_id (nullable), counted_qty (float)
   * Creates a DRAFT adjustment with deltas and posts it if any delta != 0.
   * Returns ['posted' => bool, 'adjustment_id' => int|null, 'deltas' => [...]]
   */
  public function countAndAdjust(int $branchId, array $items, string $reason = 'COUNT'): array
  {
    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty'
        : (Schema::hasColumn('stock_levels', 'quantity') ? 'quantity' : null));

    $deltas = [];
    foreach ($items as $it) {
      $where = ['branch_id' => $branchId, 'product_id' => $it['product_id']];
      if (Schema::hasColumn('stock_levels', 'unit_id') && !empty($it['unit_id'])) {
        $where['unit_id'] = $it['unit_id'];
      }
      $system = (float)(DB::table('stock_levels')->where($where)->value($qtyCol) ?? 0);
      $delta  = (float)$it['counted_qty'] - $system;
      $deltas[] = $delta;
    }

    if (empty(array_filter($deltas, fn($d) => abs($d) > 0.0001))) {
      return ['posted' => false, 'adjustment_id' => null, 'deltas' => $deltas];
    }

    $adjId = DB::table('adjustments')->insertGetId([
      'branch_id'  => $branchId,
      'reason'     => 'MANUAL',  // use an allowed value
      'status'     => 'DRAFT',
      'created_at' => now(),
      'updated_at' => now(),
    ]);


    foreach ($items as $idx => $it) {
      $delta = $deltas[$idx];
      if (abs($delta) < 0.0001) continue;

      DB::table('adjustment_items')->insert([
        'adjustment_id' => $adjId,
        'product_id'    => $it['product_id'],
        'unit_id'       => $it['unit_id'] ?? null,
        'qty_delta'     => $delta,
        'created_at'    => now(),
        'updated_at'    => now(),
      ]);
    }

    $this->adjustments->post(\App\Models\Adjustment::findOrFail($adjId));

    return ['posted' => true, 'adjustment_id' => $adjId, 'deltas' => $deltas];
  }
}
