<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    protected $fillable = [
        'branch_id',
        'status',      // DRAFT | POSTED (or whatever you use)
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    // REQUIRED by the Filament Select
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // REQUIRED by the Filament Repeater
    public function items()
    {
        return $this->hasMany(StockCountItem::class);
    }
}
