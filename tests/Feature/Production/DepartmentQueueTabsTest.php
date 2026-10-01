<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\ProductionRouting;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentQueueTabsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $carpentryWorker;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->first()->id, 'is_active' => true]);
        $this->carpentryWorker = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->first()->id,
            'department_id' => Department::where('code', 'CARPENTRY')->first()->id,
            'is_active' => true,
        ]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000816',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);
        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-816', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $poService = app(ProductionOrderService::class);
        $this->po = $poService->createFromOrderLine($line, ['released_quantity' => 10, 'manufacturing_recipe_version_id' => $version->id]);
        $poService->releaseProductionOrder($this->po, ProductionRouting::where('routing_code', 'RTG-000001')->first(), $this->manager);
    }

    public function test_worker_queue_lists_only_own_department_operations(): void
    {
        $response = $this->actingAs($this->carpentryWorker)->get(route('production.queue.index'));

        $response->assertOk();
        $operationNames = $response->viewData('operations')->pluck('operation_code')->unique()->values()->all();
        $this->assertSame(['OP_CARPENTRY'], $operationNames);
    }

    public function test_worker_cannot_widen_queue_with_department_filter(): void
    {
        $upholsteryId = Department::where('code', 'UPHOLSTERY')->first()->id;

        $response = $this->actingAs($this->carpentryWorker)->get(route('production.queue.index', ['department_id' => $upholsteryId]));

        $response->assertOk();
        $this->assertSame(['OP_CARPENTRY'], $response->viewData('operations')->pluck('operation_code')->unique()->values()->all());
    }

    public function test_rework_tab_lists_only_operations_targeted_by_open_rework(): void
    {
        $carpentryOp = $this->po->operations()->where('operation_code', 'OP_CARPENTRY')->firstOrFail();
        $this->createRework($carpentryOp, 'RWK-TEST-816', 'PENDING');

        $response = $this->actingAs($this->carpentryWorker)->get(route('production.queue.index', ['tab' => 'rework']));

        $response->assertOk()->assertSee('إعادة عمل');
        $this->assertSame([$carpentryOp->id], $response->viewData('operations')->pluck('id')->all());
        $this->assertSame(1, $response->viewData('tabCounts')['rework']);
    }

    public function test_rework_tab_is_empty_when_rework_is_completed(): void
    {
        $carpentryOp = $this->po->operations()->where('operation_code', 'OP_CARPENTRY')->firstOrFail();
        $this->createRework($carpentryOp, 'RWK-TEST-817', 'COMPLETED');

        $response = $this->actingAs($this->carpentryWorker)->get(route('production.queue.index', ['tab' => 'rework']));

        $response->assertOk()->assertSee('لا توجد مهام إعادة عمل مفتوحة لقسمك');
        $this->assertSame(0, $response->viewData('operations')->count());
    }

    public function test_progress_feedback_states_the_new_remaining_quantity(): void
    {
        $carpentryOp = $this->po->operations()->where('operation_code', 'OP_CARPENTRY')->firstOrFail();

        $response = $this->actingAs($this->carpentryWorker)
            ->from(route('production.queue.index'))
            ->post(route('production.operations.progress', $carpentryOp), ['added_quantity' => 4]);

        $response->assertRedirect(route('production.queue.index'))
            ->assertSessionHas('success', 'تم تسجيل التقدم بنجاح. المنجز 4 من 10 — المتبقي 6.');
        $this->assertSame(4, $carpentryOp->fresh()->completed_quantity);
    }

    public function test_task_sheet_hides_costs_and_customer_balances_from_worker(): void
    {
        $response = $this->actingAs($this->carpentryWorker)->get(route('production.queue.index'));

        $response->assertOk()
            ->assertSee('تسجيل التقدم')
            ->assertDontSee('ر.س')
            ->assertDontSee('الرصيد');
    }

    private function createRework(ProductionOrderOperation $operation, string $number, string $status): ProductionReworkAction
    {
        $carpentry = Department::where('code', 'CARPENTRY')->first();
        $incident = QualityIncident::create([
            'incident_number' => 'QI-'.$number,
            'production_order_id' => $this->po->id,
            'production_order_operation_id' => $operation->id,
            'affected_quantity' => 2,
            'detected_department_id' => $carpentry->id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'خلل في تجميع الهيكل',
            'status' => 'ACTION_REQUIRED',
            'detected_by_user_id' => $this->manager->id,
        ]);

        return ProductionReworkAction::create([
            'rework_number' => $number,
            'quality_incident_id' => $incident->id,
            'production_order_id' => $this->po->id,
            'source_operation_id' => $operation->id,
            'target_operation_id' => $operation->id,
            'action_type' => 'REWORK',
            'quantity' => 2,
            'status' => $status,
            'assigned_department_id' => $carpentry->id,
            'authorized_by_user_id' => $this->manager->id,
        ]);
    }
}
