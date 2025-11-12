<?php

namespace App\Services;

use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferService
{
  public function __construct(private LedgerWriter $ledger) {}

  /** Source OUT */
  public function dispatch(Transfer $transfer): Transfer
  {
    if ($transfer->status !== 'DRAFT') {
      return $transfer;
    }

    DB::transaction(function () use ($transfer) {
      foreach ($transfer->items as $line) {
        $payload = [
          'product_id'  => $line->product_id,
          'branch_id'   => $transfer->from_branch_id,
          'qty'         => (float)$line->qty,
          'movement'    => 'TRANSFER_OUT',
          'source_type' => 'transfers',
          'source_id'   => $transfer->id,
          'source_line' => $line->id ?? 0,
        ];
        if (Schema::hasColumn('inventory_ledger', 'unit_id') && $line->unit_id) {
          $payload['unit_id'] = $line->unit_id;
        }
        $this->ledger->post($payload);
      }

      $transfer->update([
        'status'        => 'DISPATCHED',
        'dispatched_at' => now(),
      ]);
    });

    return $transfer->fresh();
  }

  /** Destination IN */
  public function receive(Transfer $transfer): Transfer
  {
    if ($transfer->status !== 'DISPATCHED') {
      return $transfer;
    }

    DB::transaction(function () use ($transfer) {
      foreach ($transfer->items as $line) {
        $payload = [
          'product_id'  => $line->product_id,
          'branch_id'   => $transfer->to_branch_id,
          'qty'         => (float)$line->qty,
          'movement'    => 'TRANSFER_IN',
          'source_type' => 'transfers',
          'source_id'   => $transfer->id,
          'source_line' => $line->id ?? 0,
        ];
        if (Schema::hasColumn('inventory_ledger', 'unit_id') && $line->unit_id) {
          $payload['unit_id'] = $line->unit_id;
        }
        $this->ledger->post($payload);
      }

      $transfer->update([
        'status'      => 'RECEIVED',
        'received_at' => now(),
      ]);
    });

    return $transfer->fresh();
  }
}
