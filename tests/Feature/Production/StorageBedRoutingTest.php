<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
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

class StorageBedRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionRouting $routing;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000700',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-STORAGE', 'name' => 'Test Storage Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'has_storage' => true,
            'quantity' => 5,
            'unit_price' => 100,
            'total_price' => 500,
        ]);

        $this->po = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000700',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $line->id,
            'manufacturing_recipe_version_id' => $version->id,
            'ordered_quantity' => 5,
            'released_quantity' => 5,
            'status' => 'DRAFT',
        ]);

        $this->routing = ProductionRouting::where('routing_code', 'RTG-000002')->first();
    }

    public function test_storage_routing_creates_six_operations_with_carpentry_and_hydraulic_ready_immediately(): void
    {
        $this->assertNotNull($this->routing, 'RTG-000002 storage routing was not seeded.');

        $response = $this->actingAs($this->manager)->post(route('production.orders.release', $this->po), [
            'production_routing_id' => $this->routing->id,
        ]);

        $response->assertRedirect();
        $this->po->refresh();

        $this->assertEquals('RELEASED', $this->po->status);
        $this->assertCount(6, $this->po->operations);

        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $hydraulicBaseOp = $this->po->operations->where('operation_code', 'OP_HYDRAULIC_BASE')->first();
        $foamOp = $this->po->operations->where('operation_code', 'OP_FOAM')->first();
        $hydraulicAssemblyOp = $this->po->operations->where('operation_code', 'OP_HYDRAULIC_ASSEMBLY')->first();

        // Carpentry and the hydraulic base are independent parallel branches: both READY immediately.
        $this->assertEquals('READY', $carpentryOp->status);
        $this->assertEquals('READY', $hydraulicBaseOp->status);

        // Everything downstream waits.
        $this->assertEquals('PENDING', $foamOp->status);
        $this->assertEquals('PENDING', $hydraulicAssemblyOp->status);
    }

    public function test_hydraulic_assembly_waits_for_both_upholstery_and_hydraulic_base(): void
    {
        $this->actingAs($this->manager)->post(route('production.orders.release', $this->po), [
            'production_routing_id' => $this->routing->id,
        ]);
        $this->po->refresh();

        $service = app(ProductionOrderService::class);
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $foamOp = $this->po->operations->where('operation_code', 'OP_FOAM')->first();
        $upholsteryOp = $this->po->operations->where('operation_code', 'OP_UPHOLSTERY')->first();
        $hydraulicBaseOp = $this->po->operations->where('operation_code', 'OP_HYDRAULIC_BASE')->first();
        $hydraulicAssemblyOp = $this->po->operations->where('operation_code', 'OP_HYDRAULIC_ASSEMBLY')->first();
        $packagingOp = $this->po->operations->where('operation_code', 'OP_PACKAGING')->first();

        // Complete the upholstery branch fully, but NOT the hydraulic base yet.
        $service->recordProgress($carpentryOp->fresh(), 5, 'COMPLETE', null, $this->manager);
        $service->recordProgress($foamOp->fresh(), 5, 'COMPLETE', null, $this->manager);
        $service->recordProgress($upholsteryOp->fresh(), 5, 'COMPLETE', null, $this->manager);

        $this->assertEquals('PENDING', $hydraulicAssemblyOp->fresh()->status, 'Hydraulic assembly must still wait on the hydraulic base.');

        // Now finish the hydraulic base too — the join should unblock.
        $service->recordProgress($hydraulicBaseOp->fresh(), 5, 'COMPLETE', null, $this->manager);

        $this->assertEquals('READY', $hydraulicAssemblyOp->fresh()->status);
        $this->assertEquals('PENDING', $packagingOp->fresh()->status);

        $service->recordProgress($hydraulicAssemblyOp->fresh(), 5, 'COMPLETE', null, $this->manager);
        $this->assertEquals('READY', $packagingOp->fresh()->status);
    }
}
