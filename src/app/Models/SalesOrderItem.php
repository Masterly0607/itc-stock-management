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
        'line_total'
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
