<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // If tables/columns don’t exist (older DB), just skip.
        if (! Schema::hasTable('stock_levels') || ! Schema::hasTable('products')) {
            return;
        }

        if (
            ! Schema::hasColumn('stock_levels', 'unit_id') ||
            ! Schema::hasColumn('products', 'unit_id')
        ) {
            return;
        }

        // Backfill stock_levels.unit_id from products.unit_id, but in a DB-portable way.
        DB::table('stock_levels')
            ->whereNull('unit_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                // Get all product IDs in this chunk
                $productIds = $rows->pluck('product_id')->unique()->values()->all();
                if (empty($productIds)) {
                    return;
                }

                // Map product_id -> unit_id
                $unitByProduct = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->pluck('unit_id', 'id');

                // Update each stock_levels row individually
                foreach ($rows as $row) {
                    $unitId = $unitByProduct[$row->product_id] ?? null;

                    if ($unitId) {
                        DB::table('stock_levels')
                            ->where('id', $row->id)
                            ->update(['unit_id' => $unitId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Optional: if you ever want to "undo" the backfill:
        // DB::table('stock_levels')->update(['unit_id' => null]);
    }
};
