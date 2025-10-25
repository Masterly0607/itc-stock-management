<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_request_id',
        'product_id',
        'unit_id',
        'qty_requested',
        'qty_approved',
    ];
    protected $casts = [
        'qty_requested' => 'decimal:3',
        'qty_approved'  => 'decimal:3',
    ];

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
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
