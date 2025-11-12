<?php

namespace Tests\Feature\Governance;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{SalesOrder, SalesOrderItem};
use App\Services\{SalesService, LedgerWriter};
use DomainException;

class BranchDeactivationBlocksDeliveryTest extends TestCase
{
  public function test_cannot_deliver_from_inactive_branch()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    // seed stock (works with or without unit_id column)
    $attrs = ['branch_id' => $branch->id, 'product_id' => $product->id];
    if (Schema::hasColumn('stock_levels', 'unit_id')) $attrs['unit_id'] = $unitId;
    DB::table('stock_levels')->updateOrInsert($attrs, [
      // support either qty or on_hand schema
      ...(Schema::hasColumn('stock_levels', 'on_hand') ? ['on_hand' => 50] : ['qty' => 50]),
      ...(Schema::hasColumn('stock_levels', 'reserved') ? ['reserved' => 0] : []),
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $order = SalesOrder::create([
      'branch_id' => $branch->id,
      'customer_name' => 'Lucky Mart',
      'status' => 'DRAFT',
      'requires_prepayment' => true,
      'currency' => 'USD',
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

    // deactivate branch (supports either is_active or status column)
    if (Schema::hasColumn('branches', 'is_active')) {
      DB::table('branches')->where('id', $branch->id)->update(['is_active' => 0, 'updated_at' => now()]);
    } elseif (Schema::hasColumn('branches', 'status')) {
      DB::table('branches')->where('id', $branch->id)->update(['status' => 'INACTIVE', 'updated_at' => now()]);
    }

    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('Branch is inactive.');
    $service->deliver($order->fresh(), userId: null);
  }
}
