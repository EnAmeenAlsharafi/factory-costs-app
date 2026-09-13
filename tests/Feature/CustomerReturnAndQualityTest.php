<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerReturn;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\Department;
use App\Models\FinishedGoodsMovement;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnAndQualityTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $productionOrder;

    protected DeliveryOrder $deliveryOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $warehouse = Warehouse::create([
            'code' => 'FINISHED_GOODS',
            'name_ar' => 'مخزن المنتجات الجاهزة',
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'RETAIL'],
            ['name_ar' => 'عملاء القطاعي', 'is_active' => true]
        );

        $customer = Customer::create([
            'customer_code' => 'CUST-RET-001',
            'name' => 'عميل المرتجعات',
            'customer_type_id' => $customerType->id,
            'phone' => '0511111111',
            'is_active' => true,
        ]);

        $productModel = ProductModel::create([
            'model_code' => 'TABLE-DINING-01',
            'name_ar' => 'طاولة طعام خشب زان',
            'category' => 'TABLE',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'DIRECT'],
            ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]
        );

        $customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9003',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 8000,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'requested_height_cm' => 50,
            'quantity' => 2,
            'unit_price' => 4000,
            'total_price' => 8000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9003',
            'customer_order_id' => $customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 2,
            'released_quantity' => 2,
            'completed_quantity' => 2,
        ]);

        // Post receipt for 2 items
        $fgService = app(FinishedGoodsService::class);
        $receipt = $fgService->createReceipt($this->productionOrder, $this->manager, [
            'warehouse_id' => $warehouse->id,
            'received_quantity' => 2,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->manager);

        // Create & complete delivery order for 2 items
        $deliveryService = app(DeliveryOrderService::class);
        $this->deliveryOrder = $deliveryService->createDeliveryOrder($customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل المرتجعات',
            'customer_phone_snapshot' => '0511111111',
            'lines' => [
                ['production_order_id' => $this->productionOrder->id, 'quantity' => 2],
            ],
        ]);
        $deliveryService->dispatchDelivery($this->deliveryOrder, $this->manager);
        $deliveryService->markDelivered($this->deliveryOrder, $this->manager);
    }

    public function test_customer_return_flow_and_quality_linkage(): void
    {
        // 1. Report customer return for 1 table
        $storeResponse = $this->actingAs($this->manager)->post(route('customer-returns.store'), [
            'production_order_id' => $this->productionOrder->id,
            'delivery_order_id' => $this->deliveryOrder->id,
            'quantity_returned' => 1,
            'reason_code' => 'DEFECTIVE',
            'description' => 'وجود خدش عميق بطبقة الورنيش بالطاولة',
        ]);

        $storeResponse->assertRedirect();

        $customerReturn = CustomerReturn::first();
        $this->assertNotNull($customerReturn);
        $this->assertEquals('REPORTED', $customerReturn->status);

        // 2. Receive returned item into finished goods inventory
        $receiveResponse = $this->actingAs($this->manager)->post(route('customer-returns.receive', $customerReturn), [
            'condition_code' => 'NEEDS_INSPECTION',
        ]);

        $receiveResponse->assertRedirect();
        $customerReturn->refresh();
        $this->assertEquals('RECEIVED', $customerReturn->status);

        $this->assertDatabaseHas('finished_goods_movements', [
            'production_order_id' => $this->productionOrder->id,
            'customer_return_id' => $customerReturn->id,
            'movement_type' => 'CUSTOMER_RETURN',
            'direction' => 'IN',
            'quantity' => 1,
        ]);

        // 3. Create Quality Incident and Link
        $department = Department::firstOrCreate(
            ['code' => 'QC'],
            ['name_ar' => 'قسم ضبط الجودة', 'is_active' => true]
        );

        $incident = QualityIncident::create([
            'incident_number' => 'INC-2026-9001',
            'production_order_id' => $this->productionOrder->id,
            'customer_return_id' => $customerReturn->id,
            'detected_department_id' => $department->id,
            'incident_type' => 'DEFECT_REPORT',
            'severity' => 'MEDIUM',
            'status' => 'REPORTED',
            'description' => 'تضرر دهان الطاولة بسبب سوء التجهيز قبل الرش',
            'detected_by_user_id' => $this->manager->id,
            'reported_at' => now(),
        ]);

        $linkResponse = $this->actingAs($this->manager)->post(route('customer-returns.link-quality', $customerReturn), [
            'quality_incident_id' => $incident->id,
        ]);

        $linkResponse->assertRedirect();
        $customerReturn->refresh();
        $this->assertEquals('LINKED_TO_QUALITY', $customerReturn->status);
        $this->assertEquals($incident->id, $customerReturn->quality_incident_id);
    }

    public function test_customer_return_quantity_ceiling_scenario(): void
    {
        $customerType = CustomerType::firstOrCreate(['code' => 'RETAIL'], ['name_ar' => 'عملاء القطاعي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-RET-010',
            'name' => 'عميل سيناريو السقف',
            'customer_type_id' => $customerType->id,
            'phone' => '0599999999',
            'is_active' => true,
        ]);
        $productModel = ProductModel::create([
            'model_code' => 'CHAIR-OFFICE-10',
            'name_ar' => 'كرسي مكتب',
            'category' => 'CHAIR',
            'is_active' => true,
        ]);
        $salesChannel = SalesChannel::firstOrCreate(['code' => 'DIRECT'], ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]);
        $customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9010',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 10000,
        ]);
        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 100,
            'requested_length_cm' => 100,
            'requested_height_cm' => 50,
            'quantity' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
        ]);
        $po = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9010',
            'customer_order_id' => $customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 10,
            'released_quantity' => 10,
            'completed_quantity' => 10,
        ]);

        $fgWarehouse = Warehouse::where('code', 'FINISHED_GOODS')->first();
        $fgService = app(FinishedGoodsService::class);
        $receipt = $fgService->createReceipt($po, $this->manager, [
            'warehouse_id' => $fgWarehouse->id,
            'received_quantity' => 10,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->manager);

        $deliveryService = app(DeliveryOrderService::class);
        $delivery = $deliveryService->createDeliveryOrder($customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل سيناريو السقف',
            'customer_phone_snapshot' => '0599999999',
            'lines' => [
                ['production_order_id' => $po->id, 'quantity' => 10],
            ],
        ]);
        $deliveryService->dispatchDelivery($delivery, $this->manager);
        $deliveryService->markDelivered($delivery, $this->manager);

        // 1. Deliver quantity = 10 confirmed above. Net delivered = 10.
        $returnService = app(CustomerReturnService::class);
        $this->assertEquals(10.0, $returnService->getNetDeliveredQuantity($po));

        // 2. Create and physically receive Customer Return = 2
        $ret1 = $returnService->reportReturn($po, $this->manager, [
            'delivery_order_id' => $delivery->id,
            'quantity_returned' => 2,
            'reason_code' => 'DAMAGED_IN_TRANSIT',
        ]);
        $returnService->receiveReturnedGoods($ret1, $this->manager);
        $this->assertEquals('RECEIVED', $ret1->status);

        // 3. Confirm remaining eligible return quantity = 8
        $this->assertEquals(8.0, $returnService->getNetDeliveredQuantity($po));

        // 4. Attempt another Customer Return = 9 (Must be REJECTED)
        $failedAttempt = false;
        try {
            $returnService->reportReturn($po, $this->manager, [
                'delivery_order_id' => $delivery->id,
                'quantity_returned' => 9,
                'reason_code' => 'DEFECTIVE',
            ]);
        } catch (\Exception $e) {
            $failedAttempt = true;
            $this->assertStringContainsString('تتجاوز صافي الكمية المسلمة', $e->getMessage());
        }
        $this->assertTrue($failedAttempt, 'Return of 9 should be rejected when remaining returnable quantity is 8.');

        // 5. Attempt return exceeding precision rule = 8.0001 (Must be REJECTED)
        $failedPrecision = false;
        try {
            $returnService->reportReturn($po, $this->manager, [
                'delivery_order_id' => $delivery->id,
                'quantity_returned' => 8.0001,
                'reason_code' => 'DEFECTIVE',
            ]);
        } catch (\Exception $e) {
            $failedPrecision = true;
        }
        $this->assertTrue($failedPrecision, 'Return of 8.0001 should be rejected when remaining returnable quantity is 8.');

        // 6. Verify rejected attempts did not create any new movement records or change return totals
        $this->assertEquals(1, FinishedGoodsMovement::where('customer_return_id', $ret1->id)->count());
        $this->assertEquals(8.0, $returnService->getNetDeliveredQuantity($po));

        // 7. Return of exactly 8 after previous return of 2 = ALLOWED
        $ret2 = $returnService->reportReturn($po, $this->manager, [
            'delivery_order_id' => $delivery->id,
            'quantity_returned' => 8,
            'reason_code' => 'DEFECTIVE',
        ]);
        $returnService->receiveReturnedGoods($ret2, $this->manager);
        $this->assertEquals('RECEIVED', $ret2->status);
        $this->assertEquals(0.0, $returnService->getNetDeliveredQuantity($po));
    }
}
