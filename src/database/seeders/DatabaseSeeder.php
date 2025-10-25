<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            ProvincesSeeder::class,
            DistrictsSeeder::class,
            BranchesSeeder::class,
            UsersSeeder::class,
            CategoriesSeeder::class,
            UnitsSeeder::class,
            SuppliersSeeder::class,
            ProductsSeeder::class,

            PricesSeeder::class,
            StockLevelsSeeder::class,

        ]);
    }
}
