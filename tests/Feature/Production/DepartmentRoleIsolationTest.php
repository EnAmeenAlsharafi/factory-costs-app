<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Permission;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use App\Services\QualityIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Department-scoped roles: a carpentry role never reaches another department's work.
 */
class DepartmentRoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $carpentryWorker;

    protected User $carpentrySupervisor;

    protected User $foamWorker;

    protected ProductionOrder $po;

    protected ProductionOrderOperation $carpentryOp;

    protected ProductionOrderOperation $foamOp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $carpentry = Department::where('code', 'CARPENTRY')->first();
        $foam = Department::where('code', 'FOAM')->first();

        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->value('id'), 'is_active' => true]);
        $this->carpentryWorker = User::factory()->create(['role_id' => Role::where('name', 'carpentry.worker')->value('id'), 'department_id' => $carpentry->id, 'is_active' => true]);
        $this->carpentrySupervisor = User::factory()->create(['role_id' => Role::where('name', 'carpentry.supervisor')->value('id'), 'department_id' => $carpentry->id, 'is_active' => true]);
        $this->foamWorker = User::factory()->create(['role_id' => Role::where('name', 'foam.worker')->value('id'), 'department_id' => $foam->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000950',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-950', 'name' => 'Test Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 10,
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        $orders = app(ProductionOrderService::class);
        $this->po = $orders->createFromOrderLine($line, ['released_quantity' => 10, 'manufacturing_recipe_version_id' => $version->id]);
        $orders->releaseProductionOrder($this->po, ProductionRouting::where('routing_code', 'RTG-000001')->first(), $this->manager);

        $operations = $this->po->fresh('operations')->operations;
        $this->carpentryOp = $operations->firstWhere('operation_code', 'OP_CARPENTRY');
        $this->foamOp = $operations->firstWhere('operation_code', 'OP_FOAM');

        // Make the foam operation actionable so only the department rule can refuse it.
        $this->foamOp->update(['status' => 'READY']);
    }

    public function test_seeder_gives_each_production_department_its_own_worker_and_supervisor_roles(): void
    {
        foreach (Department::production()->get() as $department) {
            $this->assertSame(2, Role::forDepartment($department->id)->count(), $department->code);
        }

        $warehouse = Department::where('code', 'WAREHOUSE')->first();
        $this->assertSame(0, Role::forDepartment($warehouse->id)->count());
    }

    public function test_carpentry_worker_queue_lists_only_carpentry_operations_even_when_filtering_another_department(): void
    {
        $response = $this->actingAs($this->carpentryWorker)
            ->get(route('production.queue.index', ['department_id' => $this->foamOp->workCenter->department_id]));

        $response->assertOk();
        $operationIds = $response->viewData('operations')->pluck('id');
        $this->assertContains($this->carpentryOp->id, $operationIds);
        $this->assertNotContains($this->foamOp->id, $operationIds);
    }

    public function test_carpentry_worker_cannot_claim_a_foam_operation(): void
    {
        $this->actingAs($this->carpentryWorker)
            ->post(route('production.operations.claim', $this->foamOp))
            ->assertSessionHasErrors(['authorization' => 'لا يمكن تنفيذ عمليات خارج قسمك التشغيلي.']);

        $this->assertNull($this->foamOp->fresh()->assigned_user_id);
    }

    public function test_carpentry_worker_cannot_record_progress_on_a_foam_operation(): void
    {
        $this->actingAs($this->carpentryWorker)
            ->post(route('production.operations.progress', $this->foamOp), ['added_quantity' => 1])
            ->assertSessionHasErrors('authorization');

        $this->assertSame(0, $this->foamOp->fresh()->completed_quantity);
    }

    public function test_carpentry_supervisor_assigns_work_inside_carpentry(): void
    {
        $this->actingAs($this->carpentrySupervisor)
            ->post(route('production.operations.assign', $this->carpentryOp), ['employee_id' => $this->carpentryWorker->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->carpentryWorker->id, $this->carpentryOp->fresh()->assigned_user_id);
    }

    public function test_carpentry_supervisor_cannot_assign_a_foam_operation(): void
    {
        $this->actingAs($this->carpentrySupervisor)
            ->post(route('production.operations.assign', $this->foamOp), ['employee_id' => $this->foamWorker->id])
            ->assertSessionHasErrors(['authorization' => 'لا يمكن تنفيذ عمليات خارج قسمك التشغيلي.']);

        $this->assertNull($this->foamOp->fresh()->assigned_user_id);
    }

    public function test_carpentry_supervisor_cannot_resolve_a_block_in_foam(): void
    {
        app(ProductionExecutionService::class)->block($this->foamOp, 'MATERIAL_SHORTAGE', null, $this->manager);

        $this->actingAs($this->carpentrySupervisor)
            ->post(route('production.operations.resolve-block', $this->foamOp))
            ->assertSessionHasErrors('authorization');

        $this->assertSame('BLOCKED', $this->foamOp->fresh()->status);
    }

    public function test_carpentry_supervisor_cannot_correct_progress_in_foam(): void
    {
        $this->actingAs($this->carpentrySupervisor)
            ->post(route('production.operations.correct', $this->foamOp), ['new_completed_quantity' => 5, 'correction_notes' => 'تصحيح'])
            ->assertSessionHasErrors('authorization');

        $this->assertSame(0, $this->foamOp->fresh()->completed_quantity);
    }

    public function test_department_role_stays_confined_when_the_user_has_no_department_set(): void
    {
        $this->carpentryWorker->update(['department_id' => null]);

        $this->actingAs($this->carpentryWorker)
            ->post(route('production.operations.claim', $this->foamOp))
            ->assertSessionHasErrors('authorization');
    }

    public function test_production_order_outside_the_users_departments_is_hidden_from_list_and_page(): void
    {
        $hydraulic = Department::where('code', 'HYDRAULIC')->first();
        $hydraulicWorker = User::factory()->create(['role_id' => Role::where('name', 'hydraulic.worker')->value('id'), 'department_id' => $hydraulic->id, 'is_active' => true]);

        $this->actingAs($hydraulicWorker)->get(route('production.orders.index'))
            ->assertOk()
            ->assertDontSee($this->po->production_order_number);

        $this->actingAs($hydraulicWorker)->get(route('production.orders.show', $this->po))->assertNotFound();
    }

    public function test_production_order_page_shows_only_the_users_department_operations(): void
    {
        $response = $this->actingAs($this->carpentryWorker)->get(route('production.orders.show', $this->po));

        $response->assertOk();
        $this->assertSame([$this->carpentryOp->id], $response->viewData('order')->operations->pluck('id')->all());
    }

    public function test_quality_incident_of_another_department_is_hidden_from_list_and_page(): void
    {
        $foamIncident = $this->createIncident($this->foamOp);
        $carpentryIncident = $this->createIncident($this->carpentryOp);

        $incidentIds = $this->actingAs($this->carpentryWorker)->get(route('production.quality-incidents.index'))
            ->assertOk()
            ->viewData('incidents')->pluck('id');

        $this->assertContains($carpentryIncident->id, $incidentIds);
        $this->assertNotContains($foamIncident->id, $incidentIds);
        $this->actingAs($this->carpentryWorker)->get(route('production.quality-incidents.show', $foamIncident))->assertNotFound();
    }

    public function test_carpentry_worker_cannot_report_quality_on_a_foam_operation(): void
    {
        $this->actingAs($this->carpentryWorker)->post(route('production.quality-incidents.store'), [
            'production_order_id' => $this->po->id,
            'production_order_operation_id' => $this->foamOp->id,
            'affected_quantity' => 1,
            'detected_department_id' => $this->foamOp->workCenter->department_id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'عيب',
            'severity' => 'LOW',
        ])->assertSessionHasErrors([
            'production_order_operation_id' => 'لا يمكن التسجيل على عملية خارج قسمك التشغيلي.',
            'detected_department_id' => 'قسم الاكتشاف يجب أن يكون قسمك التشغيلي.',
        ]);

        $this->assertDatabaseMissing('quality_incidents', ['production_order_operation_id' => $this->foamOp->id]);
    }

    public function test_carpentry_worker_cannot_record_waste_on_a_foam_operation(): void
    {
        $this->actingAs($this->carpentryWorker)->post(route('production.waste.store'), [
            'production_order_id' => $this->po->id,
            'production_order_operation_id' => $this->foamOp->id,
        ])->assertSessionHasErrors(['production_order_operation_id' => 'لا يمكن التسجيل على عملية خارج قسمك التشغيلي.']);
    }

    public function test_employee_productivity_report_covers_only_the_users_department(): void
    {
        $this->carpentryOp->update(['assigned_user_id' => $this->carpentryWorker->id]);
        $this->foamOp->update(['assigned_user_id' => $this->foamWorker->id]);

        $employeeIds = $this->actingAs($this->carpentryWorker)->get(route('production.reports.employee-productivity'))
            ->assertOk()
            ->viewData('byEmployee')->keys()->all();

        $this->assertSame([$this->carpentryWorker->id], $employeeIds);
    }

    public function test_department_role_ignores_permissions_outside_its_department_scope(): void
    {
        $role = $this->carpentrySupervisor->role;
        $role->permissions()->attach(Permission::where('name', 'production.release')->value('id'));

        $this->assertFalse($this->carpentrySupervisor->fresh()->can('production.release'));
    }

    private function createIncident(ProductionOrderOperation $operation): QualityIncident
    {
        return app(QualityIncidentService::class)->createIncident($this->po, $this->manager, [
            'production_order_operation_id' => $operation->id,
            'detected_department_id' => $operation->workCenter->department_id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'عيب تصنيع',
        ]);
    }
}
