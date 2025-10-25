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
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('sku')->unique(); // sku = a unique code you create for each product. Used for fast product lookup, barcoding, or reporting.
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('brand')->nullable(); // brand = The brand or manufacturer name
            $table->boolean('is_active')->default(true); // true = can be sold/purchased, false = discontinued or hidden from sale. Keeps your product list clean without deleting old items.
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete(); // if supplier deleted → set NULL on product
            $table->timestamps();
            $table->index(['category_id', 'unit_id']); // SELECT * FROM products WHERE category_id = 3; with index: Database instantly finds rows 3 and 4 (Rice, Sugar) using the index instead of scanning the whole table.

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
