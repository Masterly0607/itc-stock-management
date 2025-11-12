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
        'paid_amount',
        'currency',
        'posted_at',
        'posted_by',
        'delivered_at',
    ];

    protected $casts = [
        'requires_prepayment' => 'bool',
        'total_amount'        => 'decimal:2',
        'paid_amount'         => 'decimal:2',
        'posted_at'           => 'datetime',
        'delivered_at'        => 'datetime',
    ];

    // expose "is_paid" in arrays/json
    protected $appends = ['is_paid'];

    /* ----------------- Relationships ----------------- */

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /* ----------------- Helpers ----------------- */

    /** Recalculate totals from items & payments and persist. */
    public function recalcTotals(): void
    {
        $this->total_amount = (float) $this->items()->sum('line_total');
        $this->paid_amount  = (float) $this->payments()->sum('amount');
        $this->save();
    }

    /** Computed flag for pay-before-deliver rule. */
    public function getIsPaidAttribute(): bool
    {
        return (float) $this->paid_amount >= (float) $this->total_amount;
    }
}
