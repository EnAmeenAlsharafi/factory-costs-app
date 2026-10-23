<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOperationBlock;
use App\Models\ProductionOperationEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionAssignmentService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use App\Services\QualityIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Mandatory Stage 16.5 coverage, spec §90–92, §98, §100, §104: assignment,
 * atomic claim, start, block/resume, rework history, and the permissions matrix.
 */
class ShopFloorExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $carpentryWorker;

    protected User $foamWorker;

    protected ProductionOrder $po;

    protected ProductionOrderService $orderService;

    protected ProductionExecutionService $executionService;

    protected ProductionAssignmentService $assignmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $managerRole = Role::where('name', 'production_manager')->first();
        $workerRole = Role::where('name', 'production_worker')->first();

        $carpentryDept = Department::where('code', 'CARPENTRY')->first();
        $foamDept = Department::where('code', 'FOAM')->first();

        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);
        $this->carpentryWorker = User::factory()->create(['role_id' => $workerRole->id, 'department_id' => $carpentryDept->id, 'is_active' => true]);
        $this->foamWorker = User::factory()->create(['role_id' => $workerRole->id, 'department_id' => $foamDept->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000900',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-900', 'name' => 'Test Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 20,
            'unit_price' => 100,
            'total_price' => 2000,
        ]);

        $this->orderService = app(ProductionOrderService::class);
        $this->executionService = app(ProductionExecutionService::class);
        $this->assignmentService = app(ProductionAssignmentService::class);

        $this->po = $this->orderService->createFromOrderLine($line, ['released_quantity' => 20, 'manufacturing_recipe_version_id' => $version->id]);

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $this->orderService->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    private function carpentryOp(): ProductionOrderOperation
    {
        return $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first()->fresh();
    }

    /** §90: Production Manager assigns a READY operation to an employee. */
    public function test_manager_can_assign_operation_to_employee(): void
    {
        $op = $this->carpentryOp();

        $assigned = $this->assignmentService->assign($op, $this->carpentryWorker, $this->manager);

        $this->assertEquals('ASSIGNED', $assigned->status);
        $this->assertEquals($this->carpentryWorker->id, $assigned->assigned_user_id);
        $this->assertEquals($this->manager->id, $assigned->assigned_by_user_id);
        $this->assertNotNull($assigned->assigned_at);

        $this->assertDatabaseHas('production_operation_events', [
            'production_order_operation_id' => $op->id,
            'event_type' => 'ASSIGNED',
        ]);
    }

    /** §90: assignment requires production.assignment.manage. */
    public function test_assign_rejects_actor_without_assignment_permission(): void
    {
        $op = $this->carpentryOp();

        $this->expectException(ValidationException::class);
        $this->assignmentService->assign($op, $this->carpentryWorker, $this->carpentryWorker);
    }

    /** §91: claim is an atomic compare-and-set — exactly one employee wins the race. */
    public function test_claim_race_exactly_one_employee_wins(): void
    {
        $op = $this->carpentryOp();
        $secondCarpentryWorker = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->first()->id,
            'department_id' => Department::where('code', 'CARPENTRY')->first()->id,
            'is_active' => true,
        ]);

        $winner = $this->assignmentService->claim($op, $this->carpentryWorker);
        $this->assertEquals($this->carpentryWorker->id, $winner->assigned_user_id);
        $this->assertEquals('ASSIGNED', $winner->status);

        $this->expectException(ValidationException::class);
        $this->assignmentService->claim($op->fresh(), $secondCarpentryWorker);
    }

    /** §92: starting a READY/ASSIGNED operation transitions it to IN_PROGRESS and logs STARTED. */
    public function test_start_transitions_to_in_progress_and_logs_event(): void
    {
        $op = $this->carpentryOp();

        $started = $this->executionService->start($op, $this->carpentryWorker);

        $this->assertEquals('IN_PROGRESS', $started->status);
        $this->assertEquals($this->carpentryWorker->id, $started->assigned_user_id);
        $this->assertNotNull($started->started_at);

        $this->assertDatabaseHas('production_operation_events', [
            'production_order_operation_id' => $op->id,
            'event_type' => 'STARTED',
            'to_status' => 'IN_PROGRESS',
        ]);
    }

    /** §92: start is idempotent — starting an already in-progress operation is a safe no-op. */
    public function test_start_is_idempotent(): void
    {
        $op = $this->carpentryOp();

        $this->executionService->start($op, $this->carpentryWorker);
        $again = $this->executionService->start($op->fresh(), $this->carpentryWorker);

        $this->assertEquals('IN_PROGRESS', $again->status);
        $this->assertEquals(1, ProductionOperationEvent::where('production_order_operation_id', $op->id)->where('event_type', 'STARTED')->count());
    }

    /** §92: an employee outside the operation's department cannot start it. */
    public function test_start_rejected_for_employee_outside_department(): void
    {
        $op = $this->carpentryOp();

        $this->expectException(ValidationException::class);
        $this->executionService->start($op, $this->foamWorker);
    }

    /** §98 + §99: blocking stops the operation; resolving restores the exact prior status. */
    public function test_block_stops_operation_and_resolve_restores_previous_state(): void
    {
        $op = $this->carpentryOp();
        $this->executionService->start($op, $this->carpentryWorker);
        $inProgress = $op->fresh();

        $block = $this->executionService->block($inProgress, 'MATERIAL_SHORTAGE', 'نواقص خشب', $this->carpentryWorker);

        $this->assertInstanceOf(ProductionOperationBlock::class, $block);
        $this->assertEquals('IN_PROGRESS', $block->previous_status);
        $this->assertTrue($block->is_active);
        $this->assertEquals('BLOCKED', $inProgress->fresh()->status);

        $resumed = $this->executionService->resolveBlock($inProgress->fresh(), 'تم توفير الخشب', $this->manager);

        $this->assertEquals('IN_PROGRESS', $resumed->status);
        $this->assertFalse($block->fresh()->is_active);
        $this->assertNotNull($block->fresh()->resolved_at);

        $this->assertDatabaseHas('production_operation_events', ['production_order_operation_id' => $op->id, 'event_type' => 'BLOCKED']);
        $this->assertDatabaseHas('production_operation_events', ['production_order_operation_id' => $op->id, 'event_type' => 'BLOCK_RESOLVED', 'to_status' => 'IN_PROGRESS']);
    }

    /** §98: a non-manager, non-department-member cannot block an operation. */
    public function test_block_requires_block_permission(): void
    {
        $op = $this->carpentryOp();

        $this->expectException(ValidationException::class);
        $this->executionService->block($op, 'OTHER', null, $this->foamWorker);
    }

    /** §100: creating and completing a rework action logs REWORK_CREATED / REWORK_COMPLETED on the operation's event history. */
    public function test_rework_lifecycle_preserves_history_on_operation_events(): void
    {
        $op = $this->carpentryOp();
        $this->orderService->recordProgress($op, 20, 'PROGRESS', null, $this->manager);

        $incidentService = app(QualityIncidentService::class);

        $carpentryDept = Department::where('code', 'CARPENTRY')->first();

        $incident = QualityIncident::create([
            'incident_number' => 'QI-TEST-900',
            'production_order_id' => $this->po->id,
            'production_order_operation_id' => $op->id,
            'affected_quantity' => 2,
            'detected_department_id' => $carpentryDept->id,
            'responsible_department_id' => $carpentryDept->id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'خلل في التجميع',
            'status' => 'ACTION_REQUIRED',
            'detected_by_user_id' => $this->manager->id,
        ]);

        $rework = $incidentService->createReworkAction($incident, $this->manager, [
            'source_operation_id' => $op->id,
            'target_operation_id' => $op->id,
            'action_type' => 'REWORK',
            'quantity' => 2,
            'notes' => 'إعادة تجميع القطعة',
        ]);

        $this->assertDatabaseHas('production_operation_events', [
            'production_order_operation_id' => $op->id,
            'event_type' => 'REWORK_CREATED',
        ]);

        $incidentService->completeReworkAction($rework, $this->manager);

        $this->assertEquals('COMPLETED', $rework->fresh()->status);
        $this->assertDatabaseHas('production_operation_events', [
            'production_order_operation_id' => $op->id,
            'event_type' => 'REWORK_COMPLETED',
        ]);

        // History survives: both events remain even after completion, nothing is overwritten or deleted.
        $this->assertEquals(1, ProductionOperationEvent::where('production_order_operation_id', $op->id)->where('event_type', 'REWORK_CREATED')->count());
        $this->assertEquals(1, ProductionOperationEvent::where('production_order_operation_id', $op->id)->where('event_type', 'REWORK_COMPLETED')->count());
    }

    /** §104: the permissions matrix — manager can do everything, worker is limited, customer service gets nothing. */
    public function test_permissions_matrix_for_shop_floor_actions(): void
    {
        $customerServiceRole = Role::where('name', 'customer_service')->first();
        $customerService = User::factory()->create(['role_id' => $customerServiceRole->id, 'is_active' => true]);

        // Manager: full access.
        $this->assertTrue($this->manager->can('production.assignment.view'));
        $this->assertTrue($this->manager->can('production.assignment.manage'));
        $this->assertTrue($this->manager->can('production.operation.start'));
        $this->assertTrue($this->manager->can('production.operation.claim'));
        $this->assertTrue($this->manager->can('production.operation.block'));
        $this->assertTrue($this->manager->can('production.operation.unblock'));

        // Worker: can start/claim/block, cannot manage assignment or unblock.
        $this->assertTrue($this->carpentryWorker->can('production.assignment.view'));
        $this->assertFalse($this->carpentryWorker->can('production.assignment.manage'));
        $this->assertTrue($this->carpentryWorker->can('production.operation.start'));
        $this->assertTrue($this->carpentryWorker->can('production.operation.claim'));
        $this->assertTrue($this->carpentryWorker->can('production.operation.block'));
        $this->assertFalse($this->carpentryWorker->can('production.operation.unblock'));

        // Customer service: none of the shop-floor permissions.
        $this->assertFalse($customerService->can('production.assignment.view'));
        $this->assertFalse($customerService->can('production.assignment.manage'));
        $this->assertFalse($customerService->can('production.operation.start'));
        $this->assertFalse($customerService->can('production.operation.claim'));
        $this->assertFalse($customerService->can('production.operation.block'));
        $this->assertFalse($customerService->can('production.operation.unblock'));
    }

    /** §104: the HTTP layer enforces the same matrix — an unauthorized role gets a hard 403. */
    public function test_http_layer_rejects_unauthorized_role_with_403(): void
    {
        $op = $this->carpentryOp();
        $customerServiceRole = Role::where('name', 'customer_service')->first();
        $customerService = User::factory()->create(['role_id' => $customerServiceRole->id, 'is_active' => true]);

        $this->actingAs($customerService)->post(route('production.operations.start', $op))->assertForbidden();
        $this->actingAs($customerService)->post(route('production.operations.claim', $op))->assertForbidden();
        $this->actingAs($customerService)->post(route('production.operations.block', $op), ['reason_code' => 'OTHER'])->assertForbidden();
    }

    /** §104: a worker gets a soft validation error (not a 403) when acting outside their own department. */
    public function test_http_layer_rejects_cross_department_action_with_validation_error(): void
    {
        $op = $this->carpentryOp();

        $response = $this->actingAs($this->foamWorker)->post(route('production.operations.start', $op));

        $response->assertSessionHasErrors(['authorization']);
    }
}
