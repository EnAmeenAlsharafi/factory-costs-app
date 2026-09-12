<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::where('name', 'admin')->first();

        $this->user = User::factory()->create([
            'username' => 'sales_ord_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_can_list_customer_orders(): void
    {
        CustomerOrder::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.orders.index'));

        $response->assertStatus(200);
        $response->assertViewHas('orders');
    }

    public function test_can_create_customer_order(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'customer_reference' => 'PO-2026-88',
            'order_date' => '2026-09-15',
            'requested_delivery_date' => '2026-10-01',
            'priority' => 'NORMAL',
            'commercial_notes' => 'Urgent customer order',
            'lines' => [
                [
                    'custom_design' => 1,
                    'custom_design_name' => 'Custom Dining Table',
                    'requested_width_cm' => 100,
                    'requested_length_cm' => 200,
                    'quantity' => 1,
                    'unit_price' => 2500.00,
                    'notes' => 'Oak wood finish',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.orders.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_orders', [
            'customer_id' => $this->customer->id,
            'customer_reference' => 'PO-2026-88',
            'status' => 'DRAFT',
            'total_amount' => 2500.00,
        ]);

        $this->assertDatabaseHas('customer_order_lines', [
            'custom_design' => 1,
            'custom_design_name' => 'Custom Dining Table',
            'quantity' => 1,
            'unit_price' => 2500.00,
            'line_total' => 2500.00,
        ]);
    }

    public function test_can_submit_order_for_production_review(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.orders.submit-review', $order));

        $response->assertRedirect(route('sales.orders.show', $order));
        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);
    }

    public function test_can_cancel_customer_order(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.orders.cancel', $order));

        $response->assertRedirect(route('sales.orders.show', $order));
        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);
    }
}
