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
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete(); // usually HQ
            $table->string('po_number')->unique();
            $table->enum('status', ['DRAFT', 'ORDERED', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->string('currency', 3)->default('USD');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('purchase_orders');
    }
};
