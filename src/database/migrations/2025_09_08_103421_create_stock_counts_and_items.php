<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Record a physical count at a branch. Example: Phnom Penh staff count stock on Aug 1.
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('sc_no')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'in_progress', 'posted'])->default('draft');
            $table->timestamp('counted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });
        // Goal: Products counted vs system stock. Example: System says 100 Shampoo, staff found 95 → variance = -5.
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('qty_system');
            $table->integer('qty_found');
            $table->integer('variance'); // found - system
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
