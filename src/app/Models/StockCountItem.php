<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'product_id',
        'unit_id',
        'qty_counted',
    ];

    public function count()
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
