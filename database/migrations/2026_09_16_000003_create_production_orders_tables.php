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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('production_order_number')->unique();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('customer_order_line_id')->constrained('customer_order_lines')->cascadeOnDelete();
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('manufacturing_recipe_version_id')->nullable()->constrained('manufacturing_recipe_versions')->nullOnDelete();
            $table->foreignId('customer_product_alias_id')->nullable()->constrained('customer_product_aliases')->nullOnDelete();
            $table->foreignId('production_routing_id')->nullable()->constrained('production_routings')->nullOnDelete();

            $table->boolean('is_custom_design')->default(false);
            $table->string('custom_design_name')->nullable();

            $table->decimal('requested_width_cm', 8, 2)->default(0);
            $table->decimal('requested_length_cm', 8, 2)->default(0);
            $table->decimal('reference_width_cm', 8, 2)->default(0);
            $table->decimal('reference_length_cm', 8, 2)->default(0);
            $table->boolean('has_storage')->default(false);

            $table->foreignId('fabric_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('fabric_color_id')->nullable()->constrained('fabric_colors')->nullOnDelete();

            $table->integer('ordered_quantity')->default(1);
            $table->integer('released_quantity')->default(1);
            $table->integer('completed_quantity')->default(0);

            $table->enum('priority', ['NORMAL', 'URGENT', 'VIP'])->default('NORMAL');
            $table->string('status')->default('DRAFT'); // DRAFT, READY_FOR_RELEASE, RELEASED, IN_PROGRESS, PARTIALLY_COMPLETED, COMPLETED, ON_HOLD, CANCELLED

            $table->date('planned_start_date')->nullable();
            $table->date('planned_completion_date')->nullable();

            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('production_notes')->nullable();
            $table->text('hold_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });

        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('routing_operation_id')->nullable()->constrained('production_routing_operations')->nullOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->restrictOnDelete();
            $table->string('operation_code');
            $table->string('operation_name_snapshot');
            $table->string('branch_key')->nullable();
            $table->integer('sequence_number')->default(1);

            $table->integer('required_quantity')->default(0);
            $table->integer('started_quantity')->default(0);
            $table->integer('completed_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);

            $table->string('status')->default('PENDING'); // PENDING, READY, IN_PROGRESS, PARTIALLY_COMPLETED, COMPLETED, BLOCKED, SKIPPED

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('production_operation_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_operation_id')->constrained('production_order_operations', indexName: 'pop_order_op_id_fk')->cascadeOnDelete();
            $table->string('event_type'); // START, PROGRESS, COMPLETE, HOLD, RESUME, NOTE, CORRECTION
            $table->integer('quantity')->default(0);
            $table->integer('previous_completed_quantity')->default(0);
            $table->integer('new_completed_quantity')->default(0);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_operation_progress');
        Schema::dropIfExists('production_order_operations');
        Schema::dropIfExists('production_orders');
    }
};
