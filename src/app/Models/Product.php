<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    // you chose sku unique; also keeping name/brand etc.
    protected $fillable = [
        'category_id',
        'unit_id',
        'sku',
        'barcode',
        'name',
        'brand',
        'supplier_id',
        'is_active',
    ];
    protected $casts = ['is_active' => 'bool'];

    public function baseUnit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'base_unit_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
    public function poItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
    public function transferItems()
    {
        return $this->hasMany(TransferItem::class);
    }
    public function salesItems()
    {
        return $this->hasMany(SalesOrderItem::class);
    }
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }
    public function ledger()
    {
        return $this->hasMany(InventoryLedger::class);
    }
    public function stockRequestItems()
    {
        return $this->hasMany(StockRequestItem::class);
    }
    public function adjustmentItems()
    {
        return $this->hasMany(AdjustmentItem::class);
    }
    public function stockCountItems()
    {
        return $this->hasMany(StockCountItem::class);
    }
}
