<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderChangeHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::where('name', 'sales_user')->first() ?? Role::where('name', 'admin')->first();

        $this->user = User::factory()->create([
            'username' => 'sales_change_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_editing_approved_order_resets_status_and_logs_post_approval_change(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'production_approved_by_user_id' => $this->user->id,
            'production_approved_at' => now(),
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 100,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'line_total' => 1000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => '2026-09-15',
            'priority' => 'NORMAL',
            'change_notes' => 'Customer requested additional quantity after approval',
            'lines' => [
                [
                    'custom_design' => 1,
                    'custom_design_name' => 'Custom Item',
                    'requested_width_cm' => 100,
                    'requested_length_cm' => 200,
                    'quantity' => 2,
                    'unit_price' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('sales.orders.update', $order), $payload);

        $response->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);

        $this->assertDatabaseHas('customer_order_changes', [
            'customer_order_id' => $order->id,
            'requested_by_user_id' => $this->user->id,
            'occurred_after_production_approval' => true,
        ]);
    }
}
