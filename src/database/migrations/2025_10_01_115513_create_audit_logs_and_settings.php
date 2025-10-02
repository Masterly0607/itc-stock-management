<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goal: Track who changed what. Example: Admin updated distributor phone from 123 → 456.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
        });
        //  Goal: Store global config. Example: Invoice prefix = ITC-.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
    }
};
