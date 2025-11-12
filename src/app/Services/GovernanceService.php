<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use DomainException;

class GovernanceService
{
  public function __construct(protected AuditLogger $audit) {}

  /**
   * Deactivate a branch and (if users.branch_id exists) deactivate its users.
   */
  public function deactivateBranch(int $branchId, ?int $byUserId = null, ?string $reason = null): Branch
  {
    $branch = Branch::findOrFail($branchId);
    if (!$branch->is_active) {
      return $branch;
    }

    DB::transaction(function () use ($branch, $byUserId, $reason) {
      $branch->update([
        'is_active'      => false,
        'deactivated_at' => now(),
      ]);

      $affected = 0;
      if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id') && Schema::hasColumn('users', 'is_active')) {
        $affected = DB::table('users')
          ->where('branch_id', $branch->id)
          ->update(['is_active' => false, 'updated_at' => now()]);
      }

      $this->audit->log('branch.deactivated', [
        'user_id'     => $byUserId,
        'entity_type' => 'branches',
        'entity_id'   => $branch->id,
        'payload'     => [
          'reason'              => $reason,
          'affected_user_count' => $affected,
        ],
      ]);
    });

    return $branch->fresh();
  }
}
