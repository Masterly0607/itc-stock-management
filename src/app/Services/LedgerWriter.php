<?php

namespace App\Services;

use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LedgerWriter
{
  /**
   * Backwards-compatible helpers used in tests:
   *  - postOut(): OUT movement (reduces stock)
   *  - postIn():  IN movement (increases stock)
   */
  public function postOut(array $payload): void
  {
    $payload['direction'] = 'OUT';

    // If tests passed an explicit movement, keep it;
    // otherwise default to a generic OUT movement.
    if (! isset($payload['movement'])) {
      $payload['movement'] = 'GEN_OUT';
    }

    $this->post($payload);
  }

  public function postIn(array $payload): void
  {
    $payload['direction'] = 'IN';

    if (! isset($payload['movement'])) {
      $payload['movement'] = 'GEN_IN';
    }

    $this->post($payload);
  }

  /**
   * Core posting logic — used by all flows (sales, transfer, adjust, etc.).
   *
   * Required payload keys:
   *  - branch_id
   *  - product_id
   *  - qty          (always positive)
   *  - movement
   *  - source_type
   *  - source_id
   *
   * Optional:
   *  - source_line
   *  - unit_id
   *  - direction   ('IN' | 'OUT') — overrides movement-based sign when present
   *  - meta
   */
  public function post(array $payload): void
  {
    foreach (['branch_id', 'product_id', 'qty', 'movement', 'source_type', 'source_id'] as $key) {
      if (! array_key_exists($key, $payload)) {
        throw new \InvalidArgumentException("Ledger payload missing [$key].");
      }
    }

    $branchId   = (int) $payload['branch_id'];
    $productId  = (int) $payload['product_id'];
    $unitId     = $payload['unit_id'] ?? null;
    $movement   = strtoupper((string) $payload['movement']);
    $qty        = (float) $payload['qty'];
    $sourceLine = $payload['source_line'] ?? 0;

    if ($qty == 0.0) {
      return;
    }

    DB::transaction(function () use ($branchId, $productId, $unitId, $movement, $qty, $payload, $sourceLine) {
      // -------------------------------------------------------------
      // 0. Idempotency: same source key cannot be posted twice
      // -------------------------------------------------------------
      $exists = DB::table('inventory_ledger')
        ->where('source_type', $payload['source_type'])
        ->where('source_id', $payload['source_id'])
        ->where('source_line', $sourceLine)
        ->where('movement', $movement)   // <— important
        ->exists();

      if ($exists) {
        throw ValidationException::withMessages([
          'source' => ['Duplicate ledger posting for the same source keys.'],
        ]);
      }

      // -------------------------------------------------------------
      // 1. Determine quantity column on stock_levels
      // -------------------------------------------------------------
      $qtyCol = null;
      if (Schema::hasColumn('stock_levels', 'on_hand')) {
        $qtyCol = 'on_hand';
      } elseif (Schema::hasColumn('stock_levels', 'qty')) {
        $qtyCol = 'qty';
      }

      if (! $qtyCol) {
        throw new \DomainException('stock_levels table needs qty or on_hand column.');
      }

      // -------------------------------------------------------------
      // 2. Decide sign (IN / OUT)
      // -------------------------------------------------------------
      $direction = strtoupper((string) ($payload['direction'] ?? ''));

      if ($direction === 'OUT') {
        $sign = -1.0;
      } elseif ($direction === 'IN') {
        $sign = 1.0;
      } else {
        // Fallback: infer from movement code
        $outMovements = ['SALE_OUT', 'ADJ_OUT', 'TRANSFER_OUT', 'ISSUE_OUT', 'WRITE_OFF'];
        $sign         = in_array($movement, $outMovements, true) ? -1.0 : 1.0;
      }

      // -------------------------------------------------------------
      // 3. Convert transaction qty → base-unit qty (Option B)
      // -------------------------------------------------------------
      $baseQty = $this->convertToBaseUnit($productId, $qty, $unitId);

      // -------------------------------------------------------------
      // 4. Locate / create stock_levels row in base unit
      // -------------------------------------------------------------
      $useUnits   = Schema::hasColumn('stock_levels', 'unit_id');
      $baseUnitId = $useUnits
        ? DB::table('products')->where('id', $productId)->value('unit_id')
        : null;

      $baseQuery = StockLevel::query()
        ->where('branch_id', $branchId)
        ->where('product_id', $productId);

      if ($useUnits && $baseUnitId) {
        // Prefer row already at base unit
        $level = (clone $baseQuery)
          ->where('unit_id', $baseUnitId)
          ->lockForUpdate()
          ->first();

        if (! $level) {
          // Fallback: any existing row for this branch+product (old data / tests)
          $level = $baseQuery->lockForUpdate()->first();
        }
      } else {
        $level = $baseQuery->lockForUpdate()->first();
      }

      if (! $level) {
        // Create a new row
        $level = new StockLevel();
        $level->branch_id  = $branchId;
        $level->product_id = $productId;
        if ($useUnits) {
          $level->unit_id = $baseUnitId ?: $unitId;
        }
        $level->$qtyCol = 0;
        if (Schema::hasColumn('stock_levels', 'reserved') && $level->reserved === null) {
          $level->reserved = 0;
        }
        $level->save();
        $level->refresh();
      } else {
        // Normalise unit_id to base unit if we know it
        if ($useUnits && $baseUnitId && $level->unit_id !== $baseUnitId) {
          $level->unit_id = $baseUnitId;
          $level->save();
        }
      }

      // -------------------------------------------------------------
      // 5. Negative-stock guard (uses base-unit quantities)
      // -------------------------------------------------------------
      $currentQty = (float) ($level->$qtyCol ?? 0);
      $newQty     = $currentQty + $sign * $baseQty;

      if ($newQty < -0.0000001) {
        throw ValidationException::withMessages([
          'qty' => ['Insufficient stock for this operation.'],
        ]);
      }

      // -------------------------------------------------------------
      // 6. Insert inventory_ledger row (signed qty in base unit)
      // -------------------------------------------------------------
      $ledgerRow = [
        'branch_id'   => $branchId,
        'product_id'  => $productId,
        'qty'         => $baseQty * $sign,
        'movement'    => $movement,
        'unit_id'     => $baseUnitId ?: $unitId,
        'source_type' => $payload['source_type'],
        'source_id'   => $payload['source_id'],
        'source_line' => $sourceLine,
        'posted_at'   => now(),
      ];

      if (array_key_exists('meta', $payload)) {
        $ledgerRow['meta'] = $payload['meta'];
      }

      DB::table('inventory_ledger')->insert($ledgerRow);

      // -------------------------------------------------------------
      // 7. Finally update stock_levels
      // -------------------------------------------------------------
      $level->update([$qtyCol => $newQty]);
    });
  }

  /**
   * Convert quantity from transaction unit to product base unit.
   *
   * products.unit_id is treated as base unit.
   * Supported mappings:
   *  - product_units(product_id, unit_id, multiplier|factor|ratio|qty_per_base)
   *  - OR units.factor style conversions
   * If nothing found, assumes 1:1.
   */
  protected function convertToBaseUnit(int $productId, float $qty, ?int $fromUnitId): float
  {
    if ($qty == 0.0 || ! $fromUnitId) {
      return $qty;
    }

    $baseUnitId = DB::table('products')->where('id', $productId)->value('unit_id');
    if (! $baseUnitId || $baseUnitId === $fromUnitId) {
      return $qty;
    }

    // 1) Product-specific conversions
    if (Schema::hasTable('product_units')) {
      $row = DB::table('product_units')
        ->where('product_id', $productId)
        ->where('unit_id', $fromUnitId)
        ->first();

      if ($row) {
        foreach (['multiplier', 'factor', 'ratio', 'qty_per_base'] as $col) {
          if (isset($row->$col) && (float) $row->$col > 0) {
            return $qty * (float) $row->$col;
          }
        }
      }
    }

    // 2) Global units.factor conversion
    if (Schema::hasColumn('units', 'factor')) {
      $fromFactor = (float) (DB::table('units')->where('id', $fromUnitId)->value('factor') ?? 1);
      $baseFactor = (float) (DB::table('units')->where('id', $baseUnitId)->value('factor') ?? 1);

      if ($fromFactor > 0 && $baseFactor > 0) {
        return $qty * ($fromFactor / $baseFactor);
      }
    }

    // 3) Fallback: assume 1:1
    return $qty;
  }
}
