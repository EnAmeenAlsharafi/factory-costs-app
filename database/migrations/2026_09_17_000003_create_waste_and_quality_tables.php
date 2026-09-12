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
        Schema::create('production_waste_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quality_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('production_order_operation_id')->nullable()->constrained('production_order_operations', indexName: 'qin_po_op_id_fk')->nullOnDelete();
            $table->integer('affected_quantity')->default(1);

            $table->foreignId('detected_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('responsible_department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->string('incident_type'); // WRONG_DIMENSION, WRONG_FABRIC, WRONG_COLOR, MATERIAL_DAMAGE, WORKMANSHIP_DEFECT, LOADING_DAMAGE, MISSING_COMPONENT, ASSEMBLY_ERROR, OTHER
            $table->text('description');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->string('disposition')->nullable(); // REPAIR, REWORK, REMANUFACTURE, SCRAP, ACCEPT_AS_IS
            $table->string('status')->default('OPEN'); // OPEN, UNDER_REVIEW, ACTION_REQUIRED, RESOLVED, CANCELLED

            $table->foreignId('detected_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_waste_records', function (Blueprint $table) {
            $table->id();
            $table->string('waste_number')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('production_order_operation_id')->nullable()->constrained('production_order_operations', indexName: 'pwr_po_op_id_fk')->nullOnDelete();
            $table->foreignId('quality_incident_id')->nullable()->constrained('quality_incidents')->nullOnDelete();
            $table->foreignId('material_issue_line_id')->nullable()->constrained('material_issue_lines')->nullOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();

            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();

            $table->decimal('quantity', 12, 4);
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);

            $table->foreignId('waste_reason_id')->constrained('production_waste_reasons')->restrictOnDelete();
            $table->foreignId('detected_department_id')->nullable()->constrained('departments', indexName: 'pwr_det_dept_id_fk')->nullOnDelete();
            $table->foreignId('responsible_department_id')->nullable()->constrained('departments', indexName: 'pwr_resp_dept_id_fk')->nullOnDelete();

            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('production_rework_actions', function (Blueprint $table) {
            $table->id();
            $table->string('rework_number')->unique();
            $table->foreignId('quality_incident_id')->constrained('quality_incidents')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('source_operation_id')->nullable()->constrained('production_order_operations', indexName: 'pra_src_op_id_fk')->nullOnDelete();
            $table->foreignId('target_operation_id')->nullable()->constrained('production_order_operations', indexName: 'pra_tgt_op_id_fk')->nullOnDelete();

            $table->string('action_type')->default('REWORK'); // REPAIR, REWORK, REMAKE_COMPONENT, REMANUFACTURE
            $table->integer('quantity')->default(1);
            $table->string('status')->default('PENDING'); // PENDING, READY, IN_PROGRESS, COMPLETED, CANCELLED

            $table->foreignId('assigned_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_rework_actions');
        Schema::dropIfExists('production_waste_records');
        Schema::dropIfExists('quality_incidents');
        Schema::dropIfExists('production_waste_reasons');
    }
};
