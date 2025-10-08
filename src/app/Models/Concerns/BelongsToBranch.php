<?php
// Controls what happens when saving or querying models
namespace App\Models\Concerns;

// trait = a way to reuse code in multiple classes
trait BelongsToBranch
{
  public static function bootBelongsToBranch(): void
  {
    // check if user has branch or not when adding data to database
    // static:: = Eloquent Model Event that run before function to do something
    //  static::creating = Run this function right before a new record is saved to the database.(Works when u use in that model)
    static::creating(function ($m) {
      if (auth()->check() && empty($m->branch_id)) {
        $m->branch_id = auth()->user()->branch_id;
      }
    });
  }

  // This function automatically makes sure users only see data from their own branch (except Super Admin, who sees everything).
  public function scopeForMyBranch($q)
  {
    $u = auth()->user();
    return (!$u || $u->hasRole('Super Admin'))
      ? $q
      : $q->where($q->getModel()->getTable() . '.branch_id', $u->branch_id);
  }
}
