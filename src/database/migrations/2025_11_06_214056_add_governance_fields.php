<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (!Schema::hasColumn('branches', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                if (!Schema::hasColumn('branches', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                // Add branch_id if you want the “deactivate branch -> users inactive” cascade
                if (!Schema::hasColumn('users', 'branch_id') && Schema::hasTable('branches')) {
                    $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (Schema::hasColumn('branches', 'deactivated_at')) {
                    $table->dropColumn('deactivated_at');
                }
                if (Schema::hasColumn('branches', 'is_active')) {
                    $table->dropColumn('is_active');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'is_active')) {
                    $table->dropColumn('is_active');
                }
                // Don’t drop branch_id automatically; your app may rely on it.
            });
        }
    }
};
