<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrder extends Model
{
    use HasFactory;

    // status: DRAFT|CONFIRMED|PAID|DELIVERED|CANCELLED
    protected $fillable = [
        'branch_id',
        'customer_name',
        'status',
        'requires_prepayment',
        'total_amount',
        'currency',
        'posted_at',
        'posted_by',
    ];
    protected $casts = [
        'requires_prepayment' => 'bool',
        'total_amount'        => 'decimal:2',
        'posted_at'           => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
