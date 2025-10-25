<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockCountItem extends Model
{
    use HasFactory;

    protected $fillable = ['stock_count_id', 'product_id', 'unit_id', 'qty_counted'];
    protected $casts = ['qty_counted' => 'decimal:3'];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
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
