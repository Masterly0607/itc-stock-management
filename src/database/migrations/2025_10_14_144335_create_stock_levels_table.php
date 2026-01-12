<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // For tests (sqlite) this will run fresh.
        // For your local MySQL, if table already exists you may want to drop it manually
        // then run migrate:fresh.
        if (Schema::hasTable('stock_levels')) {
            return;
        }

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // Nullable so tests can insert rows without specifying unit.
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // ✅ Canonical quantity column used everywhere in tests
            $table->decimal('qty', 15, 4)->default(0);

            // Optional reserved quantity
            $table->decimal('reserved', 15, 4)->default(0);

            $table->timestamps();

            // One row per (branch, product, unit)
            $table->unique(['branch_id', 'product_id', 'unit_id'], 'uq_stock_levels_scope');
            $table->index(['product_id', 'branch_id'], 'ix_stock_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
