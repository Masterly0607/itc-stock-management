<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            // Minimal seeds only — NO branches/products that collide with tests
            $this->call([
                RolesSeeder::class,
                ProvincesSeeder::class,   // ok
                DistrictsSeeder::class,   // ok
                // BranchesSeeder::class,  // ❌ skip in testing
                UsersSeeder::class,       // ok if it doesn't depend on seeded branches
                UnitsSeeder::class,
                CategoriesSeeder::class,
                SuppliersSeeder::class,
                // ProductsSeeder::class,  // optional: skip if it creates branches/relations
                // PricesSeeder::class,    // skip (SQLite conflict prone)
                // StockLevelsSeeder::class, // optional (tests set their own)
            ]);
            return;
        }

        // Full seed for dev/prod
        $this->call([
            RolesSeeder::class,
            ProvincesSeeder::class,
            DistrictsSeeder::class,
            BranchesSeeder::class,   // ✅ only outside testing
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
