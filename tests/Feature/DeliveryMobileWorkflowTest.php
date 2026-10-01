<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\Permission;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $driver;

    protected CustomerOrder $customerOrder;

    protected ProductionOrder $productionOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->first()->id, 'is_active' => true]);
        $this->driver = User::factory()->create(['role_id' => Role::where('name', 'delivery_user')->first()->id, 'is_active' => true]);

        $warehouse = Warehouse::create(['code' => 'FINISHED_GOODS', 'name_ar' => 'مخزن المنتجات الجاهزة', 'is_active' => true]);
        $customerType = CustomerType::firstOrCreate(['code' => 'COMMERCIAL'], ['name_ar' => 'عملاء جملة', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-MOB-001',
            'name' => 'عميل التوصيل',
            'customer_type_id' => $customerType->id,
            'phone' => '0555123456',
            'is_active' => true,
        ]);
        $productModel = ProductModel::create(['model_code' => 'BED-MOB-01', 'name_ar' => 'سرير ميلان', 'category' => 'BED', 'is_active' => true]);
        $salesChannel = SalesChannel::firstOrCreate(['code' => 'DIRECT'], ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]);

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9816',
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
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 4,
            'unit_price' => 3000,
            'total_price' => 12000,
        ]);
        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9816',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'product_model_id' => $productModel->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 4,
            'released_quantity' => 4,
            'completed_quantity' => 4,
        ]);

        $fgService = app(FinishedGoodsService::class);
        $receipt = $fgService->createReceipt($this->productionOrder, $this->manager, [
            'warehouse_id' => $warehouse->id,
            'received_quantity' => 4,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->manager);
    }

    public function test_driver_cannot_mark_draft_delivery_ready(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->driver)->post(route('delivery.orders.ready', $delivery))->assertForbidden();

        $this->assertSame('DRAFT', $delivery->fresh()->status);
    }

    public function test_manager_marks_draft_delivery_ready(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->manager)->from(route('delivery.orders.show', $delivery))
            ->post(route('delivery.orders.ready', $delivery))
            ->assertRedirect(route('delivery.orders.show', $delivery))
            ->assertSessionHas('success', 'تم تحويل أمر التوصيل إلى جاهز للتوصيل.');

        $this->assertSame('READY_FOR_DELIVERY', $delivery->fresh()->status);
    }

    public function test_my_tasks_shows_start_delivery_only_once_assigned(): void
    {
        $delivery = $this->createDelivery();
        app(DeliveryOrderService::class)->markReady($delivery, $this->manager);
        $delivery->update(['assigned_user_id' => $this->driver->id]);

        $this->actingAs($this->driver)->get(route('delivery.orders.my-tasks'))
            ->assertOk()
            ->assertSee('بانتظار إسناد المشرف')
            ->assertDontSee(route('delivery.orders.dispatch', $delivery), false);

        app(DeliveryOrderService::class)->assignDriver($delivery->fresh(), $this->driver, $this->manager);

        $this->actingAs($this->driver)->get(route('delivery.orders.my-tasks'))
            ->assertOk()
            ->assertSee(route('delivery.orders.dispatch', $delivery), false)
            ->assertSee('بدء التوصيل');
    }

    public function test_my_tasks_filters_scope_to_the_drivers_own_assignments(): void
    {
        $otherDriver = User::factory()->create(['role_id' => $this->driver->role_id, 'is_active' => true]);
        $mine = $this->assignedDelivery($this->driver);
        $theirs = $this->assignedDelivery($otherDriver);

        $response = $this->actingAs($this->driver)->get(route('delivery.orders.my-tasks', ['filter' => 'assigned']));

        $response->assertOk();
        $this->assertSame([$mine->id], $response->viewData('myDeliveries')->pluck('id')->all());
        $this->assertSame(1, $response->viewData('filterCounts')['assigned']);
        $this->assertNotContains($theirs->id, $response->viewData('myDeliveries')->pluck('id')->all());
    }

    public function test_delivery_detail_hides_payment_amounts_from_driver(): void
    {
        $delivery = $this->assignedDelivery($this->driver);

        $this->actingAs($this->driver)->get(route('delivery.orders.show', $delivery))
            ->assertOk()
            ->assertSee('التسليم موقوف — راجع قسم التحصيل قبل الانطلاق')
            ->assertDontSee('يتطلب سداد كامل المتبقي')
            ->assertDontSee('12,000.00');
    }

    public function test_delivery_detail_shows_payment_reason_to_receivables_user(): void
    {
        $receivablesUser = User::factory()->create(['role_id' => Role::where('name', 'receivables_user')->first()->id, 'is_active' => true]);
        $receivablesUser->role->permissions()->syncWithoutDetaching(
            Permission::where('name', 'delivery.view')->pluck('id')
        );
        $delivery = $this->assignedDelivery($this->driver);

        $this->actingAs($receivablesUser)->get(route('delivery.orders.show', $delivery))
            ->assertOk()
            ->assertSee('يتطلب سداد كامل المتبقي');
    }

    public function test_driver_order_view_hides_prices_and_receivables(): void
    {
        $this->actingAs($this->driver)->get(route('sales.orders.show', $this->customerOrder))
            ->assertOk()
            ->assertSee('سرير ميلان')
            ->assertDontSee('الدفعات والتحصيل ومتابعة المستحقات')
            ->assertDontSee('12,000.00')
            ->assertDontSee('3,000.00');

        $this->actingAs($this->driver)->get(route('sales.orders.index'))
            ->assertOk()
            ->assertSee('SO-2026-9816')
            ->assertDontSee('12,000.00');
    }

    public function test_production_manager_order_view_keeps_commercial_values(): void
    {
        $this->actingAs($this->manager)->get(route('sales.orders.show', $this->customerOrder))
            ->assertOk()
            ->assertSee('الدفعات والتحصيل ومتابعة المستحقات')
            ->assertSee('12,000.00');
    }

    public function test_reschedule_keeps_goods_out_when_return_to_factory_is_unticked(): void
    {
        $delivery = $this->assignedDelivery($this->driver);
        app(DeliveryOrderService::class)->dispatchDelivery($delivery, $this->driver);

        $this->actingAs($this->driver)->post(route('delivery.orders.fail-or-reschedule', $delivery), [
            'new_status' => 'RESCHEDULED',
            'reason' => 'العميل طلب التأجيل',
            'scheduled_delivery_date' => now()->addDays(2)->toDateString(),
            'returned_to_factory' => '0',
        ])->assertRedirect();

        $this->assertSame('RESCHEDULED', $delivery->fresh()->status);
        $this->assertSame(0, FinishedGoodsMovement::where('delivery_order_id', $delivery->id)->where('movement_type', 'DELIVERY_RETURN')->count());
    }

    public function test_invalid_transition_message_uses_arabic_status_labels(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->manager)->from(route('delivery.orders.show', $delivery))
            ->post(route('delivery.orders.complete', $delivery))
            ->assertSessionHas('error', 'انتقال حالة التوصيل من «مسودة» إلى «تم التسليم» غير مسموح. قد تكون الحالة تغيّرت — حدّث الصفحة.');
    }

    private function createDelivery(): DeliveryOrder
    {
        return app(DeliveryOrderService::class)->createDeliveryOrder($this->customerOrder, $this->manager, [
            'customer_name_snapshot' => 'عميل التوصيل',
            'customer_phone_snapshot' => '0555123456',
            'lines' => [['production_order_id' => $this->productionOrder->id, 'quantity' => 1]],
        ]);
    }

    private function assignedDelivery(User $driver): DeliveryOrder
    {
        $service = app(DeliveryOrderService::class);
        $delivery = $this->createDelivery();
        $service->markReady($delivery, $this->manager);

        return $service->assignDriver($delivery->fresh(), $driver, $this->manager);
    }
}
