<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureUserAndBranchActive
{
  public function handle(Request $request, Closure $next)
  {
    // Use Filament's guard (usually 'web')
    $guardName = Filament::getCurrentPanel()->getAuthGuard() ?? 'web';
    $auth = auth($guardName);

    // If not logged in, let Filament handle redirect
    if (! $auth->check()) {
      return $next($request);
    }

    // Always fetch the latest data from database
    $row = DB::table('users')
      ->where('id', $auth->id())
      ->select(['status', 'branch_id'])
      ->first();

    // 1️ User must be active
    if (! $row || strtoupper((string) $row->status) !== 'ACTIVE') {
      return $this->forceLogoutRedirect($auth, $request, 'Your account is inactive. Please contact admin.');
    }

    // 2️ Branch must be active (if user belongs to one)
    if ($row->branch_id) {
      $branchActive = (int) DB::table('branches')
        ->where('id', $row->branch_id)
        ->value('is_active');

      if ($branchActive !== 1) {
        return $this->forceLogoutRedirect($auth, $request, 'Your branch is inactive. Please contact admin.');
      }
    }

    // If both checks pass → continue
    return $next($request);
  }

  /**
   * Logout and redirect to login page with message.
   */
  private function forceLogoutRedirect($auth, Request $request, string $msg)
  {
    // Logout user
    $auth->logout();

    // Invalidate and regenerate session to prevent reuse
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    //  Flash message into the validation error bag
    return redirect()->to(\Filament\Facades\Filament::getLoginUrl())
      ->with('inactive_message', $msg);
  }
}
