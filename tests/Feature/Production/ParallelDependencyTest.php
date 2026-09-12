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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParallelDependencyTest extends TestCase
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
            'order_number' => 'ORD-2026-000500',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-003', 'name' => 'Test Recipe', 'is_active' => true]);
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

    public function test_join_dependency_limits_assembly_quantity(): void
    {
        $carpentry = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $foam = $this->po->operations->where('operation_code', 'OP_FOAM')->first();
        $upholstery = $this->po->operations->where('operation_code', 'OP_UPHOLSTERY')->first();

        $boxProd = $this->po->operations->where('operation_code', 'OP_BOX_PROD')->first();
        $boxPrep = $this->po->operations->where('operation_code', 'OP_BOX_PREP')->first();

        $assembly = $this->po->operations->where('operation_code', 'OP_ASSEMBLY')->first();

        // 1. Branch A: Carpentry 20 -> Foam 16 -> Upholstery 10
        $this->service->recordProgress($carpentry, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($foam, 16, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($upholstery, 10, 'PROGRESS', null, $this->manager);

        // 2. Branch B: Box Prod 20 -> Box Prep 14
        $this->service->recordProgress($boxProd, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($boxPrep, 14, 'PROGRESS', null, $this->manager);

        // Max eligible Assembly Qty is min(Upholstery: 10, Box Prep: 14) = 10.
        $this->assertEquals(10, $this->service->calculateMaxEligibleQuantity($assembly));

        // Attempt Assembly 12 -> Should fail
        $this->expectException(ValidationException::class);
        $this->service->recordProgress($assembly, 12, 'PROGRESS', null, $this->manager);
    }

    public function test_assembly_limit_increases_when_lagging_branch_advances(): void
    {
        $carpentry = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();
        $foam = $this->po->operations->where('operation_code', 'OP_FOAM')->first();
        $upholstery = $this->po->operations->where('operation_code', 'OP_UPHOLSTERY')->first();

        $boxProd = $this->po->operations->where('operation_code', 'OP_BOX_PROD')->first();
        $boxPrep = $this->po->operations->where('operation_code', 'OP_BOX_PREP')->first();

        $assembly = $this->po->operations->where('operation_code', 'OP_ASSEMBLY')->first();

        $this->service->recordProgress($carpentry, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($foam, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($upholstery, 10, 'PROGRESS', null, $this->manager);

        $this->service->recordProgress($boxProd, 20, 'PROGRESS', null, $this->manager);
        $this->service->recordProgress($boxPrep, 15, 'PROGRESS', null, $this->manager);

        // Assembly 10 works
        $this->service->recordProgress($assembly, 10, 'PROGRESS', null, $this->manager);
        $this->assertEquals(10, $assembly->fresh()->completed_quantity);

        // Upholstery advances +5 to 15
        $this->service->recordProgress($upholstery, 5, 'PROGRESS', null, $this->manager);

        // Now min(15, 15) = 15. Assembly can do +2 (total 12)
        $this->service->recordProgress($assembly, 2, 'PROGRESS', null, $this->manager);
        $this->assertEquals(12, $assembly->fresh()->completed_quantity);
    }
}
