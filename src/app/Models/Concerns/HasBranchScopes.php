<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasBranchScopes
{
  public function scopeForCurrentUser(Builder $query): Builder
  {
    $user = Auth::user();
    if (! $user) {
      return $query->whereRaw('1 = 0');
    }

    if ($user->hasRole('Super Admin')) {
      return $query;
    }

    if ($user->hasRole('Admin')) {
      return $query->where($this->getTable() . '.branch_id', $user->branch_id);
    }

    // Distributor has no access in panel
    return $query->whereRaw('1 = 0');
  }
}
