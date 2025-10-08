<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;

trait HasCrudPermissions
{
  // NOTE: no $permPrefix property here!

  protected static function permPrefix(): string
  {
    // If the resource defines the property, use it
    if (property_exists(static::class, 'permPrefix') && isset(static::$permPrefix) && static::$permPrefix !== '') {
      return static::$permPrefix;
    }

    // Fallback: infer from class name, e.g. BranchResource => "branch"
    $base = class_basename(static::class);
    return (string) Str::of($base)->before('Resource')->kebab();
  }

  /* -------- Filament checks (buttons + nav visibility) -------- */

  public static function canViewAny(): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.view') ?? false;
  }

  public static function canView($record): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.view') ?? false;
  }

  public static function canCreate(): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.create') ?? false;
  }

  public static function canEdit($record): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.update') ?? false;
  }

  public static function canDelete($record): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.delete') ?? false;
  }

  public static function canDeleteAny(): bool
  {
    /** @var User|null $user */
    $user = Auth::user();
    return $user?->can(static::permPrefix() . '.delete') ?? false;
  }

  public static function canForceDelete($record): bool
  {
    return false;
  }
  public static function canRestore($record): bool
  {
    return false;
  }
}
