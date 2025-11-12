<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Backfill stock_levels.unit_id from products.base_unit_id when possible,
        // but DO NOT make the column NOT NULL (tests insert rows without a unit).
        if (Schema::hasColumn('products', 'base_unit_id') && Schema::hasColumn('stock_levels', 'unit_id')) {
            DB::statement("
                UPDATE stock_levels sl
                JOIN products p ON p.id = sl.product_id
                SET sl.unit_id = p.base_unit_id
                WHERE sl.unit_id IS NULL
            ");
        }

        // Backfill inventory_ledger.unit_id similarly (if the column exists).
        if (
            Schema::hasTable('inventory_ledger') && Schema::hasColumn('inventory_ledger', 'unit_id')
            && Schema::hasColumn('products', 'base_unit_id')
        ) {
            DB::statement("
                UPDATE inventory_ledger il
                JOIN products p ON p.id = il.product_id
                SET il.unit_id = p.base_unit_id
                WHERE il.unit_id IS NULL
            ");
        }

        // Ensure the unique scope includes unit_id (while leaving it nullable).
        if (Schema::hasTable('stock_levels')) {
            try {
                Schema::table('stock_levels', function (Blueprint $table) {
                    // Drop legacy unique if it exists; ignore if it doesn't.
                    try {
                        $table->dropUnique('stock_levels_branch_id_product_id_unique');
                    } catch (\Throwable $e) {
                    }
                });

                $hasIndex = collect(DB::select('SHOW INDEX FROM `stock_levels`'))
                    ->contains(fn($r) => $r->Key_name === 'uq_stock_levels_scope');

                if (! $hasIndex) {
                    Schema::table('stock_levels', function (Blueprint $table) {
                        $table->unique(['branch_id', 'product_id', 'unit_id'], 'uq_stock_levels_scope');
                    });
                }
            } catch (\Throwable $e) {
                // safe to ignore on SQLite/older engines
            }
        }
    }

    public function down(): void
    {
        // Drop our composite unique if present (safe rollback)
        if (Schema::hasTable('stock_levels')) {
            try {
                Schema::table('stock_levels', function (Blueprint $table) {
                    $table->dropUnique('uq_stock_levels_scope');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
