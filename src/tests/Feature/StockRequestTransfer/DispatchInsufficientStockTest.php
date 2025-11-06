<?php

namespace Tests\Feature\StockRequestTransfer;

use App\Models\{StockLevel, StockRequest, StockRequestItem};
use App\Services\{StockRequestService, TransferService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DispatchInsufficientStockTest extends TestCase
{
  use RefreshDatabase;

  public function test_dispatch_blocks_when_hq_stock_not_enough(): void
  {
    $hq = $this->makeBranch('HQ', 'HQ', 'HQ6');
    $pp = $this->makeBranch('Phnom Penh', 'PP', 'PP6');
    $product = $this->makeProduct('Shampoo');

    // HQ has only 10
    StockLevel::updateOrCreate(['branch_id' => $hq->id, 'product_id' => $product->id], ['qty' => 10]);
    StockLevel::updateOrCreate(['branch_id' => $pp->id, 'product_id' => $product->id], ['qty' => 0]);

    $req = StockRequest::create([
      'request_branch_id' => $pp->id,
      'supply_branch_id'  => $hq->id,
      'status'            => 'SUBMITTED',
      'ref_no'            => 'SR-003',
    ]);

    $unitId = $this->ensureBaseUnitId();

    $line = StockRequestItem::create([
      'stock_request_id' => $req->id,
      'product_id'       => $product->id,
      'unit_id'          => $unitId,
      'qty_requested'    => 30,
    ]);

    $transfer = app(StockRequestService::class)
      ->approveAndCreateTransfer($req, [$line->id => 30]);

    $this->expectException(ValidationException::class);

    app(TransferService::class)->dispatch($transfer->fresh(['items']));
  }
}
