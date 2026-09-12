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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique()->index(); // e.g. 'SUP-000001'
            $table->string('name')->index();
            $table->string('commercial_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('mobile')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('tax_number')->nullable();
            $table->string('commercial_registration')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->index();
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
