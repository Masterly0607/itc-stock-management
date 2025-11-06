<?php

namespace Tests\Feature\Sales;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\{SalesOrder, SalesOrderItem};
use App\Services\{SalesService, LedgerWriter};
use DomainException;
use Illuminate\Support\Facades\DB;

class PayBeforeDeliverTest extends TestCase
{
  public function test_block_deliver_when_unpaid()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty'
        : (Schema::hasColumn('stock_levels', 'quantity') ? 'quantity' : null));
    $reservedCol = Schema::hasColumn('stock_levels', 'reserved') ? 'reserved' : null;

    $attrs = [
      'branch_id'  => $branch->id,
      'product_id' => $product->id,
    ];
    if (Schema::hasColumn('stock_levels', 'unit_id')) {
      $attrs['unit_id'] = $unitId;
    }

    $values = [$qtyCol => 100, 'created_at' => now(), 'updated_at' => now()];
    if ($reservedCol) $values[$reservedCol] = 0;
    DB::table('stock_levels')->updateOrInsert($attrs, $values);

    $order = SalesOrder::create([
      'branch_id'          => $branch->id,
      'customer_name'      => 'Lucky Mart',
      'status'             => 'DRAFT',
      'requires_prepayment' => true,
      'currency'           => 'USD',
    ]);

    SalesOrderItem::create([
      'sales_order_id' => $order->id,
      'product_id'     => $product->id,
      'unit_id'        => $unitId,
      'qty'            => 10,
      'unit_price'     => 3.00,
      'line_total'     => 30.00,
    ]);

    $service = new SalesService(app(LedgerWriter::class));

    $service->confirm($order->fresh());
    $service->addPayment($order->fresh(), 20.00, 'CASH'); // partial

    $this->expectException(DomainException::class);
    $service->deliver($order->fresh());
  }
}
