<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    // Allow the fields your service sets via ::create()
    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'status',
        'stock_request_id',
        'ref_no',
    ];

    // (optional) relationships — handy for later phases
    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }
    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }
}
