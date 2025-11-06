<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory;

    // type: HQ|PROVINCE|DISTRICT
    protected $fillable = ['name', 'code', 'type', 'province_id', 'district_id', 'is_active'];

    protected $casts = [
        'is_active' => 'bool',
        'deactivated_at' => 'datetime',
    ];
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Inventory relations
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }
    public function inventoryLedgers()
    {
        return $this->hasMany(InventoryLedger::class);
    }
    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }
    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }
    public function stockCounts()
    {
        return $this->hasMany(StockCount::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(Transfer::class, 'from_branch_id');
    }
    public function transfersTo()
    {
        return $this->hasMany(Transfer::class, 'to_branch_id');
    }
}
