<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Adjustment extends Model
{
    protected $fillable = ['branch_id', 'reason', 'status', 'posted_at', 'approved_by'];
    public function items()
    {
        return $this->hasMany(AdjustmentItem::class);
    }
    // If you kept 'lines' in service:
    public function lines()
    {
        return $this->items();
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    protected $casts = [
        'posted_at' => 'datetime',
    ];
    // app/Models/Adjustment.php
    protected static function booted()
    {
        static::updating(function ($m) {
            if ($m->getOriginal('status') === 'POSTED') {
                throw new \DomainException('Posted adjustments are immutable. Create a reversing adjustment.');
            }
        });
    }
}
