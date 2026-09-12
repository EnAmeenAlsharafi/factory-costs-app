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

class ProductionCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $po;

    protected ProductionOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000600',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-004', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $this->service = app(ProductionOrderService::class);
        $this->po = $this->service->createFromOrderLine($line, ['released_quantity' => 20, 'manufacturing_recipe_version_id' => $version->id]);

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $this->service->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    public function test_packaging_completion_drives_production_order_status_and_quantity(): void
    {
        $carpentry = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $foam = $this->po->operations->where('operation_code', 'OP_FOAM')->first();
        $upholstery = $this->po->operations->where('operation_code', 'OP_UPHOLSTERY')->first();

        $boxProd = $this->po->operations->where('operation_code', 'OP_BOX_PROD')->first();
        $boxPrep = $this->po->operations->where('operation_code', 'OP_BOX_PREP')->first();

        $assembly = $this->po->operations->where('operation_code', 'OP_ASSEMBLY')->first();
        $packaging = $this->po->operations->where('operation_code', 'OP_PACKAGING')->first();

        // 1. Advance all upstream
        $this->service->recordProgress($carpentry, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($foam, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($upholstery, 20, 'PROGRESS', null, $this->manager);

        $this->service->recordProgress($boxProd, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($boxPrep, 20, 'PROGRESS', null, $this->manager);

        $this->service->recordProgress($assembly, 20, 'PROGRESS', null, $this->manager);

        // 2. Packaging 10 / 20 -> PARTIALLY_COMPLETED
        $this->service->recordProgress($packaging, 10, 'PROGRESS', null, $this->manager);

        $this->po->refresh();
        $this->assertEquals(10, $this->po->completed_quantity);
        $this->assertEquals('PARTIALLY_COMPLETED', $this->po->status);

        // 3. Packaging +10 = 20 / 20 -> COMPLETED
        $packaging->refresh();
        $this->service->recordProgress($packaging, 10, 'PROGRESS', null, $this->manager);

        $this->po->refresh();
        $this->assertEquals(20, $this->po->completed_quantity);
        $this->assertEquals('COMPLETED', $this->po->status);
        $this->assertNotNull($this->po->completed_at);
    }
}
