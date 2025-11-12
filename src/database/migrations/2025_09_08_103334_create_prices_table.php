<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'province_id', 'unit_id']);
            // Why do we need prices table?
            // 1. Different prices for different provinces (e.g., Phnom Penh vs. Siem Reap).
            // 2. Supports multi-unit pricing (box, piece, carton). Ex: Coca Cola 1L can be sold by Piece, Box, or Carton. Each price record links to a unit_id, so you can sell the same product in different packaging sizes.
            // 3. Makes the system more flexible & real-world ready. Ex: A promotion runs next month: “Buy 1 Get 1” or “10% off for Siem Reap.”
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
