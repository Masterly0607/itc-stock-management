<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'is_active'];

    public function products()
    {
        return $this->hasMany(Product::class);
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
