<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('stock_levels')) {
            // Fresh create — per-unit stock
            Schema::create('stock_levels', function (Blueprint $table) {
                $table->id();

                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

                // main quantities
                $table->decimal('qty', 15, 3)->default(0);       // on hand
                $table->decimal('reserved', 15, 3)->default(0);  // future reservations

                $table->timestamps();

                // per-branch + per-product + per-unit uniqueness
                $table->unique(['branch_id', 'product_id', 'unit_id'], 'uq_stock_levels_scope');

                $table->index(['branch_id', 'product_id']);
            });

            return;
        }

        // --- Table exists: patch it safely ---

        // 1) Ensure unit_id column
        Schema::table('stock_levels', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_levels', 'unit_id')) {
                $table->foreignId('unit_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('units')
                    ->nullOnDelete();
            }
        });

        // 2) Ensure qty column (rename from on_hand if that’s what you had)
        if (! Schema::hasColumn('stock_levels', 'qty') && Schema::hasColumn('stock_levels', 'on_hand')) {
            // rename on_hand -> qty
            DB::statement('ALTER TABLE `stock_levels` CHANGE `on_hand` `qty` DECIMAL(15,3) NOT NULL DEFAULT 0');
        } elseif (! Schema::hasColumn('stock_levels', 'qty')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->decimal('qty', 15, 3)->default(0);
            });
        }

        // 3) Ensure reserved column
        if (! Schema::hasColumn('stock_levels', 'reserved')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->decimal('reserved', 15, 3)->default(0)->after('qty');
            });
        }

        // 4) Fix unique key: drop old (branch_id, product_id) and add new (branch_id, product_id, unit_id)
        try {
            Schema::table('stock_levels', function (Blueprint $table) {
                // common auto name if it existed
                $table->dropUnique('stock_levels_branch_id_product_id_unique');
            });
        } catch (\Throwable $e) {
            // ignore if it wasn't there
        }

        if (! $this->hasIndex('stock_levels', 'uq_stock_levels_scope')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->unique(['branch_id', 'product_id', 'unit_id'], 'uq_stock_levels_scope');
            });
        }
    }

    public function down(): void
    {
        // don’t drop table; just try to revert unique (optional)
        try {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->dropUnique('uq_stock_levels_scope');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->unique(['branch_id', 'product_id']);
            });
        } catch (\Throwable $e) {
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select('SHOW INDEX FROM `' . $table . '`'))
            ->contains(fn($r) => $r->Key_name === $index);
    }
};
