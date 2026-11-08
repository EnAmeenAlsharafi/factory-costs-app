<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Company identity used on printed documents, and the link between a fabric
     * purchase order line and the production orders whose beds it covers.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#1d4ed8');
            $table->string('secondary_color', 7)->default('#f5b301');
            $table->string('commercial_register', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->text('document_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('fabric_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('meters', 12, 2);
            $table->timestamps();

            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_order_allocations');
        Schema::dropIfExists('company_settings');
    }
};
