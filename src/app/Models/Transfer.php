<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transfer extends Model
{
    use HasFactory;

    // status: DRAFT|DISPATCHED|RECEIVED|CANCELLED
    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'status',
        'stock_request_id',
        'ref_no',
    ];

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }
    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }
    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }
    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }
}
