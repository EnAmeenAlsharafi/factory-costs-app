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
        // 1. Internal Product Models Master
        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique()->index(); // e.g. 'MOD-000001'
            $table->string('name_ar')->index();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('reference_image_path')->nullable();
            $table->text('design_notes')->nullable();
            $table->boolean('is_custom_template')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Standard Bed Sizes Master
        Schema::create('standard_bed_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // e.g. 'SIZE-160X200'
            $table->decimal('width_cm', 8, 2)->index();
            $table->decimal('length_cm', 8, 2)->index();
            $table->string('name_ar')->nullable(); // e.g. '160 × 200 سم'
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 3. Reusable Product Manufacturing Configurations
        Schema::create('product_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('configuration_code')->unique()->index(); // e.g. 'CFG-000001'
            $table->foreignId('product_model_id')->constrained('product_models')->cascadeOnDelete();
            $table->foreignId('standard_bed_size_id')->nullable()->constrained('standard_bed_sizes')->nullOnDelete();
            $table->decimal('width_cm', 8, 2)->index();
            $table->decimal('length_cm', 8, 2)->index();
            $table->boolean('has_storage')->default(false)->index();
            $table->string('configuration_name')->nullable();
            $table->boolean('is_standard')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_model_id', 'width_cm', 'length_cm', 'has_storage'], 'unique_model_size_storage');
        });

        // 4. Customer Product Aliases
        Schema::create('customer_product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_model_id')->constrained('product_models')->cascadeOnDelete();
            $table->string('customer_product_name')->index();
            $table->string('customer_product_code')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['customer_id', 'customer_product_code'], 'unique_customer_product_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_product_aliases');
        Schema::dropIfExists('product_configurations');
        Schema::dropIfExists('standard_bed_sizes');
        Schema::dropIfExists('product_models');
    }
};
