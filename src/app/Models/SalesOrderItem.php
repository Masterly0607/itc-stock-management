<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'unit_id',
        'qty',
        'unit_price',
        'line_total',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    protected static function booted(): void
    {
        // keep line_total correct
        $calcLine = function (SalesOrderItem $item) {
            $item->line_total = round((float)$item->qty * (float)$item->unit_price, 2);
        };
        static::creating($calcLine);
        static::updating($calcLine);

        // after ANY change to items, recalc parent total
        $recalc = function (SalesOrderItem $item) {
            if ($order = $item->order()->with('items')->first()) {
                $order->total_amount = $order->items->sum('line_total');
                $order->saveQuietly();
            }
        };
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }
}
