<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderChange;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentEvent;
use App\Models\Department;
use App\Models\FinishedGoodsMovement;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\PaymentControlOverride;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerOrderService;
use App\Services\CustomerPaymentService;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use App\Services\InventoryService;
use App\Services\OrderTrackingService;
use App\Services\PaymentAllocationService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use App\Services\QualityIncidentService;
use App\Services\QuotationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regression coverage for the system audit: double-submit safety, dead-end states,
 * cancellation safety, and the complete order journey from approval to installation.
 */
class AuditStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomerOrder $order;

    private CustomerOrderLine $line;

    private ProductionRouting $routing;

    private ProductionOrderService $production;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_active' => true]);
        $this->production = app(ProductionOrderService::class);
        $this->routing = ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail();

        $this->order = CustomerOrder::create([
            'order_number' => 'ORD-AUDIT-0001',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 200.00,
            'created_by_user_id' => $this->admin->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-AUDIT-1', 'name' => 'Audit Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $this->line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);
    }

    private function releasedOrder(int $quantity = 2): ProductionOrder
    {
        $po = $this->production->createFromOrderLine($this->line, ['released_quantity' => $quantity]);

        return $this->production->releaseProductionOrder($po, $this->routing, $this->admin);
    }

    private function operation(ProductionOrder $po, string $code): ProductionOrderOperation
    {
        return ProductionOrderOperation::where('production_order_id', $po->id)->where('operation_code', $code)->firstOrFail();
    }

    private function completeAllOperations(ProductionOrder $po): void
    {
        foreach ($po->operations()->orderBy('sequence_number')->get() as $operation) {
            app(ProductionExecutionService::class)->complete($operation->fresh(), $this->admin);
        }
    }

    private function payOrderInFull(): void
    {
        $payment = app(CustomerPaymentService::class)->createPayment([
            'customer_id' => $this->order->customer_id,
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'CASH',
        ], $this->admin);
        app(CustomerPaymentService::class)->confirmPayment($payment, $this->admin);
        app(PaymentAllocationService::class)->allocatePayment($payment, $this->order, 200, 'FINAL_PAYMENT', $this->admin);
    }

    private function override(string $stage): void
    {
        PaymentControlOverride::create([
            'customer_order_id' => $this->order->id,
            'customer_id' => $this->order->customer_id,
            'override_stage' => $stage,
            'requested_amount' => 200,
            'credit_limit' => 0,
            'current_exposure' => 0,
            'reason' => 'استثناء معتمد من الإدارة',
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_unpaid_order_cannot_be_released_to_production_without_an_override(): void
    {
        config(['app.enforce_payment_gates' => true]);
        $po = $this->production->createFromOrderLine($this->line, ['released_quantity' => 2]);

        try {
            $this->production->releaseProductionOrder($po, $this->routing, $this->admin);
            $this->fail('An unpaid FULL_BEFORE_PRODUCTION order must not reach the shop floor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('production_order', $exception->errors());
        }
        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->assertSame(0, $po->operations()->count());

        $this->override('PRODUCTION_RELEASE');

        $this->assertSame('RELEASED', $this->production->releaseProductionOrder($po->fresh(), $this->routing, $this->admin)->status);
    }

    public function test_complete_order_journey_reaches_operational_fulfilment_and_enforces_the_delivery_payment_gate(): void
    {
        config(['app.enforce_payment_gates' => true]);
        $this->override('PRODUCTION_RELEASE');
        $po = $this->releasedOrder();
        $tracking = app(OrderTrackingService::class);
        $this->assertSame('IN_PRODUCTION', $tracking->deriveStatus($this->order->fresh()));

        $this->completeAllOperations($po);
        $this->assertSame('COMPLETED', $po->fresh()->status);

        $finishedGoods = app(FinishedGoodsService::class);
        $receipt = $finishedGoods->postReceipt($finishedGoods->createReceipt($po->fresh(), $this->admin, ['quantity' => 2]), $this->admin);
        $this->assertSame('POSTED', $receipt->status);
        $this->assertSame(2.0, $finishedGoods->getAvailableQuantity($po));

        $deliveries = app(DeliveryOrderService::class);
        $driver = User::factory()->create(['role_id' => Role::where('name', 'delivery_user')->value('id'), 'is_active' => true]);
        $delivery = $deliveries->createDeliveryOrder($this->order->fresh(), $this->admin, [
            'installation_required' => true,
            'lines' => [['production_order_id' => $po->id, 'quantity' => 2]],
        ]);
        $deliveries->markReady($delivery, $this->admin);
        $deliveries->assignDriver($delivery, $driver, $this->admin);

        try {
            $deliveries->dispatchDelivery($delivery, $driver);
            $this->fail('Goods must not leave the warehouse while the order is unpaid.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('التسليم موقوف', $exception->getMessage());
        }
        $this->assertSame('ASSIGNED', $delivery->fresh()->status);
        $this->assertSame(0, FinishedGoodsMovement::where('movement_type', 'DELIVERY_DISPATCH')->count());

        $this->payOrderInFull();
        $deliveries->dispatchDelivery($delivery, $driver);
        $this->assertSame(1, FinishedGoodsMovement::where('movement_type', 'DELIVERY_DISPATCH')->count());
        $this->assertSame(0.0, $finishedGoods->getAvailableQuantity($po));
        $this->assertSame('OUT_FOR_DELIVERY', $tracking->deriveStatus($this->order->fresh()));

        $deliveries->markDelivered($delivery, $driver);
        $this->assertSame('DELIVERED', $tracking->deriveStatus($this->order->fresh()));

        $deliveries->markInstalled($delivery, $driver);
        $this->assertSame('FULFILLED', $tracking->deriveStatus($this->order->fresh()));
        $this->assertSame(0.0, $this->order->fresh()->outstanding_balance);
    }

    public function test_releasing_the_same_production_order_twice_does_not_duplicate_its_operations(): void
    {
        $po = $this->production->createFromOrderLine($this->line, ['released_quantity' => 2]);
        $staleCopy = ProductionOrder::findOrFail($po->id);
        $this->production->releaseProductionOrder($po, $this->routing, $this->admin);
        $operationCount = $po->operations()->count();

        try {
            $this->production->releaseProductionOrder($staleCopy, $this->routing, $this->admin);
            $this->fail('A second release must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('production_order', $exception->errors());
        }

        $this->assertSame($operationCount, $po->operations()->count());
    }

    public function test_posting_the_same_inventory_document_twice_moves_stock_once(): void
    {
        $material = Material::factory()->create();
        $inventory = app(InventoryService::class);
        $adjustment = $inventory->createAdjustment([
            'warehouse_id' => Warehouse::first()->id,
            'reason_id' => InventoryAdjustmentReason::where('code', 'PHYSICAL_COUNT_VARIANCE')->value('id'),
            'adjustment_date' => now()->toDateString(),
            'lines' => [['material_id' => $material->id, 'quantity' => 10, 'unit_cost' => 5, 'adjustment_type' => 'ADJUSTMENT_IN']],
        ], $this->admin->id);
        $staleCopy = InventoryAdjustment::findOrFail($adjustment->id);

        $inventory->postAdjustment($adjustment, $this->admin);

        try {
            $inventory->postAdjustment($staleCopy, $this->admin);
            $this->fail('A second posting must be rejected.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('غير مسودة', $exception->getMessage());
        }

        $this->assertSame(1, InventoryMovement::where('material_id', $material->id)->count());
        $this->assertSame(10.0, (float) InventoryLot::where('material_id', $material->id)->sum('remaining_quantity'));
    }

    public function test_converting_the_same_quotation_twice_creates_one_customer_order(): void
    {
        $quotation = Quotation::factory()->create(['status' => 'APPROVED']);
        $staleCopy = Quotation::findOrFail($quotation->id);
        $service = app(QuotationService::class);

        $service->convertToOrder($quotation, $this->admin);

        try {
            $service->convertToOrder($staleCopy, $this->admin);
            $this->fail('A second conversion must be rejected.');
        } catch (Exception) {
            $this->assertSame(1, CustomerOrder::where('quotation_id', $quotation->id)->count());
        }
    }

    public function test_confirming_the_same_payment_twice_records_one_confirmation(): void
    {
        $service = app(CustomerPaymentService::class);
        $payment = $service->createPayment([
            'customer_id' => $this->order->customer_id,
            'payment_date' => now()->toDateString(),
            'amount' => 50,
            'payment_method' => 'CASH',
        ], $this->admin);
        $staleCopy = CustomerPayment::findOrFail($payment->id);

        $service->confirmPayment($payment, $this->admin);
        $service->confirmPayment($staleCopy, $this->admin);

        $this->assertSame(1, CustomerPaymentEvent::where('customer_payment_id', $payment->id)->where('event_type', 'CONFIRMED')->count());

        try {
            $service->cancelPayment(tap(CustomerPayment::findOrFail($payment->id), fn ($copy) => $copy->status = 'PENDING_CONFIRMATION'), $this->admin);
            $this->fail('A confirmed payment must not be cancelled through a stale copy.');
        } catch (Exception) {
            $this->assertSame('CONFIRMED', $payment->fresh()->status);
        }
    }

    public function test_an_order_held_before_release_returns_to_draft_and_can_still_be_released(): void
    {
        $po = $this->production->createFromOrderLine($this->line, ['released_quantity' => 2]);

        $this->production->holdOrder($po, 'بانتظار اعتماد التصميم', $this->admin);
        $this->assertSame('DRAFT', $this->production->resumeOrder($po, $this->admin)->status);
        $this->assertSame('RELEASED', $this->production->releaseProductionOrder($po, $this->routing, $this->admin)->status);

        $this->production->holdOrder($po, 'توقف مؤقت', $this->admin);
        $this->assertSame('RELEASED', $this->production->resumeOrder($po, $this->admin)->status);
        $this->assertDatabaseHas('production_operation_events', ['production_order_id' => $po->id, 'event_type' => 'ORDER_HELD', 'user_id' => $this->admin->id]);
    }

    public function test_an_operation_blocked_while_waiting_becomes_ready_when_the_block_is_resolved_after_its_predecessor_finished(): void
    {
        $po = $this->releasedOrder();
        $execution = app(ProductionExecutionService::class);
        $foam = $this->operation($po, 'OP_FOAM');
        $this->assertSame('PENDING', $foam->status);

        $execution->block($foam, 'MATERIAL_SHORTAGE', 'نقص إسفنج', $this->admin);
        $execution->complete($this->operation($po, 'OP_CARPENTRY'), $this->admin);
        $this->assertSame('BLOCKED', $foam->fresh()->status);

        $execution->resolveBlock($foam->fresh(), 'وصل الإسفنج', $this->admin);

        $this->assertSame('READY', $foam->fresh()->status);
    }

    public function test_production_order_completes_once_its_open_quality_incident_is_resolved(): void
    {
        $po = $this->releasedOrder();
        $quality = app(QualityIncidentService::class);
        $incident = $quality->createIncident($po, $this->admin, [
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'عيب خياطة',
            'detected_department_id' => Department::first()->id,
            'affected_quantity' => 1,
        ]);

        $this->completeAllOperations($po);
        $this->assertSame('PARTIALLY_COMPLETED', $po->fresh()->status);

        $quality->setDisposition($incident, $this->admin, 'ACCEPT_AS_IS');

        $this->assertSame('COMPLETED', $po->fresh()->status);
    }

    public function test_progress_correction_cannot_drop_below_what_the_next_stage_already_finished(): void
    {
        $po = $this->releasedOrder();
        $execution = app(ProductionExecutionService::class);
        $execution->complete($this->operation($po, 'OP_CARPENTRY'), $this->admin);
        $execution->complete($this->operation($po, 'OP_FOAM'), $this->admin);

        $this->expectException(ValidationException::class);

        $this->production->correctProgress($this->operation($po, 'OP_CARPENTRY'), 1, 'تصحيح', $this->admin);
    }

    public function test_customer_order_with_live_production_cannot_be_cancelled_and_cancellation_is_audited(): void
    {
        $orders = app(CustomerOrderService::class);
        $po = $this->releasedOrder();

        try {
            $orders->cancelOrder($this->order, $this->admin, 'طلب العميل');
            $this->fail('An order with live production must not be cancelled.');
        } catch (Exception) {
            $this->assertSame('APPROVED_FOR_PRODUCTION', $this->order->fresh()->status);
        }

        $this->production->cancelOrder($po, 'إلغاء من العميل', $this->admin);
        $orders->cancelOrder($this->order, $this->admin, 'طلب العميل');

        $this->assertSame('CANCELLED', $this->order->fresh()->status);
        $this->assertTrue(CustomerOrderChange::where('customer_order_id', $this->order->id)
            ->where('field_name', 'status')->where('new_value', 'CANCELLED')->where('requested_by_user_id', $this->admin->id)->exists());
    }

    public function test_order_line_quantity_cannot_be_reduced_below_the_quantity_released_to_production(): void
    {
        $this->releasedOrder(2);

        try {
            app(CustomerOrderService::class)->updateOrder($this->order, [], [[
                'id' => $this->line->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]], $this->admin);
            $this->fail('Quantity below the released quantity must be rejected.');
        } catch (Exception) {
            $this->assertSame(2.0, (float) $this->line->fresh()->quantity);
        }
    }

    public function test_delivery_cannot_be_assigned_to_an_inactive_employee(): void
    {
        $po = $this->releasedOrder();
        $this->completeAllOperations($po);
        $finishedGoods = app(FinishedGoodsService::class);
        $finishedGoods->postReceipt($finishedGoods->createReceipt($po->fresh(), $this->admin, ['quantity' => 2]), $this->admin);
        $deliveries = app(DeliveryOrderService::class);
        $delivery = $deliveries->createDeliveryOrder($this->order->fresh(), $this->admin, ['lines' => [['production_order_id' => $po->id, 'quantity' => 2]]]);
        $deliveries->markReady($delivery, $this->admin);
        $formerDriver = User::factory()->create(['role_id' => Role::where('name', 'delivery_user')->value('id'), 'is_active' => false]);

        try {
            $deliveries->assignDriver($delivery, $formerDriver, $this->admin);
            $this->fail('An inactive employee must not receive a delivery.');
        } catch (Exception) {
            $this->assertSame('READY_FOR_DELIVERY', $delivery->fresh()->status);
        }
    }

    public function test_inventory_lot_page_opens_for_a_lot_created_by_an_adjustment(): void
    {
        $material = Material::factory()->create();
        $inventory = app(InventoryService::class);
        $inventory->postAdjustment($inventory->createAdjustment([
            'warehouse_id' => Warehouse::first()->id,
            'reason_id' => InventoryAdjustmentReason::where('code', 'PHYSICAL_COUNT_VARIANCE')->value('id'),
            'adjustment_date' => now()->toDateString(),
            'lines' => [['material_id' => $material->id, 'quantity' => 4, 'unit_cost' => 5, 'adjustment_type' => 'ADJUSTMENT_IN']],
        ], $this->admin->id), $this->admin);

        $this->actingAs($this->admin)
            ->get(route('inventory.lots.show', InventoryLot::where('material_id', $material->id)->firstOrFail()))
            ->assertOk();
    }
}
