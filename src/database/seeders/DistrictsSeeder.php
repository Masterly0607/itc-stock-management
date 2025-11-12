<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('districts')->upsert([
            ['id' => 10, 'province_id' => 1, 'name' => 'Daun Penh', 'code' => 'DNP', 'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['province_id', 'name', 'code', 'updated_at']);
    }
}
