<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_production_worker_sees_only_production_task_tiles(): void
    {
        $worker = User::where('username', 'worker.carpenter')->firstOrFail();

        $response = $this->actingAs($worker)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-dashboard-tile="production_tasks"', false)
            ->assertSee('مهام بانتظار البدء')
            ->assertDontSee('data-dashboard-tile="receivables"', false)
            ->assertDontSee('data-dashboard-tile="purchasing"', false)
            ->assertDontSee('data-dashboard-tile="delivery"', false)
            ->assertDontSee('أرصدة متأخرة السداد');
    }

    public function test_delivery_user_sees_delivery_tiles_linking_to_filtered_tasks(): void
    {
        $driver = User::where('username', 'delivery.driver')->firstOrFail();

        $response = $this->actingAs($driver)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-dashboard-tile="delivery"', false)
            ->assertSee(route('delivery.orders.my-tasks', ['filter' => 'out']), false)
            ->assertDontSee('data-dashboard-tile="production_tasks"', false)
            ->assertDontSee('data-dashboard-tile="receivables"', false);
    }

    public function test_receivables_user_sees_pending_payment_count(): void
    {
        $receivablesUser = User::factory()->create([
            'role_id' => Role::where('name', 'receivables_user')->firstOrFail()->id,
            'is_active' => true,
        ]);
        $customerId = Customer::firstOrFail()->id;
        foreach (['PAY-QA-1', 'PAY-QA-2'] as $number) {
            CustomerPayment::create([
                'payment_number' => $number,
                'customer_id' => $customerId,
                'payment_date' => now()->toDateString(),
                'amount' => 100,
                'payment_method' => 'CASH',
                'status' => 'PENDING_CONFIRMATION',
                'created_by_user_id' => $receivablesUser->id,
            ]);
        }

        $response = $this->actingAs($receivablesUser)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-dashboard-tile="receivables"', false)
            ->assertSeeInOrder(['2', 'دفعات بانتظار التأكيد'])
            ->assertDontSee('data-dashboard-tile="production_tasks"', false);
    }

    public function test_master_data_counts_are_limited_to_permitted_records(): void
    {
        $worker = User::where('username', 'worker.carpenter')->firstOrFail();

        $response = $this->actingAs($worker)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee(route('departments.index'), false)
            ->assertDontSee(route('customers.index'), false)
            ->assertDontSee(route('suppliers.index'), false);
    }
}
