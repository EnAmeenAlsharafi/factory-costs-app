<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\DeliveryOrder;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinishedGoodsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $unauthorizedUser;

    protected User $authorizedUser;

    protected ProductionOrder $productionOrder;

    protected CustomerOrder $customerOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $workerRole = Role::where('name', 'production_worker')->first();
        $managerRole = Role::where('name', 'production_manager')->first();

        $this->unauthorizedUser = User::factory()->create(['role_id' => $workerRole->id, 'is_active' => true]);
        $this->authorizedUser = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'RETAIL'],
            ['name_ar' => 'عملاء القطاعي', 'is_active' => true]
        );

        $customer = Customer::create([
            'customer_code' => 'CUST-PERM-001',
            'name' => 'عميل الصلاحيات',
            'customer_type_id' => $customerType->id,
            'phone' => '0599999999',
            'is_active' => true,
        ]);

        $productModel = ProductModel::create([
            'model_code' => 'PERM-TEST-01',
            'name_ar' => 'دولاب خشبي',
            'category' => 'WARDROBE',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'DIRECT'],
            ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]
        );

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9004',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->authorizedUser->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 3000,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'requested_height_cm' => 50,
            'quantity' => 1,
            'unit_price' => 3000,
            'total_price' => 3000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9004',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 1,
            'released_quantity' => 1,
            'completed_quantity' => 1,
        ]);
    }

    public function test_unauthorized_user_cannot_access_finished_goods_or_delivery(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('finished-goods.index'))
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->get(route('delivery.orders.index'))
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->get(route('customer-returns.index'))
            ->assertStatus(403);
    }

    public function test_authorized_user_can_access_finished_goods_and_delivery(): void
    {
        $this->actingAs($this->authorizedUser)
            ->get(route('finished-goods.index'))
            ->assertOk();

        $this->actingAs($this->authorizedUser)
            ->get(route('delivery.orders.index'))
            ->assertOk();

        $this->actingAs($this->authorizedUser)
            ->get(route('customer-returns.index'))
            ->assertOk();
    }

    public function test_delivery_user_permissions_and_restrictions(): void
    {
        $deliveryRole = Role::where('name', 'delivery_user')->first();
        $deliveryUser = User::factory()->create(['role_id' => $deliveryRole->id, 'is_active' => true]);

        // 1. Delivery User CAN access assigned deliveries, delivery list, and my-tasks
        $this->actingAs($deliveryUser)
            ->get(route('delivery.orders.index'))
            ->assertOk();

        $this->actingAs($deliveryUser)
            ->get(route('delivery.orders.my-tasks'))
            ->assertOk();

        // 2. Delivery User CANNOT access Raw Material Inventory (403)
        $this->actingAs($deliveryUser)
            ->get(route('inventory.receipts.index'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('inventory.issues.index'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('inventory.returns.index'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('inventory.adjustments.index'))
            ->assertStatus(403);

        // 3. Delivery User CANNOT access Costing Reports or Cost Analysis (403)
        $this->actingAs($deliveryUser)
            ->get(route('production.reports.cost'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('production.reports.waste'))
            ->assertStatus(403);

        // 4. Delivery User CANNOT access Manufacturing Admin / Recipes / Routings (403)
        $this->actingAs($deliveryUser)
            ->get(route('recipes.index'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('production.routings.index'))
            ->assertStatus(403);

        $this->actingAs($deliveryUser)
            ->get(route('recipes.create'))
            ->assertStatus(403);
    }

    public function test_delivery_dispatcher_can_assign_without_full_production_manager_powers(): void
    {
        $dispatcherRole = Role::where('name', 'delivery_dispatcher')->first();
        $dispatcher = User::factory()->create(['role_id' => $dispatcherRole->id, 'is_active' => true]);

        $deliveryRole = Role::where('name', 'delivery_user')->first();
        $driver = User::factory()->create(['role_id' => $deliveryRole->id, 'is_active' => true]);

        $warehouse = Warehouse::create(['code' => 'FG-PERM-TEST', 'name_ar' => 'مخزن منتجات جاهزة - اختبار', 'is_active' => true]);
        $fgService = app(FinishedGoodsService::class);
        $receipt = $fgService->createReceipt($this->productionOrder, $this->authorizedUser, [
            'warehouse_id' => $warehouse->id,
            'received_quantity' => 1,
            'receipt_date' => now()->format('Y-m-d'),
        ]);
        $fgService->postReceipt($receipt, $this->authorizedUser);

        $createResponse = $this->actingAs($this->authorizedUser)->post(route('delivery.orders.store'), [
            'customer_order_id' => $this->customerOrder->id,
            'customer_name_snapshot' => 'عميل الصلاحيات',
            'customer_phone_snapshot' => '0599999999',
            'scheduled_delivery_date' => now()->addDay()->format('Y-m-d'),
            'action' => 'ready',
            'lines' => [
                ['production_order_id' => $this->productionOrder->id, 'quantity' => 1],
            ],
        ]);
        $createResponse->assertSessionHasNoErrors();
        $delivery = DeliveryOrder::first();
        $this->assertNotNull($delivery, 'Delivery order was not created: '.session('error'));

        // Dispatcher CAN assign a delivery order to a driver.
        $this->actingAs($dispatcher)
            ->post(route('delivery.orders.assign', $delivery), ['assigned_user_id' => $driver->id])
            ->assertRedirect();

        $this->assertEquals($driver->id, $delivery->fresh()->assigned_user_id);

        // Dispatcher does NOT inherit full production_manager powers.
        $this->actingAs($dispatcher)
            ->get(route('recipes.index'))
            ->assertStatus(403);
    }
}
