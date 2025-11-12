<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LedgerWriter
{
  /**
   * Required: product_id, branch_id, qty (>0), movement, source_type, source_id
   * Optional: source_line, unit_id, posted_at, posted_by
   */
  public function post(array $payload): InventoryLedger
  {
    $payload['posted_at']   = $payload['posted_at']   ?? now();
    $payload['source_line'] = $payload['source_line'] ?? 0;
    $payload['posted_by']   = $payload['posted_by']   ?? optional(Auth::user())->id;

    return DB::transaction(function () use ($payload) {
      // 0) Normalise
      $movement = strtoupper((string)($payload['movement'] ?? ''));
      $qty      = (float) ($payload['qty'] ?? 0);
      if ($qty <= 0) {
        throw ValidationException::withMessages(['qty' => 'Quantity must be greater than 0.']);
      }
      $isOut = str_contains($movement, 'OUT');

      // 1) Idempotency
      $exists = InventoryLedger::query()->where([
        'source_type' => $payload['source_type'],
        'source_id'   => $payload['source_id'],
        'source_line' => $payload['source_line'],
        'branch_id'   => $payload['branch_id'],
        'product_id'  => $payload['product_id'],
        'movement'    => $movement,
      ])->exists();
      if ($exists) {
        throw ValidationException::withMessages(['posting' => 'This movement was already posted.']);
      }

      // 2) Locate stock_levels row (unit-aware) — reuse existing row first
      $useUnits = Schema::hasColumn('stock_levels', 'unit_id');
      $unitId   = $useUnits ? ($payload['unit_id'] ?? null) : null;

      $base = StockLevel::query()
        ->where('branch_id', $payload['branch_id'])
        ->where('product_id', $payload['product_id']);

      if ($useUnits) {
        // Try exact match on provided unit (including NULL)
        $exact = (clone $base);
        $exact = $unitId === null ? $exact->whereNull('unit_id') : $exact->where('unit_id', $unitId);
        $level = $exact->lockForUpdate()->first();

        if (! $level) {
          // Prefer existing NULL/base row to keep a single row per product+branch
          $nullRow = (clone $base)->whereNull('unit_id')->lockForUpdate()->first();
          if ($nullRow) {
            $level  = $nullRow;
            $unitId = $level->unit_id; // null
          } else {
            // Else reuse any existing row for this product+branch
            $any = (clone $base)->lockForUpdate()->first();
            if ($any) {
              $level  = $any;
              $unitId = $level->unit_id;
            }
          }

          // If nothing exists yet, create a new row with requested unit
          if (! $level) {
            $level = new StockLevel([
              'branch_id'  => $payload['branch_id'],
              'product_id' => $payload['product_id'],
              'qty'        => 0,
              'unit_id'    => $unitId, // may be null
            ]);
            $level->save();
          }
        }
      } else {
        $level = $base->lockForUpdate()->first();
        if (! $level) {
          $level = new StockLevel([
            'branch_id'  => $payload['branch_id'],
            'product_id' => $payload['product_id'],
            'qty'        => 0,
          ]);
          $level->save();
        }
      }

      // 3) Compute balance & prevent negatives
      $newBalance = $isOut ? $level->qty - $qty : $level->qty + $qty;
      if ($newBalance < 0) {
        throw ValidationException::withMessages(['stock' => 'Insufficient stock for this operation.']);
      }

      // 4) Write ledger (unit_id follows the row we actually updated)
      $ledger = new InventoryLedger([
        'product_id'    => $payload['product_id'],
        'branch_id'     => $payload['branch_id'],
        'movement'      => $movement,
        'qty'           => $qty,
        'balance_after' => $newBalance,
        'source_type'   => $payload['source_type'],
        'source_id'     => $payload['source_id'],
        'source_line'   => $payload['source_line'],
        'posted_at'     => $payload['posted_at'],
        'posted_by'     => $payload['posted_by'],
        'hash'          => $this->hash($payload, $newBalance),
      ]);
      if ($useUnits) {
        $ledger->unit_id = $level->unit_id;
      }
      $ledger->save();

      // 5) Update snapshot
      $level->qty = $newBalance;
      $level->save();

      return $ledger;
    });
  }

  protected function hash(array $payload, float $balance): string
  {
    return hash('sha256', implode('|', [
      $payload['product_id'],
      $payload['branch_id'],
      strtoupper((string)($payload['movement'] ?? '')),
      (string)($payload['qty'] ?? ''),
      $payload['source_type'] ?? '',
      $payload['source_id'] ?? '',
      $payload['source_line'] ?? 0,
      (string)($payload['posted_at'] ?? ''),
      (string)$balance,
      (string)($payload['unit_id'] ?? 'null'),
    ]));
  }

  public function postIn(array $base): InventoryLedger
  {
    $base['movement'] ??= 'IN';
    return $this->post($base);
  }

  public function postOut(array $base): InventoryLedger
  {
    $base['movement'] ??= 'OUT';
    return $this->post($base);
  }
}
