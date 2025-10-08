<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['code', 'name', 'province_id', 'district_id'];

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

    public function scopeMain($query)
    {
        return $query->whereNull('district_id');
    }
    // Auto-inactivate users when a branch is deleted
    protected static function booted(): void
    {
        static::deleting(function (Branch $branch) {
            // Inactivate all users under this branch and unlink the branch
            $branch->users()->update([
                'is_active' => false,
                'branch_id' => null,
            ]);
        });
    }
    /** Province admin accessor (from province main branch) */
    public function getProvinceAdminAttribute()
    {
        $source = $this->district_id === null
            ? $this
            : optional($this->province)->mainBranch;

        if (! $source) return null;

        return $source->users()->role('Admin')->first();
    }
}
