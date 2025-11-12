<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['stock_request_id', 'product_id', 'unit_id', 'qty_requested', 'qty_approved'];

    public function request()
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
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
