<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{SalesOrder, SalesOrderItem};
use App\Services\{SalesService, LedgerWriter};

class AuditOnSalesDeliveryTest extends TestCase
{
  /** Seed stock_levels using your actual columns (on_hand or qty; reserved if exists) */
  private function seedStock(int $branchId, int $productId, float $qty, ?int $unitId = null): array
  {
    $attrs = ['branch_id' => $branchId, 'product_id' => $productId];
    if (Schema::hasColumn('stock_levels', 'unit_id') && $unitId) {
      $attrs['unit_id'] = $unitId;
    }

    $values = ['created_at' => now(), 'updated_at' => now()];
    if (Schema::hasColumn('stock_levels', 'on_hand')) {
      $values['on_hand'] = $qty;
    } else {
      $values['qty'] = $qty;
    }
    if (Schema::hasColumn('stock_levels', 'reserved')) {
      $values['reserved'] = 0;
    }

    DB::table('stock_levels')->updateOrInsert($attrs, $values);
    return $attrs;
  }

  /** Create a minimal user row that satisfies any NOT NULL columns, return its id */
  private function createUser(int $branchId): int
  {
    $payload = [
      'name'       => 'Test User',
      'email'      => 'u' . uniqid() . '@example.com',
      'password'   => bcrypt('secret'),
      'created_at' => now(),
      'updated_at' => now(),
    ];
    if (Schema::hasColumn('users', 'branch_id')) {
      $payload['branch_id'] = $branchId;
    }
    if (Schema::hasColumn('users', 'status')) {
      $payload['status'] = 'ACTIVE';
    }
    if (Schema::hasColumn('users', 'email_verified_at')) {
      $payload['email_verified_at'] = now();
    }

    return (int) DB::table('users')->insertGetId($payload);
  }

  public function test_audit_log_is_written_after_delivery()
  {
    $branch  = $this->makeBranch('Phnom Penh', 'PP');
    $product = $this->makeProduct('Shampoo');
    $unitId  = $this->ensureBaseUnitId();

    // User for posted_by FK
    $userId = $this->createUser($branch->id);

    // Seed 100 in stock
    $stockKey = $this->seedStock($branch->id, $product->id, 100, $unitId);

    // Build order
    $order = SalesOrder::create([
      'branch_id'           => $branch->id,
      'customer_name'       => 'Demo Customer',
      'status'              => 'DRAFT',
      'requires_prepayment' => true,
      'currency'            => 'USD',
    ]);

    SalesOrderItem::create([
      'sales_order_id' => $order->id,
      'product_id'     => $product->id,
      'unit_id'        => $unitId,
      'qty'            => 5,
      'unit_price'     => 3.00,
      'line_total'     => 15.00,
    ]);

    $svc = new SalesService(app(LedgerWriter::class));
    $svc->confirm($order = $order->fresh());
    $svc->addPayment($order, 15.00, 'CASH');
    $svc->deliver($order->fresh(), userId: $userId);

    // Assert audit row exists
    $exists = DB::table('audit_logs')->where([
      'action'      => 'sales.delivered',
      'entity_type' => 'sales_orders',
      'entity_id'   => $order->id,
    ])->exists();
    $this->assertTrue($exists, 'Expected an audit_logs row for sales.delivered');

    // And stock reduced to 95
    $row    = DB::table('stock_levels')->where($stockKey)->first();
    $onHand = isset($row->on_hand) ? (float) $row->on_hand : (float) $row->qty;
    $this->assertEquals(95.0, $onHand);
  }
}
