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
        'received_at'
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
