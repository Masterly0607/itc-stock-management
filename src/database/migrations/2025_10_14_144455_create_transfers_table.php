<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();

            $table->enum('status', ['DRAFT', 'DISPATCHED', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->foreignId('stock_request_id')->nullable()->constrained('stock_requests')->nullOnDelete();

            $table->string('ref_no')->nullable();
            $table->timestamp('dispatched_at')->nullable(); // ← added
            $table->timestamp('received_at')->nullable();   // ← added

            $table->timestamps();
            $table->index(['from_branch_id', 'to_branch_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
