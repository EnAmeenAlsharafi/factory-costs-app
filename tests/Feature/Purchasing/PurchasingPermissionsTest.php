<?php

namespace Tests\Feature\Purchasing;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasingUser;

    protected User $productionWorker;

    protected User $customerServiceUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $purchasingRole = Role::where('name', 'purchasing_user')->first();
        $workerRole = Role::where('name', 'production_worker')->first();
        $csRole = Role::where('name', 'customer_service')->first();

        $this->purchasingUser = User::factory()->create(['role_id' => $purchasingRole->id, 'is_active' => true]);
        $this->productionWorker = User::factory()->create(['role_id' => $workerRole->id, 'is_active' => true]);
        $this->customerServiceUser = User::factory()->create(['role_id' => $csRole->id, 'is_active' => true]);
    }

    public function test_purchasing_user_can_access_purchasing_module(): void
    {
        $this->actingAs($this->purchasingUser)
            ->get(route('purchasing.planning.index'))
            ->assertOk();

        $this->actingAs($this->purchasingUser)
            ->get(route('purchasing.requests.index'))
            ->assertOk();

        $this->actingAs($this->purchasingUser)
            ->get(route('purchasing.rfqs.index'))
            ->assertOk();

        $this->actingAs($this->purchasingUser)
            ->get(route('purchasing.quotations.index'))
            ->assertOk();

        $this->actingAs($this->purchasingUser)
            ->get(route('purchasing.orders.index'))
            ->assertOk();
    }

    public function test_unauthorized_users_receive_403_for_purchasing_routes(): void
    {
        // Production Worker
        $this->actingAs($this->productionWorker)
            ->get(route('purchasing.planning.index'))
            ->assertStatus(403);

        $this->actingAs($this->productionWorker)
            ->get(route('purchasing.orders.index'))
            ->assertStatus(403);

        // Customer Service
        $this->actingAs($this->customerServiceUser)
            ->get(route('purchasing.requests.index'))
            ->assertStatus(403);

        $this->actingAs($this->customerServiceUser)
            ->get(route('purchasing.orders.create'))
            ->assertStatus(403);
    }
}
