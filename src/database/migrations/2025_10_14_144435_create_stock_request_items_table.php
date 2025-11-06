<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_request_id')->constrained('stock_requests')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $t->decimal('qty_requested', 18, 3);
            $t->decimal('qty_approved', 18, 3)->nullable(); // set during approval
            $t->timestamps();

            $t->unique(['stock_request_id', 'product_id', 'unit_id']);
            $t->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
    }
};
