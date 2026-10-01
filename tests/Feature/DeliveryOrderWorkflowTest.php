<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $driver;

    protected CustomerOrder $customerOrder;

    protected ProductionOrder $productionOrder;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $driverRole = Role::where('name', 'delivery_user')->first();

        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);
        $this->driver = User::factory()->create(['role_id' => $driverRole->id, 'name' => 'السائق علي', 'is_active' => true]);

        $this->warehouse = Warehouse::create([
            'code' => 'FINISHED_GOODS',
            'name_ar' => 'مخزن المنتجات الجاهزة',
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'COMMERCIAL'],
            ['name_ar' => 'عملاء جملة ومشاريع', 'is_active' => true]
        );

        $customer = Customer::create([
            'customer_code' => 'CUST-DEL-001',
            'name' => 'شركة الفنادق المتحدون',
            'customer_type_id' => $customerType->id,
            'phone' => '0555123456',
            'city' => 'الرياض',
            'district' => 'العليا',
            'address' => 'شارع الملك فهد برج 12',
            'is_active' => true,
        ]);

        $productModel = ProductModel::create([
            'model_code' => 'SOFA-3S-01',
            'name_ar' => 'كنبة ثلاثة مقاعد فاخرة',
            'category' => 'SOFA',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'DIRECT'],
            ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]
        );

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9002',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 12000,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'requested_height_cm' => 50,
            'quantity' => 4,
            'unit_price' => 3000,
            'total_price' => 12000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9002',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 4,
            'released_quantity' => 4,
            'completed_quantity' => 4,
        ]);

        // Post finished goods receipt to have 4 items available
        $fgService = app(FinishedGoodsService::class);
        $receipt = $fgService->createReceipt($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'received_quantity' => 4,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->manager);
    }

    public function test_full_successful_delivery_and_installation_lifecycle(): void
    {
        // 1. Create delivery order with address snapshot
        $createResponse = $this->actingAs($this->manager)->post(route('delivery.orders.store'), [
            'customer_order_id' => $this->customerOrder->id,
            'customer_name_snapshot' => 'شركة الفنادق المتحدون',
            'customer_phone_snapshot' => '0555123456',
            'city_snapshot' => 'الرياض',
            'district_snapshot' => 'العليا',
            'delivery_address_snapshot' => 'شارع الملك فهد برج 12',
            'assigned_user_id' => $this->driver->id,
            'scheduled_delivery_date' => now()->addDay()->format('Y-m-d'),
            'action' => 'ready',
            'lines' => [
                ['production_order_id' => $this->productionOrder->id, 'quantity' => 4],
            ],
        ]);

        $createResponse->assertRedirect();
        $delivery = DeliveryOrder::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('READY_FOR_DELIVERY', $delivery->status);

        $this->actingAs($this->manager)->post(route('delivery.orders.assign', $delivery), [
            'assigned_user_id' => $this->driver->id,
        ])->assertRedirect();

        // 2. Driver checks task list
        $myTasksResponse = $this->actingAs($this->driver)->get(route('delivery.orders.my-tasks'));
        $myTasksResponse->assertOk()->assertSee($delivery->delivery_number);

        // 3. Dispatch delivery order (stock lock & OUT movement)
        $dispatchResponse = $this->actingAs($this->driver)->post(route('delivery.orders.dispatch', $delivery));
        $dispatchResponse->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('OUT_FOR_DELIVERY', $delivery->status);

        $this->assertDatabaseHas('finished_goods_movements', [
            'production_order_id' => $this->productionOrder->id,
            'delivery_order_id' => $delivery->id,
            'movement_type' => 'DELIVERY_DISPATCH',
            'direction' => 'OUT',
            'quantity' => 4,
        ]);

        // 4. Mark Delivered
        $completeResponse = $this->actingAs($this->driver)->post(route('delivery.orders.complete', $delivery), [
            'notes' => 'تم إنزال الطرود وإرسال بيان الاستلام',
        ]);
        $completeResponse->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('DELIVERED', $delivery->status);

        // 5. Mark Installed
        $installResponse = $this->actingAs($this->driver)->post(route('delivery.orders.install', $delivery), [
            'notes' => 'تم تركيب الكنب والتشغيل النهائي',
        ]);
        $installResponse->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('INSTALLATION_COMPLETED', $delivery->status);
    }

    public function test_failed_delivery_returns_stock_to_finished_goods_inventory(): void
    {
        // Create & dispatch delivery
        $delivery = DeliveryOrder::create([
            'delivery_number' => 'DEL-2026-9099',
            'customer_order_id' => $this->customerOrder->id,
            'customer_id' => $this->customerOrder->customer_id,
            'customer_name_snapshot' => 'عميل مؤجل',
            'customer_phone_snapshot' => '0500000000',
            'city_snapshot' => 'الرياض',
            'assigned_user_id' => $this->driver->id,
            'status' => 'ASSIGNED',
            'created_by_user_id' => $this->manager->id,
        ]);

        $delivery->lines()->create([
            'production_order_id' => $this->productionOrder->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->driver)->post(route('delivery.orders.dispatch', $delivery));

        // Fail delivery & return to factory stock
        $failResponse = $this->actingAs($this->driver)->post(route('delivery.orders.fail-or-reschedule', $delivery), [
            'new_status' => 'RESCHEDULED',
            'reason' => 'العميل غير متواجد والموقع مغلق',
            'scheduled_delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'returned_to_factory' => 1,
        ]);

        $failResponse->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('RESCHEDULED', $delivery->status);

        // Verify stock return IN movement was created
        $this->assertDatabaseHas('finished_goods_movements', [
            'production_order_id' => $this->productionOrder->id,
            'delivery_order_id' => $delivery->id,
            'movement_type' => 'DELIVERY_RETURN',
            'direction' => 'IN',
            'quantity' => 2,
        ]);
    }

    public function test_delivery_cannot_jump_from_draft_to_delivered(): void
    {
        $delivery = $this->createDraftDelivery();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('غير مسموح');

        try {
            app(DeliveryOrderService::class)->markDelivered($delivery, $this->manager);
        } finally {
            $this->assertSame('DRAFT', $delivery->fresh()->status);
        }
    }

    public function test_delivery_dispatch_is_idempotent_with_stale_requests(): void
    {
        $service = app(DeliveryOrderService::class);
        $delivery = $this->createDraftDelivery();
        $service->markReady($delivery, $this->manager);
        $service->assignDriver($delivery, $this->driver, $this->manager);
        $staleDelivery = DeliveryOrder::findOrFail($delivery->id);

        $service->dispatchDelivery($delivery, $this->driver);

        try {
            $service->dispatchDelivery($staleDelivery, $this->driver);
            $this->fail('A stale second dispatch attempt must be rejected.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('غير مسموح', $exception->getMessage());
        }

        $this->assertSame(1, FinishedGoodsMovement::where('delivery_order_id', $delivery->id)
            ->where('movement_type', 'DELIVERY_DISPATCH')
            ->count());
    }

    public function test_delivery_rejects_production_order_from_another_customer_order(): void
    {
        $otherOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-CROSS',
            'customer_id' => $this->customerOrder->customer_id,
            'sales_channel_id' => $this->customerOrder->sales_channel_id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
        ]);
        $otherLine = CustomerOrderLine::create([
            'customer_order_id' => $otherOrder->id,
            'requested_width_cm' => 100,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 100,
        ]);
        $otherProductionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-CROSS',
            'customer_order_id' => $otherOrder->id,
            'customer_order_line_id' => $otherLine->id,
            'released_quantity' => 1,
            'completed_quantity' => 1,
            'status' => 'COMPLETED',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('لا ينتمي');

        app(DeliveryOrderService::class)->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل',
            'lines' => [
                ['production_order_id' => $otherProductionOrder->id, 'quantity' => 1],
            ],
        ]);
    }

    private function createDraftDelivery(): DeliveryOrder
    {
        return app(DeliveryOrderService::class)->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'شركة الفنادق المتحدون',
            'customer_phone_snapshot' => '0555123456',
            'lines' => [
                ['production_order_id' => $this->productionOrder->id, 'quantity' => 2],
            ],
        ]);
    }
}
