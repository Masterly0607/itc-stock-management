<?php

namespace Tests\Feature\Governance;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{User, SalesOrder, SalesOrderItem};
use App\Services\{SalesService, LedgerWriter};
use DomainException;
use Illuminate\Support\Facades\Hash;

class InactiveUserBlocksDeliveryTest extends TestCase
{
  public function test_inactive_user_cannot_deliver()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    $attrs = ['branch_id' => $branch->id, 'product_id' => $product->id];
    if (Schema::hasColumn('stock_levels', 'unit_id')) $attrs['unit_id'] = $unitId;
    DB::table('stock_levels')->updateOrInsert($attrs, [
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

    $user = User::create([
      'name' => 'Inactive Guy',
      'email' => 'inactive@example.com',
      'password' => Hash::make('secret'),
      ...(Schema::hasColumn('users', 'branch_id') ? ['branch_id' => $branch->id] : []),
      ...(Schema::hasColumn('users', 'is_active') ? ['is_active' => 0] : []),
    ]);

    $service = new SalesService(app(LedgerWriter::class));
    $service->confirm($order->fresh());
    $service->addPayment($order->fresh(), 15.00, 'CASH');

    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('User is inactive.');
    $service->deliver($order->fresh(), $user->id);
  }
}
