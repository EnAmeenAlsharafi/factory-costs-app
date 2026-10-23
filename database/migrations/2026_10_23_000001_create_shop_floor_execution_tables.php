<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.5 — Shop Floor Execution & End-to-End Order Tracking.
 *
 * Extends the existing production execution architecture with:
 *  - employee assignment timestamps on operations,
 *  - an append-only operation event history (canonical timeline source),
 *  - a structured operation block lifecycle,
 *  - expected operation duration for SLA derivation.
 *
 * No existing table is replaced. Historical production data is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Assignment + SLA timestamps on existing operations.
        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable()->after('status');
            $table->timestamp('assigned_at')->nullable()->after('assigned_user_id');
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_at')
                ->constrained('users')->nullOnDelete();

            // Queue / board performance (spec §72).
            $table->index(['production_order_id', 'status'], 'poo_order_status_idx');
            $table->index(['work_center_id', 'status'], 'poo_wc_status_idx');
            $table->index(['status', 'ready_at'], 'poo_status_ready_idx');
        });

        // 2. Expected operation duration — SLA source (spec §38, preferred location).
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->unsignedInteger('expected_minutes')->nullable()->after('is_required');
        });

        // 3. Append-only operation event history — canonical execution/timeline source (spec §27, §62, §121).
        Schema::create('production_operation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('production_order_operation_id')->nullable()
                ->constrained('production_order_operations', indexName: 'poe_op_id_fk')->nullOnDelete();

            // READY, ASSIGNED, CLAIMED, REASSIGNED, STARTED, PROGRESS_RECORDED,
            // BLOCKED, BLOCK_RESOLVED, COMPLETED, CANCELLED, REWORK_CREATED, REWORK_COMPLETED.
            $table->string('event_type');

            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->integer('quantity')->nullable();

            // Stable actor FK + display snapshot for historical resilience (spec §67).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name_snapshot')->nullable();
            $table->string('department_name_snapshot')->nullable();
            $table->string('work_center_name_snapshot')->nullable();

            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['production_order_id', 'occurred_at'], 'poe_order_occurred_idx');
            $table->index(['production_order_operation_id', 'occurred_at'], 'poe_op_occurred_idx');
            $table->index('event_type', 'poe_event_type_idx');
        });

        // 4. Structured operation block lifecycle (spec §20–22, §76).
        Schema::create('production_operation_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_operation_id')->constrained('production_order_operations', indexName: 'pob_op_id_fk')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();

            // MATERIAL_SHORTAGE, SPEC_ISSUE, QUALITY_ISSUE, AWAITING_MANAGER, EQUIPMENT_FAILURE, OTHER.
            $table->string('reason_code');
            $table->text('note')->nullable();

            // Status to restore on resume (spec §22).
            $table->string('previous_status');

            $table->foreignId('blocked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('blocked_at');

            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            // Optional link into existing material architecture (spec §21) — no second shortage engine.
            $table->foreignId('production_material_requirement_id')->nullable()
                ->constrained('production_material_requirements', indexName: 'pob_req_id_fk')->nullOnDelete();
            $table->foreignId('production_material_request_id')->nullable()
                ->constrained('production_material_requests', indexName: 'pob_mreq_id_fk')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['production_order_operation_id', 'is_active'], 'pob_op_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_operation_blocks');
        Schema::dropIfExists('production_operation_events');

        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->dropColumn('expected_minutes');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            // InnoDB adopted poo_order_status_idx / poo_wc_status_idx as the supporting index for
            // the pre-existing production_order_id / work_center_id foreign keys (instead of each
            // keeping its own single-column index), so each needs a plain replacement index added
            // before it can be dropped — otherwise MySQL refuses with "needed in a foreign key
            // constraint". This keeps down() a true, re-appliable undo of up().
            $table->index('production_order_id', 'poo_order_id_idx');
            $table->index('work_center_id', 'poo_wc_id_idx');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropIndex('poo_order_status_idx');
            $table->dropIndex('poo_wc_status_idx');
            $table->dropIndex('poo_status_ready_idx');
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropColumn(['ready_at', 'assigned_at']);
        });
    }
};
