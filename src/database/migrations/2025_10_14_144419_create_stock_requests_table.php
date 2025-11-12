<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();
            // who needs stock
            $t->foreignId('request_branch_id')->constrained('branches')->restrictOnDelete();
            // who will supply (HQ) — keep nullable; you can set default HQ in app logic
            $t->foreignId('supply_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $t->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('DRAFT');
            $t->string('ref_no')->nullable();
            $t->text('note')->nullable();

            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamps();

            $t->index(['request_branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
