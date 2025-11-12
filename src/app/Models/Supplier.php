<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'tax_id',
        'contact_name',
        'address',
        'is_active',
    ];
    protected $casts = ['is_active' => 'bool'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
