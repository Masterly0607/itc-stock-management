<?php

namespace Tests\Feature\StockRequestTransfer;

use App\Models\{StockLevel, StockRequest, StockRequestItem, Transfer};
use App\Services\StockRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApproveCreatesTransferTest extends TestCase
{
  use RefreshDatabase;

  public function test_approve_creates_transfer_with_approved_qty(): void
  {
    $hq = $this->makeBranch('HQ', 'HQ', 'HQ4');
    $pp = $this->makeBranch('Phnom Penh', 'PP', 'PP4');
    $product = $this->makeProduct('Shampoo');

    StockLevel::updateOrCreate(['branch_id' => $hq->id, 'product_id' => $product->id], ['qty' => 0]);
    StockLevel::updateOrCreate(['branch_id' => $pp->id, 'product_id' => $product->id], ['qty' => 0]);

    $req = StockRequest::create([
      'request_branch_id' => $pp->id,
      'supply_branch_id'  => $hq->id,
      'status'            => 'DRAFT',
      'ref_no'            => 'SR-001',
    ]);

    $unitId = $this->ensureBaseUnitId();

    $line = StockRequestItem::create([
      'stock_request_id' => $req->id,
      'product_id'       => $product->id,
      'unit_id'          => $unitId,
      'qty_requested'    => 40,
    ]);

    $svc = app(StockRequestService::class);
    $svc->submit($req);
    $transfer = $svc->approveAndCreateTransfer($req->fresh(), [$line->id => 35]);

    $this->assertEquals('APPROVED', $req->fresh()->status);
    $this->assertInstanceOf(Transfer::class, $transfer);
    $this->assertCount(1, $transfer->items);
    $this->assertSame(35.0, (float) $transfer->items->first()->qty);
    $this->assertEquals($hq->id, $transfer->from_branch_id);
    $this->assertEquals($pp->id, $transfer->to_branch_id);
  }
}
