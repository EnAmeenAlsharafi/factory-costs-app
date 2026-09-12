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

class SplitProductionOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected CustomerOrderLine $orderLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000200',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 10000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $this->orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 20,
            'unit_price' => 500,
            'total_price' => 10000,
        ]);
    }

    public function test_split_production_orders_for_single_order_line(): void
    {
        // 1. Release first PO for 12 units
        $res1 = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $this->orderLine->id,
            'released_quantity' => 12,
            'priority' => 'NORMAL',
        ]);
        $res1->assertRedirect();
        $this->assertDatabaseCount('production_orders', 1);

        // 2. Release second PO for 8 units
        $res2 = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $this->orderLine->id,
            'released_quantity' => 8,
            'priority' => 'NORMAL',
        ]);
        $res2->assertRedirect();
        $this->assertDatabaseCount('production_orders', 2);

        // Total released quantity is 20
        $alreadyReleased = ProductionOrder::where('customer_order_line_id', $this->orderLine->id)->sum('released_quantity');
        $this->assertEquals(20, $alreadyReleased);

        // 3. Attempt to release 3rd PO (0 remaining) -> should be rejected
        $res3 = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $this->orderLine->id,
            'released_quantity' => 1,
            'priority' => 'NORMAL',
        ]);
        $res3->assertSessionHasErrors(['released_quantity']);
        $this->assertDatabaseCount('production_orders', 2);
    }
}
