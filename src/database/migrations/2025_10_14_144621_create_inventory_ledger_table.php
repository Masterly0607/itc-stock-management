<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // movement values (positive=in, negative=out)
            $table->decimal('qty', 15, 4);
            $table->decimal('balance_after', 15, 4)->default(0);   // ← tests expect this
            $table->string('movement', 30)->nullable();            // IN / OUT / SALE_OUT / TRANSFER_IN / ...

            // reference to source document (tests expect these names)
            $table->string('source_type', 50)->nullable();         // e.g. 'sales_orders', 'transfers', 'adjustments'
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line')->nullable()->default(0);

            // who posted the ledger row
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            // idempotency / uniqueness (tests write/verify this)
            $table->string('hash', 64)->nullable()->unique();

            $table->timestamps();

            $table->index(['branch_id', 'product_id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};
