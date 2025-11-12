<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasBranchScopes;

class Distributor extends Model
{
    use HasFactory, BelongsToBranch, HasBranchScopes;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
