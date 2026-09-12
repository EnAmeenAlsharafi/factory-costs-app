<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_sales_user_without_review_permission_cannot_approve_production(): void
    {
        $salesRole = Role::where('name', 'sales_user')->first();

        $salesUser = User::factory()->create([
            'username' => 'sales_no_review_user',
            'role_id' => $salesRole->id,
            'is_active' => true,
        ]);

        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);

        $response = $this->actingAs($salesUser)->get(route('sales.orders.review', $order));
        $response->assertStatus(403);

        $response = $this->actingAs($salesUser)->post(route('sales.orders.approve-production', $order), []);
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('sales.quotations.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('sales.orders.index'));
        $response->assertRedirect(route('login'));
    }
}
