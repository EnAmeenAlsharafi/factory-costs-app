<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_to_login_for_all_master_data_routes(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
        $this->get(route('sales-channels.index'))->assertRedirect(route('login'));
        $this->get(route('customer-types.index'))->assertRedirect(route('login'));
        $this->get(route('units.index'))->assertRedirect(route('login'));
        $this->get(route('departments.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_create_or_modify_master_data(): void
    {
        // Production worker has no master data permissions
        $worker = User::where('username', 'worker.carpenter')->first();

        // Customer routes
        $this->actingAs($worker)->get(route('customers.index'))->assertStatus(403);
        $this->actingAs($worker)->get(route('customers.create'))->assertStatus(403);
        $this->actingAs($worker)->post(route('customers.store'), [])->assertStatus(403);

        // Supplier routes
        $this->actingAs($worker)->get(route('suppliers.index'))->assertStatus(403);
        $this->actingAs($worker)->get(route('suppliers.create'))->assertStatus(403);
        $this->actingAs($worker)->post(route('suppliers.store'), [])->assertStatus(403);

        // Sales channels routes
        $this->actingAs($worker)->get(route('sales-channels.index'))->assertStatus(403);
        $this->actingAs($worker)->get(route('sales-channels.create'))->assertStatus(403);

        // Customer types routes
        $this->actingAs($worker)->get(route('customer-types.index'))->assertStatus(403);
        $this->actingAs($worker)->get(route('customer-types.create'))->assertStatus(403);

        // Units routes
        $this->actingAs($worker)->get(route('units.index'))->assertStatus(403);
        $this->actingAs($worker)->get(route('units.create'))->assertStatus(403);

        // Departments management routes (worker can view departments list, but cannot manage/create)
        $this->actingAs($worker)->get(route('departments.create'))->assertStatus(403);
        $this->actingAs($worker)->post(route('departments.store'), [])->assertStatus(403);
    }
}
