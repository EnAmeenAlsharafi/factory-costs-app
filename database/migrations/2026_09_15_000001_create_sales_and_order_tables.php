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
        // 1. Quotations Header
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_channel_id')->constrained('sales_channels');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('status', ['DRAFT', 'SENT', 'APPROVED', 'REJECTED', 'EXPIRED', 'CONVERTED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_total', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->text('commercial_notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('converted_to_order_at')->nullable();
            $table->timestamps();
        });

        // 2. Quotation Lines
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('customer_product_alias_id')->nullable()->constrained('customer_product_aliases')->nullOnDelete();
            $table->boolean('custom_design')->default(false);
            $table->string('custom_design_name')->nullable();
            $table->decimal('requested_width_cm', 8, 2);
            $table->decimal('requested_length_cm', 8, 2);
            $table->decimal('reference_width_cm', 8, 2)->nullable();
            $table->decimal('reference_length_cm', 8, 2)->nullable();
            $table->boolean('has_storage')->default(false);
            $table->foreignId('fabric_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1.0000);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('line_total', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->text('production_notes')->nullable();
            $table->timestamps();
        });

        // 3. Customer Orders Header
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_channel_id')->constrained('sales_channels');
            $table->string('customer_reference')->nullable();
            $table->string('external_order_reference')->nullable();
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            $table->enum('priority', ['NORMAL', 'URGENT', 'VIP'])->default('NORMAL');
            $table->enum('status', ['DRAFT', 'PENDING_PRODUCTION_REVIEW', 'NEEDS_CHANGES', 'APPROVED_FOR_PRODUCTION', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_total', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->text('commercial_notes')->nullable();
            $table->text('production_notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('production_reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('production_reviewed_at')->nullable();
            $table->foreignId('production_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('production_approved_at')->nullable();
            $table->timestamps();
        });

        // 4. Customer Order Lines
        Schema::create('customer_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('customer_product_alias_id')->nullable()->constrained('customer_product_aliases')->nullOnDelete();
            $table->boolean('custom_design')->default(false);
            $table->string('custom_design_name')->nullable();
            $table->decimal('requested_width_cm', 8, 2);
            $table->decimal('requested_length_cm', 8, 2);
            $table->decimal('reference_width_cm', 8, 2)->nullable();
            $table->decimal('reference_length_cm', 8, 2)->nullable();
            $table->boolean('has_storage')->default(false);
            $table->foreignId('fabric_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1.0000);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('line_total', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->text('production_notes')->nullable();
            $table->timestamps();
        });

        // 5. Customer Order Change History
        Schema::create('customer_order_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained('customer_order_lines')->nullOnDelete();
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('change_type')->default('UPDATE');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('occurred_after_production_approval')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_order_changes');
        Schema::dropIfExists('customer_order_lines');
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
