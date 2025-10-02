<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Full history of all stock movements (append-only). Example: Aug 1, Ledger shows: IN 1000 (PO#001 from Unilever), OUT 50 (SO#101 Lucky Mart), Transfer OUT 200 (HQ → PP)
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('ref_type');        // PO, SO, TRANSFER, ADJUST, COUNT
            $table->unsignedBigInteger('ref_id');
            $table->integer('qty_in')->default(0);
            $table->integer('qty_out')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['branch_id', 'product_id', 'occurred_at']);
        });
        // Goal: Current stock balance (fast lookup). Example: HQ Shampoo = 750 left.
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('qty_current')->default(0);
            $table->timestamps();
            $table->primary(['branch_id', 'product_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_ledger');
    }
};
