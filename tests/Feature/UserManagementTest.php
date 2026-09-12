<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test administrator can create a new user.
     */
    public function test_administrator_can_create_new_user(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'production_worker')->first();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'نجار جديد',
            'username' => 'new.carpenter',
            'email' => 'carpenter@sadir-factory.com',
            'role_id' => $role->id,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => 'new.carpenter',
            'name' => 'نجار جديد',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test administrator can deactivate a user and deactivated user cannot login.
     */
    public function test_administrator_can_deactivate_user_and_deactivated_cannot_login(): void
    {
        $admin = User::where('username', 'admin')->first();
        $targetUser = User::where('username', 'cs.agent')->first();

        $this->assertTrue($targetUser->is_active);

        // Deactivate target user
        $response = $this->actingAs($admin)->post(route('users.toggle-status', $targetUser));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => false,
        ]);

        // Attempt login as newly deactivated user
        $this->post('/logout');

        $loginResponse = $this->post('/login', [
            'username' => 'cs.agent',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $loginResponse->assertSessionHasErrors('username');
    }

    /**
     * Test administrator cannot deactivate their own currently logged-in account.
     */
    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('users.toggle-status', $admin));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test updating user permissions matrix for a role.
     */
    public function test_administrator_can_update_role_permissions(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'sales_user')->first();

        $response = $this->actingAs($admin)->put(route('roles.update', $role), [
            'permissions' => [],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertCount(0, $role->fresh()->permissions);
    }
}
