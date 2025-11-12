<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
  /** Control visibility of menu items */
  public static function shouldRegisterNavigation(): bool
  {
    return static::canViewAny();   // ← key change
  }

  /** Default permissions — SA only by default */
  public static function canViewAny(): bool
  {
    $u = auth()->user();
    return $u?->hasAnyRole(['Distributor', 'Admin', 'Super Admin']) ?? false;
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
