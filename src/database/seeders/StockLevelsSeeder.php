<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockLevelsSeeder extends Seeder
{
    public function run(): void
    {
        $branches = DB::table('branches')->pluck('id');
        $products = DB::table('products')->pluck('id');

        $now  = now();
        $rows = [];

        foreach ($branches as $b) {
            foreach ($products as $p) {
                $rows[] = [
                    'branch_id'  => $b,
                    'product_id' => $p,
                    'qty'        => 0,      // start clean; LedgerWriter will change this
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        collect($rows)
            ->chunk(1000)
            ->each(
                fn($chunk) =>
                DB::table('stock_levels')->upsert(
                    $chunk->toArray(),
                    ['branch_id', 'product_id'],        // unique key
                    ['qty', 'updated_at']               // update columns on conflict
                )
            );
    }
}
