<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method')->nullable(); // CASH, TRANSFER, etc.
            $table->timestamp('received_at')->nullable();

            // NEW: who received the payment (tests expect this)
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['sales_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
