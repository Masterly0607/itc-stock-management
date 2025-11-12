<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();

            // which branch the adjustment affects
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // free-text reason (no ENUM truncation issues)
            $table->string('reason', 255)->nullable();

            // workflow
            $table->enum('status', ['DRAFT', 'POSTED'])->default('DRAFT');

            // bookkeeping / governance
            $table->string('ref_no')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
