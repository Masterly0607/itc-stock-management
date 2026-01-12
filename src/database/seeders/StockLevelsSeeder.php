<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockLevelsSeeder extends Seeder
{
    public function run(): void
    {
        // All branches
        $branches = DB::table('branches')->pluck('id');

        // Products with their base unit (assumes products.unit_id exists)
        $products = DB::table('products')->select('id', 'unit_id')->get();

        // Fallback: first unit in table, in case some products have no unit_id
        $defaultUnitId = DB::table('units')->value('id');

        $now  = now();
        $rows = [];

        foreach ($branches as $branchId) {
            foreach ($products as $product) {
                $unitId = $product->unit_id ?: $defaultUnitId;

                // If we still don't have a unit, skip to avoid constraint errors
                if (! $unitId) {
                    continue;
                }

                $rows[] = [
                    'branch_id'  => $branchId,
                    'product_id' => $product->id,
                    'unit_id'    => $unitId,
                    'qty'        => 0,
                    'reserved'   => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        collect($rows)
            ->chunk(1000)
            ->each(function ($chunk) {
                DB::table('stock_levels')->upsert(
                    $chunk->toArray(),
                    // unique key in schema: one row per (branch, product, unit)
                    ['branch_id', 'product_id', 'unit_id'],
                    // columns to update on conflict
                    ['qty', 'reserved', 'updated_at']
                );
            });
    }
}
