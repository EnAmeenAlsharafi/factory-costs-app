<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsReceipt;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOperationEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\OrderTimelineService;
use App\Services\OrderTrackingService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mandatory Stage 16.5 coverage, spec §101–103: finished-goods handoff does
 * not auto-create a delivery, the derived Customer Order status walks
 * through every production/delivery stage correctly, and the unified
 * timeline is chronological with no duplicate events.
 */
class OrderTrackingAndTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected CustomerOrder $order;

    protected ProductionOrder $po;

    protected ProductionOrderService $orderService;

    protected ProductionExecutionService $executionService;

    protected OrderTrackingService $trackingService;

    protected OrderTimelineService $timelineService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000902',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-902', 'name' => 'Test Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id,
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
        $this->trackingService = app(OrderTrackingService::class);
        $this->timelineService = app(OrderTimelineService::class);

        $this->po = $this->orderService->createFromOrderLine($line, ['released_quantity' => 20, 'manufacturing_recipe_version_id' => $version->id]);

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $this->orderService->releaseProductionOrder($this->po, $routing, $this->manager);
    }

    private function op(string $code): ProductionOrderOperation
    {
        return $this->po->operations->where('operation_code', $code)->first()->fresh();
    }

    private function completeAllOperations(): void
    {
        foreach (['OP_CARPENTRY', 'OP_FOAM', 'OP_UPHOLSTERY', 'OP_BOX_PROD', 'OP_BOX_PREP', 'OP_ASSEMBLY', 'OP_PACKAGING'] as $code) {
            $this->executionService->complete($this->op($code), $this->manager);
        }
    }

    /** §101: completing packaging drives the Production Order to COMPLETED but never auto-creates a finished-goods receipt or delivery. */
    public function test_packaging_completion_does_not_auto_create_finished_goods_or_delivery(): void
    {
        $this->completeAllOperations();

        $this->assertEquals('COMPLETED', $this->po->fresh()->status);
        $this->assertEquals(0, FinishedGoodsReceipt::where('production_order_id', $this->po->id)->count());
        $this->assertEquals(0, DeliveryOrder::where('customer_order_id', $this->order->id)->count());
    }

    /** §102: derived status walks IN_PRODUCTION (once released) → PRODUCTION_BLOCKED → IN_PRODUCTION → PRODUCTION_COMPLETED. */
    public function test_derived_status_transitions_through_production_lifecycle(): void
    {
        // Approved for production but no release yet (no production orders at all) — still APPROVED_FOR_PRODUCTION.
        $unreleasedOrder = CustomerOrder::create([
            'order_number' => 'ORD-2026-000903',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 500.00,
            'created_by_user_id' => $this->manager->id,
        ]);
        $this->assertEquals('APPROVED_FOR_PRODUCTION', $this->trackingService->deriveStatus($unreleasedOrder));

        // Releasing to the shop floor already counts as "in production" even before any progress is recorded.
        $this->assertEquals('IN_PRODUCTION', $this->trackingService->deriveStatus($this->order->fresh()));

        $carpentry = $this->op('OP_CARPENTRY');
        $this->executionService->recordProgress($carpentry, 5, null, $this->manager);
        $this->assertEquals('IN_PRODUCTION', $this->trackingService->deriveStatus($this->order->fresh()));

        $this->executionService->block($carpentry->fresh(), 'MATERIAL_SHORTAGE', null, $this->manager);
        $this->assertEquals('PRODUCTION_BLOCKED', $this->trackingService->deriveStatus($this->order->fresh()));

        $this->executionService->resolveBlock($carpentry->fresh(), null, $this->manager);
        $this->assertEquals('IN_PRODUCTION', $this->trackingService->deriveStatus($this->order->fresh()));

        $this->completeAllOperations();
        $this->assertEquals('PRODUCTION_COMPLETED', $this->trackingService->deriveStatus($this->order->fresh()));
    }

    /** §102: once production is complete, delivery status (however it was reached) drives the derived order status. */
    public function test_derived_status_reflects_delivery_lifecycle_after_production_completed(): void
    {
        $this->completeAllOperations();

        $delivery = DeliveryOrder::create([
            'delivery_number' => 'DEL-TEST-902',
            'customer_order_id' => $this->order->id,
            'customer_id' => $this->order->customer_id,
            'sales_channel_id' => $this->order->sales_channel_id,
            'status' => 'READY_FOR_DELIVERY',
            'customer_name_snapshot' => 'عميل تجريبي',
            'installation_required' => true,
            'created_by_user_id' => $this->manager->id,
        ]);

        $this->assertEquals('READY_FOR_DELIVERY', $this->trackingService->deriveStatus($this->order->fresh()));

        $delivery->update(['status' => 'OUT_FOR_DELIVERY']);
        $this->assertEquals('OUT_FOR_DELIVERY', $this->trackingService->deriveStatus($this->order->fresh()));

        $delivery->update(['status' => 'DELIVERED']);
        $this->assertEquals('DELIVERED', $this->trackingService->deriveStatus($this->order->fresh()));

        $delivery->update(['status' => 'INSTALLATION_COMPLETED']);
        $this->assertEquals('FULFILLED', $this->trackingService->deriveStatus($this->order->fresh()));
    }

    /** §103: the production-order timeline is strictly chronological and carries no duplicate events. */
    public function test_production_order_timeline_is_chronological_with_no_duplicates(): void
    {
        $carpentry = $this->op('OP_CARPENTRY');
        $this->executionService->start($carpentry, $this->manager);
        $this->executionService->recordProgress($carpentry->fresh(), 10, 'نصف الكمية', $this->manager);
        $this->executionService->block($carpentry->fresh(), 'OTHER', null, $this->manager);
        $this->executionService->resolveBlock($carpentry->fresh(), null, $this->manager);
        $this->executionService->complete($carpentry->fresh(), $this->manager);

        $timeline = $this->timelineService->forProductionOrder($this->po->fresh());

        // Every event recorded for this Production Order appears exactly once — the aggregation
        // neither drops events nor double-counts any of them.
        $expectedCount = ProductionOperationEvent::where('production_order_id', $this->po->id)->count();
        $this->assertCount($expectedCount, $timeline);

        // Strictly non-decreasing occurred_at — the timeline reads chronologically.
        $timestamps = array_map(fn (array $row) => $row['occurred_at']->getTimestamp(), $timeline);
        $sorted = $timestamps;
        sort($sorted);
        $this->assertSame($sorted, $timestamps);

        // The last event chronologically is the completion — the journey ends where the work ended.
        $this->assertEquals('COMPLETED', end($timeline)['type']);
    }
}
