<?php

namespace App\Services;

use App\Models\Adjustment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdjustmentService
{
  public function __construct(protected LedgerWriter $ledger) {}

  public function post(Adjustment $adjustment, ?int $userId = null): Adjustment
  {
    if (($adjustment->status ?? 'DRAFT') !== 'DRAFT') {
      throw new DomainException('Only DRAFT adjustments can be posted.');
    }
    if ($adjustment->posted_at) {
      throw new DomainException('Adjustment already posted.');
    }

    $items = $adjustment->items()->get();
    if ($items->isEmpty()) {
      throw new DomainException('Adjustment has no items.');
    }

    // quantity column on stock_levels
    $qtyCol = Schema::hasColumn('stock_levels', 'qty') ? 'qty'
      : (Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand' : null);
    if (!$qtyCol) {
      throw new DomainException('stock_levels has no qty/on_hand column.');
    }

    // Pre-check negatives for OUT lines
    foreach ($items as $it) {
      $delta = (float) $it->qty_delta;
      if ($delta < 0) {
        $where = [
          'branch_id'  => $adjustment->branch_id,
          'product_id' => $it->product_id,
        ];
        if (Schema::hasColumn('stock_levels', 'unit_id') && $it->unit_id) {
          $where['unit_id'] = $it->unit_id;
        }
        $available = (float) (DB::table('stock_levels')->where($where)->value($qtyCol) ?? 0);
        if ($available + $delta < 0) {
          throw new DomainException("Insufficient stock for product {$it->product_id} on adjust-out.");
        }
      }
    }

    DB::transaction(function () use ($adjustment, $items, $userId) {
      foreach ($items as $it) {
        $delta = (float) $it->qty_delta;
        if ($delta == 0.0) {
          continue;
        }

        $payload = [
          'product_id'  => $it->product_id,
          'branch_id'   => $adjustment->branch_id,
          'qty'         => abs($delta),
          'movement'    => $delta >= 0 ? 'ADJ_IN' : 'ADJ_OUT',
          'source_type' => 'adjustments',
          'source_id'   => $adjustment->id,
          'source_line' => $it->id ?? 0,
        ];

        // ✅ ALWAYS pass the unit that user selected
        if ($it->unit_id) {
          $payload['unit_id'] = $it->unit_id;
        }

        $this->ledger->post($payload);
      }

      $adjustment->update([
        'status'      => 'POSTED',
        'posted_at'   => now(),
        'approved_by' => $userId,
      ]);
    });

    return $adjustment->fresh();
  }
}
