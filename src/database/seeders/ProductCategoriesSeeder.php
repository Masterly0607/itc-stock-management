<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        ProductCategory::query()->upsert([
            ['name' => 'Shampoo'],
            ['name' => 'Cream'],
        ], ['name'], ['name']);
    }
}
