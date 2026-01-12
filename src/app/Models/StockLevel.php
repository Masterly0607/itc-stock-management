<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockLevel extends Model
{
    use HasFactory;

    protected $table = 'stock_levels';

    protected $fillable = [
        'branch_id',
        'product_id',
        'unit_id',
        'qty',
        'reserved',
    ];

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
