<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
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
            'username' => 'sales_quo_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_can_list_quotations(): void
    {
        Quotation::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.quotations.index'));

        $response->assertStatus(200);
        $response->assertViewHas('quotations');
    }

    public function test_can_create_quotation_with_lines(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'quotation_date' => '2026-09-15',
            'valid_until' => '2026-10-15',
            'notes' => 'Test quotation notes',
            'lines' => [
                [
                    'custom_design' => 1,
                    'custom_design_name' => 'Custom Bed Headboard',
                    'requested_width_cm' => 180,
                    'requested_length_cm' => 200,
                    'quantity' => 2,
                    'unit_price' => 1500.00,
                    'notes' => 'Line 1 note',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.quotations.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('quotations', [
            'customer_id' => $this->customer->id,
            'status' => 'DRAFT',
            'total_amount' => 3000.00,
        ]);

        $this->assertDatabaseHas('quotation_lines', [
            'custom_design' => 1,
            'custom_design_name' => 'Custom Bed Headboard',
            'quantity' => 2,
            'unit_price' => 1500.00,
            'line_total' => 3000.00,
        ]);
    }

    public function test_can_approve_draft_quotation(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.quotations.approve', $quotation));

        $response->assertRedirect(route('sales.quotations.show', $quotation));
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'APPROVED',
        ]);
    }

    public function test_can_reject_draft_quotation(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.quotations.reject', $quotation));

        $response->assertRedirect(route('sales.quotations.show', $quotation));
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'REJECTED',
        ]);
    }
}
