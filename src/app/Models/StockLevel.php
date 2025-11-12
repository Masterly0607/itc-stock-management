<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'unit_id',
        'qty',        // if your table uses qty
        'on_hand',    // if your table uses on_hand
        'reserved',   // optional column we'll add below
    ];

    protected $casts = [
        'reserved' => 'float',
    ];

    // --- Relationships
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

    // --- Accessors: unify qty/on_hand and computed available
    public function getOnHandAttribute()
    {
        if (array_key_exists('on_hand', $this->attributes)) {
            return (float) $this->attributes['on_hand'];
        }
        if (array_key_exists('qty', $this->attributes)) {
            return (float) $this->attributes['qty'];
        }
        return 0.0;
    }

    public function setOnHandAttribute($value): void
    {
        // Write back to whichever column exists in your schema
        if (array_key_exists('on_hand', $this->attributes)) {
            $this->attributes['on_hand'] = $value;
        } else {
            $this->attributes['qty'] = $value;
        }
    }

    public function getReservedAttribute()
    {
        // default 0 if the column is missing/null
        return (float) ($this->attributes['reserved'] ?? 0);
    }

    public function getAvailableAttribute()
    {
        return $this->on_hand - $this->reserved;
    }
}
