<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->upsert([
            ['id' => 1, 'name' => 'Shampoo', 'code' => 'CAT-SHMP', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Soap', 'code' => 'CAT-SOAP', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['name', 'code', 'is_active', 'updated_at']);
    }
}
