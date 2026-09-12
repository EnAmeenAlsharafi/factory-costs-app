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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique()->index(); // e.g. 'CUS-000001'
            $table->foreignId('customer_type_id')->constrained('customer_types')->restrictOnDelete();
            $table->foreignId('default_sales_channel_id')->nullable()->constrained('sales_channels')->nullOnDelete();

            $table->string('name')->index();
            $table->string('commercial_name')->nullable();
            $table->string('mobile')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('tax_number')->nullable();
            $table->string('commercial_registration')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_credit_customer')->default(false)->index();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
