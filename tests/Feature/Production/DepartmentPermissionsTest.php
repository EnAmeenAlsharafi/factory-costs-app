<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $carpentryWorker;

    protected User $customerService;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $managerRole = Role::where('name', 'production_manager')->first();
        $workerRole = Role::where('name', 'production_worker')->first();
        $csRole = Role::where('name', 'customer_service')->first();

        $carpentryDept = Department::where('code', 'CARPENTRY')->first();

        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->carpentryWorker = User::factory()->create([
            'role_id' => $workerRole->id,
            'department_id' => $carpentryDept->id,
            'is_active' => true,
        ]);

        $this->customerService = User::factory()->create([
            'role_id' => $csRole->id,
            'is_active' => true,
        ]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000700',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-005', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $poService->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    public function test_carpentry_worker_can_update_carpentry_operation(): void
    {
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();

        $response = $this->actingAs($this->carpentryWorker)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertEquals(5, $carpentryOp->fresh()->completed_quantity);
    }

    public function test_carpentry_worker_cannot_update_upholstery_operation(): void
    {
        $upholsteryOp = $this->po->operations->where('operation_code', 'OP_UPHOLSTERY')->first();

        $response = $this->actingAs($this->carpentryWorker)->post(route('production.operations.progress', $upholsteryOp), [
            'added_quantity' => 5,
        ]);

        $response->assertSessionHasErrors(['authorization']);
    }

    public function test_production_manager_can_update_all_departments(): void
    {
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();

        $response = $this->actingAs($this->manager)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertEquals(5, $carpentryOp->fresh()->completed_quantity);
    }

    public function test_customer_service_cannot_update_progress(): void
    {
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();

        $response = $this->actingAs($this->customerService)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 5,
        ]);

        $response->assertStatus(403);
    }
}
