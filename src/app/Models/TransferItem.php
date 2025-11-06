<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferItem extends Model
{
    protected $fillable = [
        'transfer_id',
        'product_id',
        'unit_id',
        'qty',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
