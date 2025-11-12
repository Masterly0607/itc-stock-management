<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
  /**
   * No-op by default. If the table still exists, we optionally record.
   */
  public static function log(string $action, array $data = []): void
  {
    if (! Schema::hasTable('audit_logs')) {
      return; // audit fully disabled
    }

    // If you truly want ZERO writes, delete everything below this line.
    DB::table('audit_logs')->insert([
      'created_at'  => now(),
      'user_id'     => $data['user_id'] ?? null,
      'action'      => $action,
      'entity_type' => $data['entity_type'] ?? null,
      'entity_id'   => $data['entity_id'] ?? null,
      'payload'     => $data['payload'] ?? ($data['meta'] ?? null),
    ]);
  }
}
