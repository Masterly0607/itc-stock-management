<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles; # HasRoles = gives user roles/permissions (Super Admin, Admin, Distributor).

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = ['name', 'email', 'password', 'branch_id', 'status'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // 'status' => 'boolean',
            // 'branch_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // useful inverse relations
    public function createdStockCounts()
    {
        return $this->hasMany(StockCount::class, 'created_by');
    }
    public function paymentsReceived()
    {
        return $this->hasMany(Payment::class, 'received_by');
    }
    public function postedLedgers()
    {
        return $this->hasMany(InventoryLedger::class, 'posted_by');
    }
}
