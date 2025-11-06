<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('branches')->insertOrIgnore([
            ['name' => 'HQ',          'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Phnom Penh', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
