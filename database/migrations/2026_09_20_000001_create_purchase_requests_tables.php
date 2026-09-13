<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->date('request_date');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('source_type', 50)->default('MANUAL'); // MANUAL, LOW_STOCK, PRODUCTION_SHORTAGE, REORDER, OTHER
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->string('priority', 20)->default('NORMAL'); // NORMAL, URGENT, CRITICAL
            $table->date('required_by_date')->nullable();
            $table->string('status', 30)->default('DRAFT'); // DRAFT, SUBMITTED, UNDER_REVIEW, APPROVED, PARTIALLY_ORDERED, ORDERED, REJECTED, CANCELLED
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->decimal('requested_quantity', 12, 4);
            $table->foreignId('base_unit_id')->constrained('units_of_measure');
            $table->foreignId('preferred_purchase_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->date('required_by_date')->nullable();
            $table->decimal('estimated_unit_cost', 15, 4)->nullable();
            $table->decimal('estimated_total_cost', 15, 4)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};
