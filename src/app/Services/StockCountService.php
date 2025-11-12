<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockCountService
{
  public function __construct(
    protected AdjustmentService $adjustments,
  ) {}

  /**
   * Post a stock count:
   * - Reads stock_count_items (product_id, unit_id, counted qty)
   * - Compares to current stock_levels (qty/on_hand)
   * - Creates & posts an Adjustment for any non-zero deltas
   * - Marks stock_counts as POSTED and links adjustment_id
   */
  public function post(int $stockCountId, ?int $userId = null): array
  {
    $userId ??= (Auth::check() ? Auth::id() : null);

    // Determine stock_levels quantity column.
    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty' : null);
    if (!$qtyCol) {
      throw new \DomainException('stock_levels needs an on_hand or qty column.');
    }

    // Load the header.
    $count = DB::table('stock_counts')->where('id', $stockCountId)->first();
    if (!$count) {
      throw new \DomainException('Stock count not found.');
    }
    if (($count->status ?? 'DRAFT') !== 'DRAFT') {
      return ['posted' => false, 'adjustment_id' => $count->adjustment_id ?? null, 'message' => 'Already posted'];
    }

    // Figure out which column your items table uses for the counted quantity.
    // We support: qty_counted (preferred), counted_qty, or qty.
    $countedCol = null;
    foreach (['qty_counted', 'counted_qty', 'qty'] as $candidate) {
      if (Schema::hasColumn('stock_count_items', $candidate)) {
        $countedCol = $candidate;
        break;
      }
    }
    if (!$countedCol) {
      throw new \DomainException('stock_count_items needs a qty_counted / counted_qty / qty column.');
    }

    // Load lines and alias to counted_qty so the rest of the code is simple.
    $lines = DB::table('stock_count_items')
      ->where('stock_count_id', $stockCountId)
      ->select('product_id', 'unit_id', DB::raw("$countedCol as counted_qty"))
      ->get();

    if ($lines->isEmpty()) {
      return ['posted' => false, 'adjustment_id' => null, 'message' => 'No items to post'];
    }

    // Compute deltas (counted - system).
    $deltas = [];
    foreach ($lines as $it) {
      $where = ['branch_id' => $count->branch_id, 'product_id' => $it->product_id];
      if (Schema::hasColumn('stock_levels', 'unit_id') && !empty($it->unit_id)) {
        $where['unit_id'] = $it->unit_id;
      }
      $system = (float) (DB::table('stock_levels')->where($where)->value($qtyCol) ?? 0);
      $delta  = (float) $it->counted_qty - $system;
      if (abs($delta) > 0.0001) {
        $deltas[] = ['item' => $it, 'delta' => $delta];
      }
    }

    // If no variance, still mark the count as POSTED (closed) and stop.
    if (empty($deltas)) {
      DB::table('stock_counts')->where('id', $stockCountId)->update([
        'status'     => 'POSTED',
        'posted_at'  => now(),
        'posted_by'  => $userId,
        'updated_at' => now(),
      ]);
      return ['posted' => false, 'adjustment_id' => null, 'message' => 'No variance'];
    }

    // Create the adjustment (DRAFT).
    $adjId = DB::table('adjustments')->insertGetId([
      'branch_id'  => $count->branch_id,
      'reason'     => 'COUNT',
      'status'     => 'DRAFT',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    foreach ($deltas as $row) {
      DB::table('adjustment_items')->insert([
        'adjustment_id' => $adjId,
        'product_id'    => $row['item']->product_id,
        'unit_id'       => $row['item']->unit_id,
        'qty_delta'     => $row['delta'],
        'created_at'    => now(),
        'updated_at'    => now(),
      ]);
    }

    // Post the adjustment (updates ledger + stock).
    $this->adjustments->post(\App\Models\Adjustment::findOrFail($adjId), $userId);

    // Mark the stock count as POSTED and link the adjustment.
    DB::table('stock_counts')->where('id', $stockCountId)->update([
      'status'        => 'POSTED',
      'posted_at'     => now(),
      'posted_by'     => $userId,
      'adjustment_id' => $adjId,
      'updated_at'    => now(),
    ]);

    return ['posted' => true, 'adjustment_id' => $adjId];
  }
}
