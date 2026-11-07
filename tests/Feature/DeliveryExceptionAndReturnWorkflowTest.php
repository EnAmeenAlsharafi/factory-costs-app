<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerReturn;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\Department;
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

/**
 * Door outcomes, management decisions after a failed delivery, the inspection area, and the return lifecycle.
 */
class DeliveryExceptionAndReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $driver;

    protected CustomerOrder $customerOrder;

    protected ProductionOrder $productionOrder;

    protected FinishedGoodsService $finishedGoods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->value('id'), 'is_active' => true]);
        $this->driver = User::factory()->create(['role_id' => Role::where('name', 'delivery_user')->value('id'), 'is_active' => true]);
        Department::create(['code' => 'DELIVERY_INSTALLATION', 'name_ar' => 'التوصيل والتركيب', 'is_active' => true]);

        $warehouse = Warehouse::create(['code' => 'FINISHED_GOODS', 'name_ar' => 'مخزن المنتجات الجاهزة', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-EXC-001',
            'name' => 'عميل الاستثناءات',
            'customer_type_id' => CustomerType::firstOrCreate(['code' => 'COMMERCIAL'], ['name_ar' => 'عملاء جملة', 'is_active' => true])->id,
            'phone' => '0555000111',
            'is_active' => true,
        ]);
        $productModel = ProductModel::create(['model_code' => 'BED-EXC-01', 'name_ar' => 'سرير روما', 'category' => 'BED', 'is_active' => true]);

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9901',
            'customer_id' => $customer->id,
            'sales_channel_id' => SalesChannel::firstOrCreate(['code' => 'DIRECT'], ['name_ar' => 'مبيعات مباشرة', 'is_active' => true])->id,
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 6000,
        ]);
        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 2,
            'unit_price' => 3000,
            'total_price' => 6000,
        ]);
        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9901',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'product_model_id' => $productModel->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 2,
            'released_quantity' => 2,
            'completed_quantity' => 2,
        ]);

        $this->finishedGoods = app(FinishedGoodsService::class);
        $receipt = $this->finishedGoods->createReceipt($this->productionOrder, $this->manager, [
            'warehouse_id' => $warehouse->id,
            'received_quantity' => 2,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $this->finishedGoods->postReceipt($receipt, $this->manager);
    }

    public function test_refusal_for_a_defect_sends_goods_to_inspection_and_opens_a_quality_incident(): void
    {
        $delivery = $this->dispatchedDelivery();

        $this->actingAs($this->driver)->post(route('delivery.orders.fail-or-reschedule', $delivery), [
            'new_status' => 'FAILED',
            'reason_code' => 'REFUSED_DEFECT',
            'reason' => 'خدش في الظهر',
            'returned_to_factory' => '1',
        ])->assertSessionHasNoErrors();

        $delivery->refresh();
        $this->assertSame('FAILED', $delivery->status);
        $this->assertSame('REFUSED_DEFECT', $delivery->failure_reason_code);
        $this->assertSame('QUARANTINE', $delivery->goods_location);
        $this->assertTrue($delivery->isAwaitingDecision());
        $this->assertEquals(1.0, $this->finishedGoods->getQuarantinedQuantity($this->productionOrder));
        $this->assertEquals(1.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));
        $this->assertSame(1, QualityIncident::where('production_order_id', $this->productionOrder->id)->where('incident_type', 'WORKMANSHIP_DEFECT')->count());
    }

    public function test_other_reason_requires_details(): void
    {
        $delivery = $this->dispatchedDelivery();

        $this->actingAs($this->driver)->post(route('delivery.orders.fail-or-reschedule', $delivery), [
            'new_status' => 'FAILED',
            'reason_code' => 'OTHER',
        ])->assertSessionHasErrors('reason');

        $this->assertSame('OUT_FOR_DELIVERY', $delivery->fresh()->status);
    }

    public function test_manager_reschedules_a_failed_delivery_as_a_new_ready_order(): void
    {
        $delivery = $this->failedDelivery('CUSTOMER_UNAVAILABLE');
        $this->assertSame('FACTORY', $delivery->goods_location);
        $this->assertEquals(2.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $response = $this->actingAs($this->manager)->post(route('delivery.orders.reschedule-as-new', $delivery), [
            'scheduled_delivery_date' => now()->addDays(2)->toDateString(),
        ]);

        $successor = DeliveryOrder::whereKeyNot($delivery->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('delivery.orders.show', $successor));
        $this->assertSame('READY_FOR_DELIVERY', $successor->status);
        $this->assertEquals(1.0, (float) $successor->lines()->sum('quantity'));
        $this->assertSame($successor->id, $delivery->fresh()->successor_delivery_order_id);
        $this->assertFalse($delivery->fresh()->isAwaitingDecision());
    }

    public function test_goods_left_with_driver_must_be_confirmed_back_before_a_decision(): void
    {
        $delivery = $this->failedDelivery('CUSTOMER_UNAVAILABLE', returned: false);
        $this->assertSame('WITH_DRIVER', $delivery->goods_location);
        $this->assertEquals(1.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('delivery.orders.cancel-after-failure', $delivery), ['reason' => 'ألغى العميل'])
            ->assertSessionHas('error', 'البضاعة ما زالت مع فريق التوصيل. أكّد عودتها للمصنع أولاً.');
        $this->assertSame('FAILED', $delivery->fresh()->status);

        $this->actingAs($this->manager)->post(route('delivery.orders.confirm-goods-returned', $delivery))->assertSessionHasNoErrors();
        $this->assertSame('FACTORY', $delivery->fresh()->goods_location);
        $this->assertEquals(2.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('delivery.orders.cancel-after-failure', $delivery), ['reason' => 'ألغى العميل'])
            ->assertSessionHas('success');
        $this->assertSame('CANCELLED', $delivery->fresh()->status);
    }

    public function test_driver_cannot_take_management_decisions(): void
    {
        $delivery = $this->failedDelivery('CUSTOMER_UNAVAILABLE');

        $this->actingAs($this->driver)->post(route('delivery.orders.reschedule-as-new', $delivery), [
            'scheduled_delivery_date' => now()->addDay()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($this->driver)->post(route('delivery.orders.cancel-after-failure', $delivery), ['reason' => 'x'])->assertForbidden();

        $this->assertTrue($delivery->fresh()->isAwaitingDecision());
    }

    public function test_quarantined_goods_cannot_be_rescheduled_until_inspection_releases_them(): void
    {
        $delivery = $this->failedDelivery('REFUSED_DEFECT');
        // The other unit is already out on another delivery, so only the inspected unit could be re-delivered.
        $this->dispatchedDelivery();
        $this->assertEquals(0.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('delivery.orders.reschedule-as-new', $delivery), [
            'scheduled_delivery_date' => now()->addDay()->toDateString(),
        ])->assertSessionHas('error');
        $this->assertNull($delivery->fresh()->successor_delivery_order_id);

        $this->actingAs($this->manager)->post(route('finished-goods.quarantine.release', $this->productionOrder), [
            'quantity' => 1,
            'notes' => 'لا يوجد عيب بعد الفحص',
        ])->assertSessionHas('success');
        $this->assertEquals(0.0, $this->finishedGoods->getQuarantinedQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('delivery.orders.reschedule-as-new', $delivery), [
            'scheduled_delivery_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($delivery->fresh()->successor_delivery_order_id);
    }

    public function test_delivery_records_the_recipient_name(): void
    {
        $delivery = $this->dispatchedDelivery();

        $this->actingAs($this->driver)->post(route('delivery.orders.complete', $delivery), ['recipient_name' => 'أحمد ابن العميل'])
            ->assertSessionHas('success');

        $this->assertSame('DELIVERED', $delivery->fresh()->status);
        $this->assertSame('أحمد ابن العميل', $delivery->fresh()->recipient_name);
    }

    public function test_received_return_is_not_sellable_until_inspection_restocks_it(): void
    {
        $return = $this->deliveredAndReported();

        $this->actingAs($this->manager)->post(route('customer-returns.approve', $return))->assertSessionHas('success');
        $this->actingAs($this->manager)->post(route('customer-returns.receive', $return), ['condition_code' => 'NEEDS_INSPECTION'])->assertSessionHas('success');

        $this->assertEquals(1.0, $this->finishedGoods->getQuarantinedQuantity($this->productionOrder));
        $this->assertEquals(1.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('customer-returns.inspect', $return), ['decision' => 'RESTOCK'])->assertSessionHas('success');
        $this->assertSame('INSPECTED', $return->fresh()->status);
        $this->assertEquals(2.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('customer-returns.resolve', $return), ['resolution_code' => 'REPLACEMENT'])->assertSessionHas('success');
        $this->assertSame('RESOLVED', $return->fresh()->status);
        $this->assertSame('REPLACEMENT', $return->fresh()->resolution_code);
    }

    public function test_scrapped_return_never_reaches_sellable_stock(): void
    {
        $return = $this->deliveredAndReported();
        app(CustomerReturnService::class)->receiveReturnedGoods($return, $this->manager);

        $this->actingAs($this->manager)->post(route('customer-returns.inspect', $return), ['decision' => 'SCRAP'])->assertSessionHas('success');

        $this->assertEquals(0.0, $this->finishedGoods->getQuarantinedQuantity($this->productionOrder));
        $this->assertEquals(1.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));
    }

    public function test_repaired_return_is_restocked_when_the_repair_completes(): void
    {
        $return = $this->deliveredAndReported();
        app(CustomerReturnService::class)->receiveReturnedGoods($return, $this->manager);

        $this->actingAs($this->manager)->post(route('customer-returns.inspect', $return), ['decision' => 'REPAIR']);
        $this->assertSame('UNDER_REPAIR', $return->fresh()->status);
        $this->assertEquals(1.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));

        $this->actingAs($this->manager)->post(route('customer-returns.complete-repair', $return))->assertSessionHas('success');
        $this->assertSame('INSPECTED', $return->fresh()->status);
        $this->assertEquals(2.0, $this->finishedGoods->getAvailableQuantity($this->productionOrder));
    }

    public function test_return_cannot_be_inspected_before_it_is_received(): void
    {
        $return = $this->deliveredAndReported();

        $this->actingAs($this->manager)->post(route('customer-returns.inspect', $return), ['decision' => 'RESTOCK'])
            ->assertSessionHas('error');
        $this->assertSame('REPORTED', $return->fresh()->status);
    }

    public function test_rejected_return_is_closed_with_its_reason(): void
    {
        $return = $this->deliveredAndReported();

        $this->actingAs($this->manager)->post(route('customer-returns.reject', $return), ['reason' => 'خارج مدة الضمان'])->assertSessionHas('success');

        $this->assertSame('REJECTED', $return->fresh()->status);
        $this->assertStringContainsString('خارج مدة الضمان', $return->fresh()->resolution_notes);
    }

    public function test_exception_return_and_inspection_screens_show_the_next_decision(): void
    {
        $failed = $this->failedDelivery('REFUSED_DEFECT');

        $this->actingAs($this->manager)->get(route('delivery.orders.show', $failed))
            ->assertOk()
            ->assertSee('رفض الاستلام: عيب ظاهر في المنتج')
            ->assertSee('في منطقة الفحص بانتظار قرار')
            ->assertSee('إعادة جدولة');

        $this->actingAs($this->manager)->get(route('delivery.orders.index', ['status' => 'AWAITING_DECISION']))
            ->assertOk()
            ->assertSee($failed->delivery_number);

        $this->actingAs($this->manager)->get(route('finished-goods.quarantine'))
            ->assertOk()
            ->assertSee($this->productionOrder->production_order_number);

        $return = $this->deliveredAndReported();
        $this->actingAs($this->manager)->get(route('customer-returns.show', $return))
            ->assertOk()
            ->assertSee('مسار المرتجع')
            ->assertSee('اعتماد الإرجاع');

        $incident = QualityIncident::where('production_order_id', $this->productionOrder->id)->firstOrFail();
        app(CustomerReturnService::class)->linkQualityIncident($return, $incident);
        $this->actingAs($this->manager)->get(route('customer-returns.show', $return))
            ->assertOk()
            ->assertSee(route('production.quality-incidents.show', $incident), false);
    }

    private function dispatchedDelivery(): DeliveryOrder
    {
        $service = app(DeliveryOrderService::class);
        $delivery = $service->createDeliveryOrder($this->customerOrder, $this->manager, [
            'lines' => [['production_order_id' => $this->productionOrder->id, 'quantity' => 1]],
        ]);
        $service->markReady($delivery, $this->manager);
        $service->assignDriver($delivery->fresh(), $this->driver, $this->manager);

        return $service->dispatchDelivery($delivery->fresh(), $this->driver)->fresh();
    }

    private function failedDelivery(string $reasonCode, bool $returned = true): DeliveryOrder
    {
        $delivery = $this->dispatchedDelivery();

        return app(DeliveryOrderService::class)
            ->failOrReschedule($delivery, $this->driver, 'FAILED', null, null, $returned, $reasonCode)
            ->fresh();
    }

    private function deliveredAndReported(): CustomerReturn
    {
        $delivery = $this->dispatchedDelivery();
        app(DeliveryOrderService::class)->markDelivered($delivery, $this->driver);

        return app(CustomerReturnService::class)->reportReturn($this->productionOrder, $this->manager, [
            'quantity' => 1,
            'reason_code' => 'PRODUCT_DEFECT',
            'delivery_order_id' => $delivery->id,
        ]);
    }
}
