<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BranchScoped
{
  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()->forMyBranch();
  }
}
