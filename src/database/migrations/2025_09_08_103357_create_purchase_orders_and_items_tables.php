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
        // Goal: Document when company orders from supplier. Example: HQ creates PO#001 for 1000 Shampoo from Unilever.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'approved', 'received', 'closed'])->default('draft');
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        // Goal: List of products inside the PO. Example: PO#001 includes 1000 Shampoo.
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchase_orders');
    }
};
