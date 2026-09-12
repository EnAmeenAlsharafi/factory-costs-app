<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test system administrator can access user management.
     */
    public function test_administrator_can_access_user_management(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee('إدارة المستخدمين والموظفين');
    }

    /**
     * Test unauthorized user cannot access user management.
     */
    public function test_unauthorized_user_cannot_access_user_management(): void
    {
        $warehouseKeeper = User::where('username', 'warehouse.keeper')->first();

        $response = $this->actingAs($warehouseKeeper)->get(route('users.index'));

        $response->assertStatus(403);
    }

    /**
     * Test customer service role permissions boundary:
     * Customer service must NOT have permission to approve production orders.
     */
    public function test_customer_service_cannot_approve_production(): void
    {
        $csUser = User::where('username', 'cs.agent')->first();

        // Customer service can create and update orders and request changes
        $this->assertTrue($csUser->hasPermission('orders.view'));
        $this->assertTrue($csUser->hasPermission('orders.create'));
        $this->assertTrue($csUser->hasPermission('orders.request_change'));

        // Customer service MUST NOT approve orders for production or release production
        $this->assertFalse($csUser->hasPermission('orders.approve_production'));
        $this->assertFalse($csUser->hasPermission('production.release'));
        $this->assertFalse($csUser->hasPermission('inventory.adjust'));
        $this->assertFalse($csUser->hasPermission('roles.manage'));
    }

    /**
     * Test warehouse keeper does not receive administration access.
     */
    public function test_warehouse_keeper_does_not_receive_admin_access(): void
    {
        $warehouseUser = User::where('username', 'warehouse.keeper')->first();

        $this->assertTrue($warehouseUser->hasPermission('inventory.view'));
        $this->assertTrue($warehouseUser->hasPermission('inventory.receive'));
        $this->assertTrue($warehouseUser->hasPermission('inventory.issue'));

        $this->assertFalse($warehouseUser->hasPermission('users.view'));
        $this->assertFalse($warehouseUser->hasPermission('users.create'));
        $this->assertFalse($warehouseUser->hasPermission('roles.manage'));
    }

    /**
     * Test production manager receives production and review permissions but not user administration.
     */
    public function test_production_manager_permissions(): void
    {
        $prodManager = User::where('username', 'prod.manager')->first();

        $this->assertTrue($prodManager->hasPermission('orders.review_production'));
        $this->assertTrue($prodManager->hasPermission('orders.approve_production'));
        $this->assertTrue($prodManager->hasPermission('production.release'));
        $this->assertTrue($prodManager->hasPermission('production.manage_rework'));
        $this->assertTrue($prodManager->hasPermission('production.material_requests'));
        $this->assertTrue($prodManager->hasPermission('production.manage_quality'));
        $this->assertTrue($prodManager->hasPermission('production.approve_waste'));

        $this->assertFalse($prodManager->hasPermission('users.create'));
        $this->assertFalse($prodManager->hasPermission('roles.manage'));
    }
}
