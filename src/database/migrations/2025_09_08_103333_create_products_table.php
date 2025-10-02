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
        // Goal: Actual items sold/bought. Example: SKU SH-001, “Shampoo 250ml”.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('unit')->default('pcs');
            $table->decimal('base_price', 12, 2)->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
