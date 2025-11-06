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
    // ------------------------------
    // Phase 13 governance hard guards
    // ------------------------------

    // Branch active?
    if (Schema::hasColumn('branches', 'is_active')) {
      $active = DB::table('branches')->where('id', $order->branch_id)->value('is_active');
      if ($active !== null && (int)$active === 0) {
        throw new DomainException('Branch is inactive.');
      }
    } elseif (Schema::hasColumn('branches', 'status')) {
      $status = DB::table('branches')->where('id', $order->branch_id)->value('status');
      if (is_string($status) && strtoupper($status) === 'INACTIVE') {
        throw new DomainException('Branch is inactive.');
      }
    }
    // --- User active check (supports actingAs + many schema styles) ---
    $userId = $userId ?? (auth()->check() ? auth()->id() : null);

    if ($userId) {
      $user = DB::table('users')->where('id', $userId)->first();

      if ($user) {
        // Soft-deleted => inactive
        if (Schema::hasColumn('users', 'deleted_at') && $user->deleted_at !== null) {
          throw new DomainException('User is inactive.');
        }

        $inactive = false;

        $isInactiveBool = function ($val): bool {
          // Treat falsey / 0 / '0' as inactive
          return $val === false || $val === 0 || $val === '0' || $val === null;
        };

        $isInactiveStatus = function ($val): bool {
          if (is_numeric($val)) {
            return (int)$val === 0;
          }
          $v = strtoupper((string)$val);
          return in_array($v, ['INACTIVE', 'DISABLED', 'DEACTIVATED', 'SUSPENDED', 'BLOCKED', 'BANNED'], true);
        };

        // Common boolean flags
        if (!$inactive && Schema::hasColumn('users', 'is_active') && isset($user->is_active)) {
          $inactive = $isInactiveBool($user->is_active);
        }
        if (!$inactive && Schema::hasColumn('users', 'active') && isset($user->active)) {
          $inactive = $isInactiveBool($user->active);
        }
        if (!$inactive && Schema::hasColumn('users', 'enabled') && isset($user->enabled)) {
          $inactive = $isInactiveBool($user->enabled);
        }
        if (!$inactive && Schema::hasColumn('users', 'is_enabled') && isset($user->is_enabled)) {
          $inactive = $isInactiveBool($user->is_enabled);
        }

        // String or numeric status/state fields
        if (!$inactive && Schema::hasColumn('users', 'status') && isset($user->status)) {
          $inactive = $isInactiveStatus($user->status);
        }
        if (!$inactive && Schema::hasColumn('users', 'state') && isset($user->state)) {
          $inactive = $isInactiveStatus($user->state);
        }

        if ($inactive) {
          throw new DomainException('User is inactive.');
        }
      }
    }


    // Pay-before-deliver rule
    if ($order->status !== 'PAID') {
      throw new DomainException('Pay-before-deliver: order is not PAID.');
    }

    // Idempotency
    if ($order->posted_at || $order->status === 'DELIVERED') {
      throw new DomainException('Order already delivered.');
    }

    $lines = $order->items()->get();

    // Check availability (supports either `on_hand` or `qty` schema)
    foreach ($lines as $line) {
      $where = [
        'branch_id'  => $order->branch_id,
        'product_id' => $line->product_id,
      ];
      if (Schema::hasColumn('stock_levels', 'unit_id') && !is_null($line->unit_id)) {
        $where['unit_id'] = $line->unit_id;
      }

      $available = null;
      if (Schema::hasColumn('stock_levels', 'on_hand')) {
        $available = (float) (DB::table('stock_levels')->where($where)->value('on_hand') ?? 0);
      } else {
        $available = (float) (DB::table('stock_levels')->where($where)->value('qty') ?? 0);
      }

      if ($available < (float) $line->qty) {
        throw new DomainException("Insufficient stock for product {$line->product_id}");
      }
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

      // Audit
      if (class_exists(\App\Services\AuditLogger::class)) {
        \App\Services\AuditLogger::log('sales.delivered', [
          'user_id'     => $userId,
          'entity_type' => 'sales_orders',
          'entity_id'   => $order->id,
          'payload'     => [
            'branch_id'    => $order->branch_id,
            'total_amount' => $order->total_amount,
            'lines'        => $lines->map(fn($l) => [
              'product_id' => $l->product_id,
              'qty'        => $l->qty,
              'unit_id'    => $l->unit_id,
              'line_total' => $l->line_total,
            ])->values(),
          ],
        ]);
      }
    });

    return $order->fresh();
  }
}
