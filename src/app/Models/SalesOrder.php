<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'branch_id',
        'customer_name',
        'status',
        'requires_prepayment',
        'total_amount',
        'currency',
        'posted_at',
        'posted_by'
    ];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
