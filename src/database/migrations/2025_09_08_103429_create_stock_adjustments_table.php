<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Manual corrections (damage, returns, mistakes). Example: 3 Shampoo bottles broken → adjustment OUT -3.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adj_no')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->integer('qty');
            $table->string('reason')->nullable();
            // optional references (sales return, purchase return, count, etc.)
            $table->nullableMorphs('ref'); // ref_type, ref_id
            $table->enum('status', ['draft', 'approved', 'posted'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'product_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
