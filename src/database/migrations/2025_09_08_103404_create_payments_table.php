<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Record money in/out (linked to SO or PO). Example: Lucky Mart pays $100 for SO#101.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // sales_orders or purchase_orders
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('cash');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
