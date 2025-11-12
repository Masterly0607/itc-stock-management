<?php

namespace Tests\Feature\StockRequestTransfer;

use App\Models\{StockLevel, StockRequest, StockRequestItem, InventoryLedger};
use App\Services\{StockRequestService, TransferService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchReceivePostsLedgerTest extends TestCase
{
  use RefreshDatabase;

  public function test_dispatch_and_receive_update_stock_and_ledger(): void
  {
    $hq = $this->makeBranch('HQ', 'HQ', 'HQ5');
    $pp = $this->makeBranch('Phnom Penh', 'PP', 'PP5');
    $product = $this->makeProduct('Shampoo');

    StockLevel::updateOrCreate(['branch_id' => $hq->id, 'product_id' => $product->id], ['qty' => 100]);
    StockLevel::updateOrCreate(['branch_id' => $pp->id, 'product_id' => $product->id], ['qty' => 0]);

    $req = StockRequest::create([
      'request_branch_id' => $pp->id,
      'supply_branch_id'  => $hq->id,
      'status'            => 'SUBMITTED',
      'ref_no'            => 'SR-002',
    ]);

    $unitId = $this->ensureBaseUnitId();

    $line = StockRequestItem::create([
      'stock_request_id' => $req->id,
      'product_id'       => $product->id,
      'unit_id'          => $unitId,
      'qty_requested'    => 40,
    ]);

    $reqSvc = app(StockRequestService::class);
    $transfer = $reqSvc->approveAndCreateTransfer($req, [$line->id => 35]);

    $transferSvc = app(TransferService::class);
    $transferSvc->dispatch($transfer->fresh(['items']));

    // HQ decreased by 35
    $hqLevel = \App\Models\StockLevel::where(['branch_id' => $hq->id, 'product_id' => $product->id])->firstOrFail();
    $this->assertSame(65.0, (float) $hqLevel->qty);

    // Ledger TRANSFER_OUT exists
    $this->assertTrue(
      InventoryLedger::where([
        'branch_id'  => $hq->id,
        'product_id' => $product->id,
        'movement'   => 'TRANSFER_OUT',
        'source_type' => 'transfers',
        'source_id'  => $transfer->id,
      ])->exists()
    );

    // Receive → PP increased by 35 & ledger IN exists
    $transferSvc->receive($transfer->fresh(['items']));

    $ppLevel = \App\Models\StockLevel::where(['branch_id' => $pp->id, 'product_id' => $product->id])->firstOrFail();
    $this->assertSame(35.0, (float) $ppLevel->qty);

    $this->assertTrue(
      InventoryLedger::where([
        'branch_id'  => $pp->id,
        'product_id' => $product->id,
        'movement'   => 'TRANSFER_IN',
        'source_type' => 'transfers',
        'source_id'  => $transfer->id,
      ])->exists()
    );
  }
}
