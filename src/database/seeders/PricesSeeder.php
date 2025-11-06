<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PricesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // -------- Ensure base UNIT --------
        $unitId = DB::table('units')->value('id');
        if (!$unitId) {
            $unitId = DB::table('units')->insertGetId([
                'name'       => 'PCS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // -------- Ensure base CATEGORY (products.category_id is NOT NULL in your schema) --------
        $categoryId = null;
        if (Schema::hasColumn('products', 'category_id')) {
            $categoryId = DB::table('categories')->value('id');
            if (!$categoryId) {
                $categoryId = DB::table('categories')->insertGetId([
                    'name'       => 'General',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // -------- Ensure base SUPPLIER if products.supplier_id exists --------
        $supplierId = null;
        if (Schema::hasColumn('products', 'supplier_id')) {
            $supplierId = DB::table('suppliers')->value('id');
            if (!$supplierId) {
                $supplierId = DB::table('suppliers')->insertGetId([
                    'name'       => 'Default Supplier',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // -------- Province (nullable OK; if NOT NULL in your prices table, ensure at least one) --------
        $provinceId = DB::table('provinces')->value('id'); // keep null if prices.province_id is nullable

        // -------- Ensure at least two PRODUCTS exist with required FKs --------
        $productIds = DB::table('products')->pluck('id')->all();

        $ensureProduct = function (string $name) use ($now, $categoryId, $supplierId) {
            $payload = [
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('products', 'category_id')) {
                $payload['category_id'] = $categoryId;
            }
            if (Schema::hasColumn('products', 'supplier_id')) {
                $payload['supplier_id'] = $supplierId;
            }
            if (Schema::hasColumn('products', 'unit_id')) {
                // if your products table has a unit FK, reuse the base unit
                $payload['unit_id'] = DB::table('units')->value('id');
            }
            if (Schema::hasColumn('products', 'sku')) {
                $payload['sku'] = strtoupper(uniqid('SKU'));
            }
            // Add other required (NOT NULL) columns if your schema has them:
            // e.g., if Schema::hasColumn('products','price') { $payload['price']=0; }
            // Keep this flexible.

            // Try to find existing by name; else create
            $existing = DB::table('products')->where('name', $name)->value('id');
            return $existing ?: DB::table('products')->insertGetId($payload);
        };

        if (count($productIds) < 1) {
            $productIds[] = $ensureProduct('Shampoo');
        }
        if (count($productIds) < 2) {
            $productIds[] = $ensureProduct('Soap');
        }
        // Refresh list in case some already existed
        $productIds = DB::table('products')->pluck('id')->take(2)->all();

        // -------- Build price rows --------
        $rows = [];
        if (isset($productIds[0])) {
            $rows[] = [
                'product_id'  => $productIds[0],
                'province_id' => $provinceId,   // leave null if column allows
                'unit_id'     => $unitId,
                'price'       => 3.50,
                'currency'    => 'USD',
                'starts_at'   => $now,
                'ends_at'     => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        if (isset($productIds[1])) {
            $rows[] = [
                'product_id'  => $productIds[1],
                'province_id' => $provinceId,
                'unit_id'     => $unitId,
                'price'       => 1.00,
                'currency'    => 'USD',
                'starts_at'   => $now,
                'ends_at'     => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }

        // -------- Upsert with a real UNIQUE key on your prices table --------
        // Recommend a unique index: unique(product_id, province_id, unit_id, starts_at)
        $hasUnique = true; // assume you created it
        if ($hasUnique) {
            DB::table('prices')->upsert(
                $rows,
                ['product_id', 'province_id', 'unit_id', 'starts_at'],
                ['price', 'currency', 'ends_at', 'is_active', 'updated_at']
            );
        } else {
            // Fallback if you don't have a composite unique:
            foreach ($rows as $r) {
                DB::table('prices')->updateOrInsert(
                    [
                        'product_id'  => $r['product_id'],
                        'province_id' => $r['province_id'],
                        'unit_id'     => $r['unit_id'],
                        'starts_at'   => $r['starts_at'],
                    ],
                    [
                        'price'      => $r['price'],
                        'currency'   => $r['currency'],
                        'ends_at'    => $r['ends_at'],
                        'is_active'  => $r['is_active'],
                        'updated_at' => $r['updated_at'],
                        'created_at' => $r['created_at'],
                    ]
                );
            }
        }
    }
}
