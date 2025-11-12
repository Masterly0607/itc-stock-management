<?php

namespace App\Support;

use Filament\Facades\Filament;
// one place to get current branch and “is super admin?”
class Branch
{
  public static function id(): ?int
  {
    return Filament::auth()->user()?->branch_id;
  }

  public static function isSuper(): bool
  {
    $u = Filament::auth()->user();
    return $u?->hasRole('Super Admin') ?? false;
  }
}
