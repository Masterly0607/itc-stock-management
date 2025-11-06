<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('products')->insertOrIgnore([
            ['name' => 'Shampoo', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
