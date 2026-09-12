<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationConversionTest extends TestCase
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
            'username' => 'sales_conv_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_can_convert_approved_quotation_to_customer_order(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'APPROVED',
            'total_amount' => 5000.00,
        ]);

        QuotationLine::create([
            'quotation_id' => $quotation->id,
            'custom_design' => true,
            'custom_design_name' => 'Custom Wardrobe',
            'requested_width_cm' => 240,
            'requested_length_cm' => 220,
            'quantity' => 1,
            'unit_price' => 5000.00,
            'line_total' => 5000.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.quotations.convert', $quotation));

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'CONVERTED',
        ]);

        $quotation->refresh();
        $this->assertNotNull($quotation->customerOrder);
        $createdOrder = $quotation->customerOrder;

        $this->assertDatabaseHas('customer_orders', [
            'id' => $createdOrder->id,
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'status' => 'DRAFT',
            'total_amount' => 5000.00,
        ]);

        $this->assertDatabaseHas('customer_order_lines', [
            'customer_order_id' => $createdOrder->id,
            'custom_design' => true,
            'custom_design_name' => 'Custom Wardrobe',
            'requested_width_cm' => 240,
            'requested_length_cm' => 220,
            'quantity' => 1,
            'unit_price' => 5000.00,
            'line_total' => 5000.00,
        ]);

        $response->assertRedirect(route('sales.orders.show', $createdOrder));
    }

    public function test_cannot_convert_draft_quotation(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.quotations.convert', $quotation));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'DRAFT',
        ]);
    }

    public function test_converted_quotation_cannot_create_a_second_customer_order(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'APPROVED',
            'total_amount' => 1000,
        ]);
        QuotationLine::create([
            'quotation_id' => $quotation->id,
            'custom_design' => true,
            'custom_design_name' => 'منتج اختبار',
            'requested_width_cm' => 100,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        $this->actingAs($this->user)->post(route('sales.quotations.convert', $quotation))->assertRedirect();
        $firstOrderId = $quotation->fresh()->customerOrder->id;

        $this->post(route('sales.quotations.convert', $quotation->fresh()))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('customer_orders', 1);
        $this->assertSame($firstOrderId, $quotation->fresh()->customerOrder->id);
    }
}
