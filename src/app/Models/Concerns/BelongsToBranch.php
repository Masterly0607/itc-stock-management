<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds a reusable branch() relationship for models that have a branch_id.
 */
trait BelongsToBranch
{
  public function branch(): BelongsTo
  {
    return $this->belongsTo(Branch::class);
  }
}
