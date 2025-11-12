<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'sales_order_id',
        'amount',
        'currency',
        'method',
        'received_at',
        'received_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    protected static function booted()
    {
        // Keep order totals in sync and flip to PAID when fully paid
        $sync = function (Payment $p) {
            $order = $p->order()->with('items', 'payments')->first();
            if ($order) {
                $order->recalcTotals();

                // If fully paid, set status to PAID (but don't override DELIVERED).
                $fullyPaid = (float) $order->paid_amount + 0.0001 >= (float) $order->total_amount;
                if ($fullyPaid && $order->status !== 'DELIVERED' && $order->status !== 'PAID') {
                    $order->update(['status' => 'PAID']);
                }
            }
        };

        static::created($sync);
        static::updated($sync);
        static::deleted($sync);
    }
}
