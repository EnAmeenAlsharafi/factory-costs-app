<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixedDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $driver;

    protected CustomerOrder $customerOrder;

    protected CustomerOrderLine $readyLine;

    protected Material $readyMaterial;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $managerRole = Role::where('name', 'production_manager')->first();
        $driverRole = Role::where('name', 'delivery_user')->first();

        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);
        $this->driver = User::factory()->create(['role_id' => $driverRole->id, 'is_active' => true]);

        $this->warehouse = Warehouse::where('code', 'READY_GOODS')->first();

        $customerType = CustomerType::firstOrCreate(['code' => 'COMMERCIAL'], ['name_ar' => 'عملاء جملة ومشاريع', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-MIX-001',
            'name' => 'عميل اختبار التوصيل المختلط',
            'customer_type_id' => $customerType->id,
            'phone' => '0555000111',
            'city' => 'الرياض',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(['code' => 'DIRECT'], ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]);

        $this->readyMaterial = Material::create([
            'code' => 'MAT-MIX-READY-0001',
            'name_ar' => 'مرتبة اختبار التوصيل',
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
            'sales_sku' => 'RDY-MIX-0001',
        ]);

        InventoryLot::create([
            'lot_code' => 'LOT-MIX-0001',
            'material_id' => $this->readyMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->subDay(),
            'original_quantity' => 5,
            'remaining_quantity' => 5,
            'base_unit_id' => $this->readyMaterial->base_unit_id,
            'unit_cost' => 100,
            'status' => 'ACTIVE',
        ]);

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-MIX-001',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_FULFILLMENT',
            'order_date' => now(),
            'total_amount' => 1000,
        ]);

        $this->readyLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'line_type' => 'READY_STOCK',
            'material_id' => $this->readyMaterial->id,
            'quantity' => 3,
            'unit_price' => 500,
            'line_total' => 1500,
        ]);
    }

    public function test_creating_a_delivery_for_a_ready_stock_only_order_succeeds(): void
    {
        $response = $this->actingAs($this->manager)->post(route('delivery.orders.store'), [
            'customer_order_id' => $this->customerOrder->id,
            'customer_name_snapshot' => 'عميل اختبار التوصيل المختلط',
            'action' => 'ready',
            'lines' => [
                ['line_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'customer_order_line_id' => $this->readyLine->id, 'quantity' => 3],
            ],
        ]);

        $response->assertRedirect();
        $delivery = DeliveryOrder::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('READY_FOR_DELIVERY', $delivery->status);
        $this->assertDatabaseHas('delivery_order_lines', [
            'delivery_order_id' => $delivery->id,
            'line_type' => 'READY_STOCK',
            'material_id' => $this->readyMaterial->id,
            'quantity' => 3,
        ]);
    }

    public function test_requesting_more_ready_stock_than_available_is_rejected(): void
    {
        $this->expectException(Exception::class);

        app(DeliveryOrderService::class)->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل',
            'lines' => [
                ['line_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'customer_order_line_id' => $this->readyLine->id, 'quantity' => 999],
            ],
        ]);
    }

    public function test_dispatching_a_ready_stock_line_deducts_the_inventory_lot_and_records_sales_dispatch(): void
    {
        $service = app(DeliveryOrderService::class);
        $delivery = $service->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل',
            'lines' => [
                ['line_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'customer_order_line_id' => $this->readyLine->id, 'quantity' => 3],
            ],
        ]);
        $service->markReady($delivery, $this->manager);
        $service->assignDriver($delivery, $this->driver, $this->manager);

        $service->dispatchDelivery($delivery, $this->driver);

        $this->assertEquals(2, InventoryLot::where('material_id', $this->readyMaterial->id)->first()->remaining_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'material_id' => $this->readyMaterial->id,
            'movement_type' => 'SALES_DISPATCH',
            'direction' => 'OUT',
            'quantity' => 3,
            'reference_type' => DeliveryOrderLine::class,
        ]);
    }

    public function test_failed_ready_stock_delivery_returns_inventory_to_the_same_lot(): void
    {
        $service = app(DeliveryOrderService::class);
        $delivery = $service->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل',
            'lines' => [
                ['line_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'customer_order_line_id' => $this->readyLine->id, 'quantity' => 3],
            ],
        ]);
        $service->markReady($delivery, $this->manager);
        $service->assignDriver($delivery, $this->driver, $this->manager);
        $service->dispatchDelivery($delivery, $this->driver);

        $service->failOrReschedule($delivery, $this->driver, 'RESCHEDULED', 'العميل غير متواجد', now()->addDays(2)->format('Y-m-d'), true);

        $lot = InventoryLot::where('material_id', $this->readyMaterial->id)->first();
        $this->assertEquals(5, $lot->remaining_quantity);
        $this->assertEquals('ACTIVE', $lot->status);
        $this->assertDatabaseHas('inventory_movements', [
            'material_id' => $this->readyMaterial->id,
            'movement_type' => 'SALES_RETURN',
            'direction' => 'IN',
            'quantity' => 3,
        ]);
        $this->assertEquals(2, InventoryMovement::where('material_id', $this->readyMaterial->id)->count());
    }

    public function test_mixed_order_delivery_creates_one_line_of_each_type(): void
    {
        $productModel = ProductModel::create(['model_code' => 'MOD-MIX-001', 'name_ar' => 'سرير اختبار مختلط', 'is_active' => true]);
        $manufacturedLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'line_type' => 'MANUFACTURED',
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 2000,
            'line_total' => 2000,
        ]);
        $productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-MIX-001',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $manufacturedLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 1,
            'released_quantity' => 1,
            'completed_quantity' => 1,
        ]);
        $fgService = app(FinishedGoodsService::class);
        $fgWarehouse = Warehouse::where('code', 'FINISHED_GOODS')->first();
        $receipt = $fgService->createReceipt($productionOrder, $this->manager, [
            'warehouse_id' => $fgWarehouse->id,
            'received_quantity' => 1,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->manager);

        $delivery = app(DeliveryOrderService::class)->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل',
            'lines' => [
                ['line_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'customer_order_line_id' => $this->readyLine->id, 'quantity' => 3],
                ['line_type' => 'MANUFACTURED', 'production_order_id' => $productionOrder->id, 'customer_order_line_id' => $manufacturedLine->id, 'quantity' => 1],
            ],
        ]);

        $this->assertEquals(2, $delivery->lines()->count());
        $this->assertEquals(1, $delivery->lines()->where('line_type', 'READY_STOCK')->count());
        $this->assertEquals(1, $delivery->lines()->where('line_type', 'MANUFACTURED')->count());
    }
}
