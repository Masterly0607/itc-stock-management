<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name');
            $table->string('code')->unique();

            // HQ / PROVINCE / DISTRICT
            $table->enum('type', ['HQ', 'PROVINCE', 'DISTRICT'])->default('HQ');

            // Location refs
            $table->foreignId('province_id')->nullable()
                ->constrained('provinces')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()
                ->constrained('districts')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // ---------- Generated columns for constraints ----------
            // One HQ in the whole system:
            // NULL for non-HQ, 1 for HQ → UNIQUE allows many NULLs but only one "1".
            $table->unsignedTinyInteger('hq_token')->nullable()
                ->storedAs("CASE WHEN `type` = 'HQ' THEN 1 ELSE NULL END");

            // One PROVINCE branch per province:
            // NULL for non-PROVINCE, province_id for PROVINCE → UNIQUE per province.
            $table->unsignedBigInteger('province_token')->nullable()
                ->storedAs("CASE WHEN `type` = 'PROVINCE' THEN `province_id` ELSE NULL END");

            // Indexes to enforce the above
            $table->unique('hq_token', 'uq_only_one_hq');
            $table->unique('province_token', 'uq_one_province_branch_per_province');

            // Helpful compound index for lookups
            $table->index(['type', 'province_id', 'district_id'], 'ix_branch_type_geo');
        });

        // (Optional) CHECK constraints — only if your MySQL supports them.
        // If you still want them, use raw SQL (MySQL 8.0.16+):
        // Comment out if your server complains.
        try {
            DB::statement("
                ALTER TABLE `branches`
                ADD CONSTRAINT `chk_branch_shape`
                CHECK (
                    (`type` = 'HQ'       AND province_id IS NULL AND district_id IS NULL) OR
                    (`type` = 'PROVINCE' AND province_id IS NOT NULL AND district_id IS NULL) OR
                    (`type` = 'DISTRICT' AND province_id IS NOT NULL AND district_id IS NOT NULL)
                )
            ");
        } catch (\Throwable $e) {
            // Ignore if CHECK not supported; generated columns already protect the main rules.
        }
    }

    public function down(): void
    {
        // Drop the extra indexes/columns first (order matters on some engines)
        if (Schema::hasTable('branches')) {
            // Drop CHECK if it exists (ignore errors on engines that don’t support it)
            try {
                DB::statement("ALTER TABLE `branches` DROP CHECK `chk_branch_shape`");
            } catch (\Throwable $e) {
                // noop
            }

            Schema::table('branches', function (Blueprint $table) {
                $table->dropUnique('uq_only_one_hq');
                $table->dropUnique('uq_one_province_branch_per_province');
                $table->dropIndex('ix_branch_type_geo');

                $table->dropColumn(['hq_token', 'province_token']);
            });
        }

        Schema::dropIfExists('branches');
    }
};
