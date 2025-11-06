<?php

namespace App\Services;

use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferService
{
  public function __construct(private LedgerWriter $ledger) {}

  /**
   * HQ dispatch → TRANSFER_OUT at source branch
   */
  public function dispatch(Transfer $transfer): Transfer
  {
    if (!in_array($transfer->status, ['DRAFT'])) {
      // allow re-dispatch only from DRAFT for this simple flow
      return $transfer;
    }

    DB::transaction(function () use ($transfer) {
      foreach ($transfer->items as $line) {
        $this->ledger->post([
          'product_id'  => $line->product_id,
          'branch_id'   => $transfer->from_branch_id,
          'qty'         => $line->qty,
          'movement'    => 'TRANSFER_OUT',
          'source_type' => 'transfers',
          'source_id'   => $transfer->id,
          'source_line' => $line->id,
        ]);
      }

      $transfer->update([
        'status'        => 'DISPATCHED',
        'dispatched_at' => now(),
      ]);
    });

    return $transfer->fresh();
  }

  /**
   * Destination receive → TRANSFER_IN at destination branch
   */
  public function receive(Transfer $transfer): Transfer
  {
    if ($transfer->status !== 'DISPATCHED') {
      return $transfer;
    }

    DB::transaction(function () use ($transfer) {
      foreach ($transfer->items as $line) {
        $this->ledger->post([
          'product_id'  => $line->product_id,
          'branch_id'   => $transfer->to_branch_id,
          'qty'         => $line->qty,
          'movement'    => 'TRANSFER_IN',
          'source_type' => 'transfers',
          'source_id'   => $transfer->id,
          'source_line' => $line->id,
        ]);
      }

      $transfer->update([
        'status'      => 'RECEIVED',
        'received_at' => now(),
      ]);
    });

    return $transfer->fresh();
  }
}
