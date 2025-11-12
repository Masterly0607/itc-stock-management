<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvincesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('provinces')->upsert([
            ['id' => 1, 'name' => 'Phnom Penh', 'code' => 'PP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Siem Reap', 'code' => 'SR', 'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['name', 'code', 'updated_at']);
    }
}
