<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Adjustment extends Model
{
    use HasFactory;

    // reason: DAMAGE|EXPIRE|MANUAL  — status: DRAFT|POSTED
    protected $fillable = ['branch_id', 'reason', 'status', 'posted_at', 'approved_by'];
    protected $casts = ['posted_at' => 'datetime'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function items()
    {
        return $this->hasMany(AdjustmentItem::class);
    }
}
