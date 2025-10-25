<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'product_id', 'unit_id', 'on_hand', 'reserved'];
    protected $casts = ['on_hand' => 'decimal:3', 'reserved' => 'decimal:3'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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
