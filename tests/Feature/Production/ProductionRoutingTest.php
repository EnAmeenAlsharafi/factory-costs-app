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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRoutingTest extends TestCase
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
            'order_number' => 'ORD-2026-000300',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-001', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $this->po = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000300',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $line->id,
            'manufacturing_recipe_version_id' => $version->id,
            'ordered_quantity' => 10,
            'released_quantity' => 10,
            'status' => 'DRAFT',
        ]);

        $this->routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
    }

    public function test_routing_snapshot_created_on_order_release(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.orders.release', $this->po), [
            'production_routing_id' => $this->routing->id,
        ]);

        $response->assertRedirect();
        $this->po->refresh();

        $this->assertEquals('RELEASED', $this->po->status);
        $this->assertCount($this->routing->operations->count(), $this->po->operations);

        // Verify initial status: headboard carpentry and box prod should be READY (0 dependencies)
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $foamOp = $this->po->operations->where('operation_code', 'OP_FOAM')->first();

        $this->assertEquals('READY', $carpentryOp->status);
        $this->assertEquals('PENDING', $foamOp->status);
    }

    public function test_master_routing_edits_do_not_mutate_already_released_production_orders(): void
    {
        $this->actingAs($this->manager)->post(route('production.orders.release', $this->po), [
            'production_routing_id' => $this->routing->id,
        ]);

        $opCountOriginal = $this->po->operations()->count();

        // Mutate master routing by deleting an operation
        $routingOp = $this->routing->operations()->first();
        $routingOp->delete();

        // Released order operations should remain unchanged
        $this->assertEquals($opCountOriginal, $this->po->operations()->count());
    }
}
