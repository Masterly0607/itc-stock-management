<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransferItem extends Model
{
    use HasFactory;

    protected $fillable = ['transfer_id', 'product_id', 'unit_id', 'qty'];
    protected $casts = ['qty' => 'decimal:3'];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
