<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $sh = ProductCategory::where('name', 'Shampoo')->first();
        $cr = ProductCategory::where('name', 'Cream')->first();
        foreach (
            [
                ['sku' => 'SH-001', 'name' => 'Shampoo 250ml', 'category_id' => $sh?->id, 'unit' => 'pcs', 'base_price' => 2.00, 'min_stock' => 50, 'is_active' => true],
                ['sku' => 'CR-001', 'name' => 'Face Cream 100g', 'category_id' => $cr?->id, 'unit' => 'pcs', 'base_price' => 5.00, 'min_stock' => 30, 'is_active' => true],
            ] as $r
        ) {
            Product::updateOrCreate(['sku' => $r['sku']], $r);
        }
    }
}
