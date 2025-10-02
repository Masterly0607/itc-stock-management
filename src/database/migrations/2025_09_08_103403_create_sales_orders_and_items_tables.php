<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Document when distributor buys from a branch. Example: Lucky Mart orders 50 Shampoo from Phnom Penh → SO#101.
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_no')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'delivered', 'closed'])->default('draft');
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        // Goal: Products inside the sales order. Example: SO#101 has 50 Shampoo @ $2 each.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->unique(['sales_order_id', 'product_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('sales_orders');
    }
};
