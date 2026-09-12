<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected CustomerOrder $approvedOrder;

    protected CustomerOrderLine $orderLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();

        $this->approvedOrder = CustomerOrder::create([
            'order_number' => 'ORD-2026-000100',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 5000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $material = Material::first();
        $color = FabricColor::first();

        $this->orderLine = CustomerOrderLine::create([
            'customer_order_id' => $this->approvedOrder->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'reference_width_cm' => 160,
            'reference_length_cm' => 200,
            'has_storage' => true,
            'fabric_material_id' => $material?->id,
            'fabric_color_id' => $color?->id,
            'quantity' => 10,
            'unit_price' => 500,
            'total_price' => 5000,
        ]);
    }

    public function test_cannot_create_production_order_from_unapproved_customer_order(): void
    {
        $unapprovedOrder = CustomerOrder::create([
            'order_number' => 'ORD-2026-000101',
            'customer_id' => $this->approvedOrder->customer_id,
            'sales_channel_id' => $this->approvedOrder->sales_channel_id,
            'status' => 'DRAFT',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $unapprovedLine = CustomerOrderLine::create([
            'customer_order_id' => $unapprovedOrder->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
        ]);

        $response = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $unapprovedLine->id,
            'released_quantity' => 2,
            'priority' => 'NORMAL',
        ]);

        $response->assertSessionHasErrors(['customer_order_id']);
        $this->assertDatabaseCount('production_orders', 0);
    }

    public function test_can_create_production_order_from_approved_customer_order(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $this->orderLine->id,
            'released_quantity' => 10,
            'priority' => 'NORMAL',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('production_orders', 1);

        $po = ProductionOrder::first();
        $this->assertEquals(160, (int) $po->requested_width_cm);
        $this->assertEquals(200, (int) $po->requested_length_cm);
        $this->assertTrue($po->has_storage);
        $this->assertEquals($this->orderLine->fabric_material_id, $po->fabric_material_id);
        $this->assertEquals($this->orderLine->fabric_color_id, $po->fabric_color_id);
        $this->assertEquals(10, $po->released_quantity);
    }

    public function test_custom_design_production_order_is_supported(): void
    {
        $customLine = CustomerOrderLine::create([
            'customer_order_id' => $this->approvedOrder->id,
            'item_number' => 2,
            'custom_design' => true,
            'custom_design_name' => 'سرير كابيتونيه خاص بالعميل',
            'requested_width_cm' => 200,
            'requested_length_cm' => 210,
            'quantity' => 1,
            'unit_price' => 3000,
            'total_price' => 3000,
        ]);

        $response = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $customLine->id,
            'released_quantity' => 1,
            'priority' => 'URGENT',
        ]);

        $response->assertRedirect();
        $po = ProductionOrder::where('is_custom_design', true)->first();
        $this->assertNotNull($po);
        $this->assertEquals('سرير كابيتونيه خاص بالعميل', $po->custom_design_name);
        $this->assertEquals('URGENT', $po->priority);
    }

    public function test_production_quantity_cannot_exceed_remaining_order_quantity(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.orders.store'), [
            'customer_order_line_id' => $this->orderLine->id,
            'released_quantity' => 15, // Max allowed is 10
            'priority' => 'NORMAL',
        ]);

        $response->assertSessionHasErrors(['released_quantity']);
    }
}
