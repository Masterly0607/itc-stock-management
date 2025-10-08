<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvincesSeeder::class,
            DistrictsSeeder::class,
            GeoSeeder::class,
            PermissionsSeeder::class,
            BranchesSeeder::class,
            ProductCategoriesSeeder::class,
            ProductsSeeder::class,
            SuppliersSeeder::class,
            DistributorsSeeder::class,
            RolesAndUsersSeeder::class,
        ]);
    }
}
