<?php

namespace App\Services;

use App\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesService
{
  public function __construct(
    protected LedgerWriter $ledger,
  ) {}

  public function post(int $orderId, int $userId): void
  {
    DB::transaction(function () use ($orderId, $userId) {
      /** @var SalesOrder $order */
      $order = SalesOrder::with('items', 'payments')
        ->lockForUpdate()
        ->findOrFail($orderId);

      if ($order->status !== 'DRAFT') {
        throw new \Exception('Only draft orders can be posted.');
      }
      if ($order->items->isEmpty()) {
        throw new \Exception('Order has no items.');
      }

      $order->recalcTotals();

      $order->update([
        'status'    => 'POSTED',
        'posted_by' => $userId,
        'posted_at' => now(),
      ]);
    });
  }

  public function confirm(SalesOrder $order): SalesOrder
  {
    if ($order->status !== 'DRAFT') {
      throw new DomainException('Only DRAFT orders can be confirmed.');
    }

    $order->update([
      'total_amount' => (float) $order->items()->sum('line_total'),
      'status'       => 'CONFIRMED',
    ]);

    return $order->fresh();
  }

  public function addPayment(SalesOrder $order, float $amount, string $method = 'CASH')
  {
    if (! in_array($order->status, ['CONFIRMED', 'POSTED', 'PAID'], true)) {
      throw new DomainException('Order must be CONFIRMED, POSTED or PAID to receive payments.');
    }

    return $order->payments()->create([
      'amount'      => $amount,
      'currency'    => $order->currency ?? 'USD',
      'method'      => $method,
      'received_at' => now(),
      'received_by' => auth()->id(),
    ]);
  }

  public function deliver(SalesOrder $order, ?int $userId = null): SalesOrder
  {
    $order->refresh()->loadMissing('items', 'payments');
    $order->recalcTotals();

    // Pay-before-deliver only when required
    if ($order->requires_prepayment && ! $order->is_paid) {
      throw new DomainException('Pay-before-deliver: order is not PAID.');
    }

    // Idempotency
    if ($order->posted_at || $order->status === 'DELIVERED') {
      throw new DomainException('Order already delivered.');
    }

    // Branch active governance
    if (Schema::hasColumn('branches', 'is_active')) {
      $active = DB::table('branches')->where('id', $order->branch_id)->value('is_active');
      if ($active !== null && (int) $active === 0) {
        throw new DomainException('Branch is inactive.');
      }
    } elseif (Schema::hasColumn('branches', 'status')) {
      $status = DB::table('branches')->where('id', $order->branch_id)->value('status');
      if (is_string($status) && strtoupper($status) === 'INACTIVE') {
        throw new DomainException('Branch is inactive.');
      }
    }

    // User active governance
    $userId = $userId ?? (auth()->check() ? auth()->id() : null);
    if ($userId) {
      $user = DB::table('users')->where('id', $userId)->first();
      if ($user) {
        if (Schema::hasColumn('users', 'deleted_at') && $user->deleted_at !== null) {
          throw new DomainException('User is inactive.');
        }

        $inactive = false;
        $falsy = fn($v) => $v === false || $v === 0 || $v === '0' || $v === null;

        $isInactiveStatus = function ($v): bool {
          if (is_numeric($v)) {
            return (int) $v === 0;
          }
          $v = strtoupper((string) $v);
          return in_array($v, ['INACTIVE', 'DISABLED', 'DEACTIVATED', 'SUSPENDED', 'BLOCKED', 'BANNED'], true);
        };

        foreach (['is_active', 'active', 'enabled', 'is_enabled'] as $col) {
          if (! $inactive && Schema::hasColumn('users', $col) && isset($user->{$col})) {
            $inactive = $falsy($user->{$col});
          }
        }

        foreach (['status', 'state'] as $col) {
          if (! $inactive && Schema::hasColumn('users', $col) && isset($user->{$col})) {
            $inactive = $isInactiveStatus($user->{$col});
          }
        }

        if ($inactive) {
          throw new DomainException('User is inactive.');
        }
      }
    }

    // Idempotency (again, after governance)
    if ($order->delivered_at || $order->status === 'DELIVERED') {
      throw new DomainException('Order already delivered.');
    }

    // Availability check
    $qtyCol = Schema::hasColumn('stock_levels', 'qty') ? 'qty'
      : (Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand' : null);

    if (! $qtyCol) {
      throw new DomainException('stock_levels has no qty/on_hand column.');
    }

    foreach ($order->items as $line) {
      $where = [
        'branch_id'  => $order->branch_id,
        'product_id' => $line->product_id,
      ];

      if (Schema::hasColumn('stock_levels', 'unit_id') && $line->unit_id) {
        $where['unit_id'] = $line->unit_id;
      }

      $available = (float) (DB::table('stock_levels')->where($where)->value($qtyCol) ?? 0);
      if ($available < (float) $line->qty) {
        throw new DomainException("Insufficient stock for product {$line->product_id}");
      }
    }

    DB::transaction(function () use ($order, $userId) {
      foreach ($order->items as $line) {
        $payload = [
          'product_id'  => $line->product_id,
          'branch_id'   => $order->branch_id,
          'qty'         => (float) $line->qty,
          'movement'    => 'SALE_OUT',
          'source_type' => 'sales_orders',
          'source_id'   => $order->id,
          'source_line' => $line->id ?? 0,
        ];


        if ($line->unit_id) {
          $payload['unit_id'] = $line->unit_id;
        }

        $this->ledger->post($payload);
      }


      $order->update([
        'status'       => 'DELIVERED',
        'delivered_at' => now(),
        'posted_at'    => $order->posted_at ?: now(),
        'posted_by'    => $userId,
      ]);

      if (class_exists(\App\Services\AuditLogger::class)) {
        \App\Services\AuditLogger::log('sales.delivered', [
          'user_id'     => $userId,
          'entity_type' => 'sales_orders',
          'entity_id'   => $order->id,
        ]);
      }
    });

    return $order->fresh();
  }
}
