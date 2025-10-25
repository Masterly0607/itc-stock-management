<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'province_id',
        'unit_id',
        'price',
        'currency',
        'starts_at',
        'ends_at',
        'is_active',
    ];
    protected $casts = [
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'bool',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function province()
    {
        return $this->belongsTo(Province::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
