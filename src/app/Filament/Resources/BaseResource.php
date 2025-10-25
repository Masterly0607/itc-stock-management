<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
  /** Control visibility of menu items */
  public static function shouldRegisterNavigation(): bool
  {
    $u = auth()->user();
    return $u?->hasAnyRole(['Super Admin']) ?? false;
  }

  /** Default permissions — SA only by default */
  public static function canViewAny(): bool
  {
    return auth()->check();
  }
  public static function canCreate(): bool
  {
    return auth()->user()?->hasRole('Super Admin') ?? false;
  }
  public static function canEdit($record): bool
  {
    return auth()->user()?->hasRole('Super Admin') ?? false;
  }
  public static function canDelete($record): bool
  {
    return auth()->user()?->hasRole('Super Admin') ?? false;
  }
  public static function canDeleteAny(): bool
  {
    return auth()->user()?->hasRole('Super Admin') ?? false;
  }
}
