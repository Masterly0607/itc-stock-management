<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
  public static function log(string $action, array $data = []): void
  {
    DB::table('audit_logs')->insert([
      'action'      => $action,
      'user_id'     => $data['user_id']     ?? null,
      'entity_type' => $data['entity_type'] ?? null,
      'entity_id'   => $data['entity_id']   ?? null,
      'payload'     => isset($data['payload']) ? json_encode($data['payload']) : null,
      'created_at'  => now(),
      'updated_at'  => now(),
    ]);
  }
}
