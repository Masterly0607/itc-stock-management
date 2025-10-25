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
        // Goal: Who we buy from. Example: Supplier A, Supplier B.
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // This is the company or supplier’s business name — the actual name of the supplier organization.
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable(); // It means the supplier’s government tax number. It’s used on bills and invoices so the tax department knows who the supplier is. Why? Because governments want to track business transactions for tax purposes. It proves the supplier is a legal, registered business.
            $table->string('contact_name')->nullable(); // This is the person you talk to at that company — your main contact person.
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
