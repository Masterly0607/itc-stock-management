<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_branch_id',
        'supply_branch_id',
        'status',
        'ref_no',
        'note',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
    ];

    public function items()
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function lines()
    {
        return $this->items();
    }

    public function requestBranch()
    {
        return $this->belongsTo(Branch::class, 'request_branch_id');
    }

    public function supplyBranch()
    {
        return $this->belongsTo(Branch::class, 'supply_branch_id');
    }
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];
}
