<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;   //  import Schema

trait BranchScopedResource
{
  public static function getEloquentQuery(): Builder
  {
    /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
    $model = static::getModel();

    $query = $model::query();

    $u = auth()->user();

    if (! $u) {
      // Not logged in (Filament will redirect anyway) — return no rows as a guard.
      return $query->whereRaw('1=0');
    }

    // Super Admin sees everything
    if ($u->hasRole('Super Admin')) {
      return $query;
    }

    // If the model has a branch_id, scope to the user's branch
    $instance  = new $model;                 //  create an instance
    $table     = $instance->getTable();
    $hasBranch = Schema::hasColumn($table, 'branch_id');  //  use imported Schema

    if ($hasBranch && $u->branch_id) {
      //  call qualifyColumn() on the instance (not statically)
      return $query->where($instance->qualifyColumn('branch_id'), $u->branch_id);
    }

    // No branch_id → treat as master data (no filter)
    return $query;
  }
}
