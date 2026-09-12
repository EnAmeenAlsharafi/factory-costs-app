<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCostPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $worker;

    protected ProductionOrder $productionOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $workerRole = Role::where('name', 'production_worker')->first();

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->worker = User::factory()->create(['role_id' => $workerRole->id, 'is_active' => true]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000204',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->admin->id,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000204',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 1,
            'released_quantity' => 1,
            'status' => 'IN_PROGRESS',
        ]);
    }

    public function test_admin_with_costing_view_permission_sees_cost_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('production.reports.cost'));
        $response->assertOk();
        $response->assertSee('ر.س');
    }

    public function test_worker_without_costing_view_permission_cannot_access_cost_report(): void
    {
        $response = $this->actingAs($this->worker)->get(route('production.reports.cost'));
        $response->assertForbidden();
    }
}
