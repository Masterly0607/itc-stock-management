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
        Schema::table('stock_counts', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_counts', 'adjustment_id')) {
                $table->foreignId('adjustment_id')
                    ->nullable()
                    ->constrained('adjustments')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            if (Schema::hasColumn('stock_counts', 'adjustment_id')) {
                $table->dropConstrainedForeignId('adjustment_id');
            }
        });
    }
};
