<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_environment_may_create_development_users_and_demo_customer(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['username' => 'admin']);
        $this->assertDatabaseHas('customers', ['customer_code' => 'CUS-000001']);
    }

    public function test_production_environment_does_not_create_development_users_or_demo_customer(): void
    {
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->seedProductionReferences();

            $this->assertDatabaseMissing('users', ['username' => 'admin']);
            $this->assertDatabaseMissing('users', ['username' => 'prod.manager']);
            $this->assertDatabaseMissing('customers', ['customer_code' => 'CUS-000001']);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_production_safe_seeders_are_idempotent_and_preserve_custom_assignments_and_passwords(): void
    {
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->seedProductionReferences();
            $role = Role::where('name', 'production_manager')->firstOrFail();
            $customPermission = Permission::create([
                'name' => 'custom.factory.permission',
                'display_name' => 'صلاحية مصنع مخصصة',
                'module' => 'custom',
            ]);
            $role->permissions()->attach($customPermission);
            $user = User::factory()->create([
                'username' => 'secure.production.user',
                'role_id' => $role->id,
                'password' => Hash::make('unique-secret-value'),
                'is_active' => true,
            ]);
            $counts = [
                Role::count(),
                Permission::count(),
                \DB::table('sales_channels')->count(),
                \DB::table('warehouses')->count(),
            ];

            $this->seedProductionReferences();

            $this->assertSame($counts, [
                Role::count(),
                Permission::count(),
                \DB::table('sales_channels')->count(),
                \DB::table('warehouses')->count(),
            ]);
            $this->assertTrue($role->fresh()->permissions()->whereKey($customPermission->id)->exists());
            $this->assertTrue(Hash::check('unique-secret-value', $user->fresh()->password));
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    private function seedProductionReferences(): void
    {
        $this->assertSame(0, Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]));
    }
}
