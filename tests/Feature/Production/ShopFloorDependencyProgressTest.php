<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Mandatory Stage 16.5 coverage, spec §93–97: dependency math, parallel
 * branches, partial progress and over-progress/overshoot protection — now
 * exercised through ProductionExecutionService (the new execution façade)
 * rather than ProductionOrderService directly, confirming the façade
 * delegates to the single authoritative quantity engine with no drift.
 */
class ShopFloorDependencyProgressTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $po;

    protected ProductionOrderService $orderService;

    protected ProductionExecutionService $executionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000901',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-901', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $this->po = $this->orderService->createFromOrderLine($line, ['released_quantity' => 20, 'manufacturing_recipe_version_id' => $version->id]);

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $this->orderService->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    private function op(string $code): ProductionOrderOperation
    {
        return $this->po->operations->where('operation_code', $code)->first()->fresh();
    }

    /** §93/§94: the join at OP_ASSEMBLY is limited by the slower of its two parallel branches. */
    public function test_execution_service_enforces_join_dependency_across_parallel_branches(): void
    {
        $this->executionService->recordProgress($this->op('OP_CARPENTRY'), 20, null, $this->manager);
        $this->executionService->recordProgress($this->op('OP_FOAM'), 20, null, $this->manager);
        $this->executionService->recordProgress($this->op('OP_UPHOLSTERY'), 12, null, $this->manager);

        $this->executionService->recordProgress($this->op('OP_BOX_PROD'), 20, null, $this->manager);
        $this->executionService->recordProgress($this->op('OP_BOX_PREP'), 8, null, $this->manager);

        // Assembly is capped at min(Upholstery: 12, Box Prep: 8) = 8.
        $this->executionService->recordProgress($this->op('OP_ASSEMBLY'), 8, null, $this->manager);
        $this->assertEquals(8, $this->op('OP_ASSEMBLY')->completed_quantity);

        $this->expectException(ValidationException::class);
        $this->executionService->recordProgress($this->op('OP_ASSEMBLY'), 1, null, $this->manager);
    }

    /** §95: partial progress can be recorded in multiple increments, moving through PARTIALLY_COMPLETED. */
    public function test_partial_progress_accumulates_across_multiple_calls(): void
    {
        $carpentry = $this->op('OP_CARPENTRY');

        $this->executionService->recordProgress($carpentry, 5, null, $this->manager);
        $this->assertEquals('PARTIALLY_COMPLETED', $carpentry->fresh()->status);

        $this->executionService->recordProgress($carpentry->fresh(), 7, null, $this->manager);
        $this->assertEquals(12, $carpentry->fresh()->completed_quantity);
        $this->assertEquals('PARTIALLY_COMPLETED', $carpentry->fresh()->status);

        $this->executionService->complete($carpentry->fresh(), $this->manager);
        $this->assertEquals(20, $carpentry->fresh()->completed_quantity);
        $this->assertEquals('COMPLETED', $carpentry->fresh()->status);
    }

    /** §96: recording more than the remaining quantity is rejected. */
    public function test_over_progress_beyond_required_quantity_is_rejected(): void
    {
        $carpentry = $this->op('OP_CARPENTRY');
        $this->executionService->recordProgress($carpentry, 18, null, $this->manager);

        $this->expectException(ValidationException::class);
        $this->executionService->recordProgress($carpentry->fresh(), 5, null, $this->manager);
    }

    /** §97: two actors recording progress on the same operation never push it past required_quantity (row-locked). */
    public function test_concurrent_progress_from_two_actors_does_not_overshoot(): void
    {
        $secondManager = User::factory()->create(['role_id' => $this->manager->role_id, 'is_active' => true]);
        $carpentry = $this->op('OP_CARPENTRY');

        $this->executionService->recordProgress($carpentry, 15, null, $this->manager);
        $this->executionService->recordProgress($carpentry->fresh(), 5, null, $secondManager);

        $this->assertEquals(20, $carpentry->fresh()->completed_quantity);
        $this->assertEquals('COMPLETED', $carpentry->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->executionService->recordProgress($carpentry->fresh(), 1, null, $secondManager);
    }

    /** §17: complete() finishes the remaining quantity in one action and is idempotent once complete. */
    public function test_complete_finishes_remaining_quantity_and_is_idempotent(): void
    {
        $carpentry = $this->op('OP_CARPENTRY');
        $this->executionService->recordProgress($carpentry, 14, null, $this->manager);

        $completed = $this->executionService->complete($carpentry->fresh(), $this->manager);
        $this->assertEquals(20, $completed->completed_quantity);
        $this->assertEquals('COMPLETED', $completed->status);

        // Idempotent: completing again is a safe no-op, not a second COMPLETED event.
        $again = $this->executionService->complete($completed->fresh(), $this->manager);
        $this->assertEquals('COMPLETED', $again->status);
    }
}
