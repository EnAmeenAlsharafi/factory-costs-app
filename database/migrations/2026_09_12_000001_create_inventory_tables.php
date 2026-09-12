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
        // 1. Warehouses / Inventory Locations
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // e.g. 'RAW_MATERIALS'
            $table->string('name_ar')->index();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Material Receiving Headers
        Schema::create('material_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique()->index(); // e.g. 'REC-2026-000001'
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('receipt_date')->index();
            $table->string('supplier_reference')->nullable();
            $table->string('purchase_invoice_reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT')->index();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // 3. Material Receiving Lines
        Schema::create('material_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_receipt_id')->constrained('material_receipts')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();

            // Purchase Quantity & Unit
            $table->decimal('quantity_received', 18, 4);
            $table->foreignId('purchase_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            // Normalized Base Quantity & Unit
            $table->decimal('conversion_factor', 18, 6)->default(1);
            $table->decimal('base_quantity', 18, 4);
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            // Costing (High Precision)
            $table->decimal('unit_cost_purchase', 18, 6);
            $table->decimal('total_cost', 18, 4);
            $table->decimal('unit_cost_base', 18, 6);

            $table->string('supplier_material_code')->nullable();
            $table->string('quality_note')->nullable();
            $table->string('lot_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Inventory Lots (Operational State)
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_code')->unique()->index(); // e.g. 'LOT-000001'
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('receipt_line_id')->nullable()->constrained('material_receipt_lines')->nullOnDelete();

            $table->date('received_date')->index();
            $table->decimal('original_quantity', 18, 4);
            $table->decimal('remaining_quantity', 18, 4)->index();
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('unit_cost', 18, 6);

            $table->string('quality_grade')->nullable();
            $table->string('supplier_lot_reference')->nullable();
            $table->enum('status', ['ACTIVE', 'EXHAUSTED', 'EXPIRED'])->default('ACTIVE')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Central Inventory Movement Ledger (Authoritative History)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number')->unique()->index(); // e.g. 'MOV-000001'
            $table->enum('movement_type', [
                'RECEIPT',
                'ISSUE',
                'RETURN',
                'ADJUSTMENT_IN',
                'ADJUSTMENT_OUT',
                'OPENING_BALANCE',
            ])->index();

            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();

            // Movement Quantity & Direction
            $table->decimal('quantity', 18, 4); // Always positive
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->enum('direction', ['IN', 'OUT'])->index();

            // Movement Financials
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('total_cost', 18, 4)->nullable();

            // Document Association
            $table->string('reference_type')->nullable()->index(); // Model class name e.g. App\Models\MaterialReceipt
            $table->unsignedBigInteger('reference_id')->nullable()->index();

            $table->timestamp('occurred_at')->index();
            $table->foreignId('performed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Material Issue Headers
        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number')->unique()->index(); // e.g. 'ISS-2026-000001'
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('issue_date')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('purpose')->nullable(); // e.g. Internal consumption, workshop use
            $table->text('notes')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT')->index();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // 7. Material Issue Lines
        Schema::create('material_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->restrictOnDelete();

            $table->decimal('requested_quantity', 18, 4)->nullable();
            $table->decimal('issued_quantity', 18, 4);
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Material Return Headers
        Schema::create('material_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique()->index(); // e.g. 'RET-2026-000001'
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('return_date')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT')->index();
            $table->foreignId('returned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Material Return Lines
        Schema::create('material_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_return_id')->constrained('material_returns')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->restrictOnDelete();
            $table->foreignId('original_issue_line_id')->nullable()->constrained('material_issue_lines')->nullOnDelete();

            $table->decimal('returned_quantity', 18, 4);
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Inventory Adjustment Reasons Master
        Schema::create('inventory_adjustment_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('name_ar')->index();
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 10. Inventory Adjustment Headers
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique()->index(); // e.g. 'ADJ-2026-000001'
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('adjustment_date')->index();
            $table->foreignId('reason_id')->constrained('inventory_adjustment_reasons')->restrictOnDelete();

            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT')->index();
            $table->foreignId('adjusted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Inventory Adjustment Lines
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();

            $table->enum('adjustment_type', ['ADJUSTMENT_IN', 'ADJUSTMENT_OUT'])->index();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_adjustment_reasons');
        Schema::dropIfExists('material_return_lines');
        Schema::dropIfExists('material_returns');
        Schema::dropIfExists('material_issue_lines');
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('material_receipt_lines');
        Schema::dropIfExists('material_receipts');
        Schema::dropIfExists('warehouses');
    }
};
