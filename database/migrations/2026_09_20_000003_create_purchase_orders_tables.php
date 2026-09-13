<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('supplier_quotation_id')->nullable()->constrained('supplier_quotations')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->string('status', 30)->default('DRAFT'); // DRAFT, PENDING_APPROVAL, APPROVED, SENT, PARTIALLY_RECEIVED, RECEIVED, CLOSED, CANCELLED
            $table->string('supplier_reference', 100)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->string('delivery_terms', 255)->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('shipping_amount', 15, 4)->default(0);
            $table->decimal('other_charges', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->text('close_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->nullable()->constrained('purchase_request_lines')->nullOnDelete();
            $table->foreignId('supplier_quotation_line_id')->nullable()->constrained('supplier_quotation_lines')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->decimal('ordered_quantity', 12, 4);
            $table->foreignId('purchase_unit_id')->constrained('units_of_measure');
            $table->decimal('conversion_factor', 12, 4)->default(1.0000);
            $table->decimal('ordered_base_quantity', 12, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('line_total', 15, 4);
            $table->decimal('received_base_quantity', 12, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
