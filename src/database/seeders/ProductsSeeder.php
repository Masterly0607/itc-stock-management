<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Supplier;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $acmeId = Supplier::firstOrCreate(
            ['code' => 'SUP-ACME'],
            ['name' => 'Acme Co', 'is_active' => true]
        )->id;

        $careId = Supplier::firstOrCreate(
            ['code' => 'SUP-CARE'],
            ['name' => 'Care Supplies', 'is_active' => true]
        )->id;

        Product::updateOrCreate(
            ['id' => 101],
            [
                'category_id' => 1,
                'unit_id' => 1,
                'supplier_id' => $acmeId,
                'sku' => 'SH-001',
                'barcode' => '885000000001',
                'name' => 'Shampoo 250ml',
                'brand' => 'FreshCare',
                'is_active' => 1,
            ]
        );

        Product::updateOrCreate(
            ['id' => 201],
            [
                'category_id' => 2,
                'unit_id' => 1,
                'supplier_id' => $careId,
                'sku' => 'SP-001',
                'barcode' => '885000000101',
                'name' => 'Soap Bar',
                'brand' => 'CleanMe',
                'is_active' => 1,
            ]
        );
    }
}
