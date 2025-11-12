<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_type', 100)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
            // no FK to users to avoid test FK issues
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
