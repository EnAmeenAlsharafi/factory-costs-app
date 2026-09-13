<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number', 50)->unique();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->date('issue_date');
            $table->date('response_due_date')->nullable();
            $table->string('status', 30)->default('DRAFT'); // DRAFT, SENT, RESPONSES_RECEIVED, CLOSED, CANCELLED
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_rfq_id')->constrained('purchase_rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('status', 30)->default('PENDING'); // PENDING, RESPONDED, DECLINED
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_quotation_number', 50)->unique();
            $table->foreignId('purchase_rfq_id')->nullable()->constrained('purchase_rfqs')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_reference', 100)->nullable();
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->string('payment_terms', 255)->nullable();
            $table->string('delivery_terms', 255)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->string('status', 30)->default('SUBMITTED'); // DRAFT, SUBMITTED, SELECTED, REJECTED, EXPIRED
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('shipping_amount', 15, 4)->default(0);
            $table->decimal('other_charges', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('selected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quotation_id')->constrained('supplier_quotations')->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->nullable()->constrained('purchase_request_lines')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->decimal('quoted_quantity', 12, 4);
            $table->foreignId('purchase_unit_id')->constrained('units_of_measure');
            $table->decimal('conversion_factor', 12, 4)->default(1.0000);
            $table->decimal('normalized_base_quantity', 12, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('line_total', 15, 4);
            $table->string('supplier_material_code', 100)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotation_lines');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('purchase_rfq_suppliers');
        Schema::dropIfExists('purchase_rfqs');
    }
};
