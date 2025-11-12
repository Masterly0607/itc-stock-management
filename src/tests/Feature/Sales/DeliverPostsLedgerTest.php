<?php

namespace Tests\Feature\Sales;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{SalesOrder, SalesOrderItem};
use App\Services\{SalesService, LedgerWriter};

class DeliverPostsLedgerTest extends TestCase
{
  public function test_deliver_updates_stock_and_ledger()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    // pick stock cols
    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty'
        : (Schema::hasColumn('stock_levels', 'quantity') ? 'quantity' : null));
    $reservedCol = Schema::hasColumn('stock_levels', 'reserved') ? 'reserved' : null;

    // seed stock
    $attrs = [
      'branch_id'  => $branch->id,
      'product_id' => $product->id,
    ];
    if (Schema::hasColumn('stock_levels', 'unit_id')) {
      $attrs['unit_id'] = $unitId;
    }

    $values = [$qtyCol => 50, 'created_at' => now(), 'updated_at' => now()];
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
      'qty'            => 5,
      'unit_price'     => 3.00,
      'line_total'     => 15.00,
    ]);

    $service = new SalesService(app(LedgerWriter::class));

    $service->confirm($order->fresh());
    $service->addPayment($order->fresh(), 15.00, 'CASH');
    $service->deliver($order->fresh());

    // stock decreased
    $after = (float) DB::table('stock_levels')->where($attrs)->value($qtyCol);
    $this->assertEquals(45.0, $after);

    // ledger has SALE_OUT
    $count = DB::table('inventory_ledger')->where([
      'branch_id'   => $branch->id,
      'product_id'  => $product->id,
      'movement'    => 'SALE_OUT',
      'source_type' => 'sales_orders',
      'source_id'   => $order->id,
    ])->count();
    $this->assertEquals(1, $count);
  }
}
