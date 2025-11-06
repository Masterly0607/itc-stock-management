<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use DomainException;

class SalesService
{
  public function __construct(protected LedgerWriter $ledger) {}

  public function confirm(SalesOrder $order): SalesOrder
  {
    if ($order->status !== 'DRAFT') {
      throw new DomainException('Only DRAFT orders can be confirmed.');
    }

    $total = $order->items()->sum('line_total');
    $order->update([
      'total_amount' => $total,
      'status'       => 'CONFIRMED',
    ]);

    return $order->fresh();
  }

  public function addPayment(SalesOrder $order, float $amount, string $method = 'CASH')
  {
    if (!in_array($order->status, ['CONFIRMED', 'PAID'])) {
      throw new DomainException('Order must be CONFIRMED or PAID to receive payments.');
    }

    $payment = $order->payments()->create([
      'amount'      => $amount,
      'currency'    => $order->currency ?? 'USD',
      'method'      => $method,
      'received_at' => now(),
    ]);

    $paid = (float) $order->payments()->sum('amount');
    if ($paid + 0.0001 >= (float) $order->total_amount) {
      $order->update(['status' => 'PAID']);
    }

    return $payment;
  }

  public function deliver(SalesOrder $order, ?int $userId = null): SalesOrder
  {
    if ($order->status !== 'PAID') {
      throw new DomainException('Pay-before-deliver: order is not PAID.');
    }

    $lines = $order->items()->get();

    // Decide which quantity column stock_levels uses
    $qtyCol = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand'
      : (Schema::hasColumn('stock_levels', 'qty') ? 'qty'
        : (Schema::hasColumn('stock_levels', 'quantity') ? 'quantity' : null));

    if (!$qtyCol) {
      throw new DomainException('stock_levels table has no quantity column (expected on_hand/qty/quantity).');
    }

    // Check availability (works with or without stock_levels.unit_id)
    foreach ($lines as $line) {
      $where = [
        'branch_id'  => $order->branch_id,
        'product_id' => $line->product_id,
      ];

      if (Schema::hasColumn('stock_levels', 'unit_id') && !is_null($line->unit_id)) {
        $where['unit_id'] = $line->unit_id;
      }

      $available = (float) (DB::table('stock_levels')->where($where)->value($qtyCol) ?? 0);
      if ($available < (float) $line->qty) {
        throw new DomainException("Insufficient stock for product {$line->product_id}");
      }
    }

    if ($order->posted_at || $order->status === 'DELIVERED') {
      throw new DomainException('Order already delivered.');
    }

    DB::transaction(function () use ($order, $lines, $userId) {
      foreach ($lines as $line) {
        $payload = [
          'product_id'  => $line->product_id,
          'branch_id'   => $order->branch_id,
          'qty'         => $line->qty,
          'movement'    => 'SALE_OUT',
          'source_type' => 'sales_orders',
          'source_id'   => $order->id,
          'source_line' => $line->id ?? 0,
        ];

        if (Schema::hasColumn('inventory_ledger', 'unit_id') && !is_null($line->unit_id)) {
          $payload['unit_id'] = $line->unit_id;
        }

        $this->ledger->post($payload);
      }

      $order->update([
        'status'    => 'DELIVERED',
        'posted_at' => now(),
        'posted_by' => $userId,
      ]);
    });

    return $order->fresh();
  }
}
