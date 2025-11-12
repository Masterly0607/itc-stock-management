<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('stock_levels')) return;

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            // Core quantities
            $table->decimal('on_hand', 15, 4)->default(0);
            $table->decimal('reserved', 15, 4)->default(0); // optional but useful

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
