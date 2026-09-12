<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOperationProgress;
use App\Models\ProductionOrder;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationProgressTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000400',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-002', 'name' => 'Test Recipe', 'is_active' => true]);
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

        $poService = app(ProductionOrderService::class);
        $this->po = $poService->createFromOrderLine($line, ['released_quantity' => 20, 'manufacturing_recipe_version_id' => $version->id]);

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $poService->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    public function test_incremental_progress_records_history_events(): void
    {
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();

        // Morning: +5
        $this->actingAs($this->manager)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 5,
            'notes' => 'وجبة الصباح',
        ])->assertRedirect();

        $carpentryOp->refresh();
        $this->assertEquals(5, $carpentryOp->completed_quantity);
        $this->assertEquals('PARTIALLY_COMPLETED', $carpentryOp->status);

        // Afternoon: +3
        $this->actingAs($this->manager)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 3,
            'notes' => 'وجبة المساء',
        ])->assertRedirect();

        $carpentryOp->refresh();
        $this->assertEquals(8, $carpentryOp->completed_quantity);

        // Verify history
        $logs = ProductionOperationProgress::where('production_order_operation_id', $carpentryOp->id)->get();
        $this->assertCount(2, $logs);
    }

    public function test_cannot_exceed_required_quantity(): void
    {
        $carpentryOp = $this->po->operations->where('operation_code', 'OP_CARPENTRY')->first();

        $response = $this->actingAs($this->manager)->post(route('production.operations.progress', $carpentryOp), [
            'added_quantity' => 25, // Required is 20
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }
}
