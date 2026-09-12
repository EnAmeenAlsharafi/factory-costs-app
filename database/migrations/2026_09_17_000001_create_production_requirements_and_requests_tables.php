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
        Schema::create('production_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders', indexName: 'pmreq_po_id_fk')->cascadeOnDelete();
            $table->foreignId('manufacturing_recipe_version_id')->nullable()->constrained('manufacturing_recipe_versions', indexName: 'pmreq_mrv_id_fk')->nullOnDelete();
            $table->foreignId('manufacturing_recipe_item_id')->nullable()->constrained('manufacturing_recipe_items', indexName: 'pmreq_mri_id_fk')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('semi_finished_component_id')->nullable()->constrained('semi_finished_components', indexName: 'pmreq_sfc_id_fk')->nullOnDelete();

            $table->decimal('required_quantity_per_unit', 12, 4)->default(0);
            $table->decimal('waste_percentage', 5, 2)->default(0);
            $table->decimal('planned_quantity_per_unit', 12, 4)->default(0);
            $table->integer('production_quantity')->default(1);
            $table->decimal('total_planned_quantity', 12, 4)->default(0);

            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('material_name_snapshot')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('production_material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_from_department_id')->nullable()->constrained('departments', indexName: 'pmr_req_dept_id_fk')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('request_date');

            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, PARTIALLY_FULFILLED, FULFILLED, REJECTED, CANCELLED
            $table->text('notes')->nullable();

            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            $table->timestamps();
        });

        Schema::create('production_material_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_material_request_id')->constrained('production_material_requests', indexName: 'pmr_lines_pmr_id_fk')->cascadeOnDelete();
            $table->foreignId('production_material_requirement_id')->nullable()->constrained('production_material_requirements', indexName: 'pmr_lines_pmreq_id_fk')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();

            $table->decimal('requested_quantity', 12, 4)->default(0);
            $table->decimal('approved_quantity', 12, 4)->nullable();
            $table->decimal('issued_quantity', 12, 4)->default(0);
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->string('request_reason')->default('PLANNED_PRODUCTION'); // PLANNED_PRODUCTION, ADDITIONAL_REQUIREMENT, REWORK, REMANUFACTURE, CORRECTION, OTHER
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_material_request_lines');
        Schema::dropIfExists('production_material_requests');
        Schema::dropIfExists('production_material_requirements');
    }
};
