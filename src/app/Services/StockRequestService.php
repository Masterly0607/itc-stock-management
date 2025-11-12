<?php

namespace App\Services;

use App\Models\{StockRequest, StockRequestItem, Transfer, TransferItem};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockRequestService
{
  /**
   * Submit a draft request.
   */
  public function submit(StockRequest $req): StockRequest
  {
    if ($req->status !== 'DRAFT') {
      throw ValidationException::withMessages(['status' => 'Only DRAFT can be submitted.']);
    }

    $req->status = 'SUBMITTED';
    $req->submitted_at = now();
    $req->submitted_by = optional(Auth::user())->id;
    $req->save();

    return $req->fresh();
  }

  /**
   * Approve a submitted request and create a Transfer with approved quantities.
   * @param array $approvedByItemId  e.g. [ stock_request_item_id => qty_approved, ... ]
   * @param int|null $supplyBranchId override supply branch (HQ). If null, uses $req->supply_branch_id
   */
  public function approveAndCreateTransfer(StockRequest $req, array $approvedByItemId, ?int $supplyBranchId = null): Transfer
  {
    if (!in_array($req->status, ['SUBMITTED', 'DRAFT'])) {
      throw ValidationException::withMessages(['status' => 'Only DRAFT or SUBMITTED can be approved.']);
    }

    return DB::transaction(function () use ($req, $approvedByItemId, $supplyBranchId) {
      // 1) Set approved qty on each item (default 0)
      $lines = $req->items()->lockForUpdate()->get();
      foreach ($lines as $line) {
        $approved = (float)($approvedByItemId[$line->id] ?? 0);
        $line->qty_approved = max(0, $approved);
        $line->save();
      }

      // 2) Mark request approved
      $req->status = 'APPROVED';
      $req->approved_at = now();
      $req->approved_by = optional(Auth::user())->id;
      $req->supply_branch_id = $supplyBranchId ?? $req->supply_branch_id;
      if (!$req->supply_branch_id) {
        throw ValidationException::withMessages(['supply_branch_id' => 'Supply branch (HQ) is required.']);
      }
      $req->save();

      // 3) Create Transfer header linked to request
      /** @var Transfer $transfer */
      $transfer = Transfer::create([
        'from_branch_id'  => $req->supply_branch_id,
        'to_branch_id'    => $req->request_branch_id,
        'status'          => 'DRAFT',
        'stock_request_id' => $req->id,
        'ref_no'          => $req->ref_no,
      ]);

      // 4) Create Transfer items with approved qty only (>0)
      foreach ($lines as $line) {
        $qty = (float)($line->qty_approved ?? 0);
        if ($qty <= 0) continue;

        TransferItem::create([
          'transfer_id' => $transfer->id,
          'product_id'  => $line->product_id,
          'unit_id'     => $line->unit_id,
          'qty'         => $qty,
        ]);
      }

      return $transfer->fresh(['items']);
    });
  }
}
