<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — foundation tables for Salla Excel order intake: bundle
 * definitions, product/SKU-name mappings, and import batch/row tracking.
 * Pure new tables (no alterations), so they carry no migration risk.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Bundle Definitions (البكجات)
        Schema::create('sales_bundle_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sales_sku')->nullable()->index();
            $table->string('match_name_normalized')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Bundle Components
        Schema::create('sales_bundle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_definition_id')->constrained('sales_bundle_definitions')->cascadeOnDelete();
            $table->enum('component_type', ['MANUFACTURED', 'READY_STOCK']);
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1.0000);
            $table->timestamps();
        });

        // 3. Salla Product/SKU Mappings
        Schema::create('sales_item_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->enum('match_type', ['SKU', 'NAME']);
            $table->string('match_value')->index();
            $table->enum('target_type', ['MANUFACTURED', 'READY_STOCK', 'BUNDLE', 'IGNORE']);
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('bundle_definition_id')->nullable()->constrained('sales_bundle_definitions')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Import Batches (رفع طلبات سلة)
        Schema::create('sales_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_hash')->index();
            $table->foreignId('sales_channel_id')->constrained('sales_channels');
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', ['UPLOADED', 'PREVIEWED', 'CONFIRMED', 'FAILED'])->default('UPLOADED')->index();
            $table->timestamps();
        });

        // 5. Import Rows (per Salla order, staged)
        Schema::create('sales_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('sales_import_batches')->cascadeOnDelete();
            $table->string('external_order_reference')->index();
            $table->json('raw_data');
            $table->enum('outcome', ['CREATED', 'SKIPPED', 'FAILED', 'DUPLICATE'])->nullable();
            $table->text('message')->nullable();
            $table->foreignId('created_customer_order_id')->nullable()->constrained('customer_orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_import_rows');
        Schema::dropIfExists('sales_import_batches');
        Schema::dropIfExists('sales_item_mappings');
        Schema::dropIfExists('sales_bundle_components');
        Schema::dropIfExists('sales_bundle_definitions');
    }
};
