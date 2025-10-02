<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Move stock between branches. Example: HQ → Phnom Penh Branch, 200 Shampoo → TR#201.
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('tr_no')->unique();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('status', ['draft', 'approved', 'shipped', 'received', 'closed'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['from_branch_id', 'to_branch_id', 'status']);
        });
        // Goal: List of products moved in a transfer. Example: TR#201 contains 200 Shampoo.
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();
            $table->unique(['transfer_id', 'product_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
    }
};
