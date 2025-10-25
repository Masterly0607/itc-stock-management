<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockCount extends Model
{
    use HasFactory;

    // status: DRAFT|POSTED
    protected $fillable = ['branch_id', 'status', 'created_by'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function items()
    {
        return $this->hasMany(StockCountItem::class);
    }
}
