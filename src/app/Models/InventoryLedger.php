<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLedger extends Model
{
    protected $table = 'inventory_ledger';

    // allow mass assign on all columns we write
    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'qty' => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    // (optional) relationships used by resources
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
    public function user()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
