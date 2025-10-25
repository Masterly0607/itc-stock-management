<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryLedger extends Model
{
    use HasFactory;

    // txn_type: PURCHASE_IN|TRANSFER_OUT|TRANSFER_IN|SALE_OUT|ADJUST_IN|ADJUST_OUT|COUNT_SET
    protected $fillable = [
        'branch_id',
        'product_id',
        'unit_id',
        'txn_type',
        'qty_delta',
        'balance_after',
        'reference_type',
        'reference_id',
        'posted_at',
        'posted_by',
    ];
    protected $casts = [
        'qty_delta'     => 'decimal:3',
        'balance_after' => 'decimal:3',
        'posted_at'     => 'datetime',
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
    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
