<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles; # HasRoles = gives user roles/permissions (Super Admin, Admin, Distributor).

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = ['name', 'email', 'password', 'branch_id'];

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
            'is_active' => 'bool',
            'reactivated_at' => 'datetime',
        ];
    }
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
    // Controls if the user can even open the panel
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(['Super Admin', 'Admin', 'Distributor']);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Convenience accessors
    // public function province()
    // {
    //     return $this->belongsTo(\App\Models\Province::class);
    // }

    // public function district()
    // {
    //     return $this->belongsTo(\App\Models\District::class);
    // }
}
