<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DepartmentRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Department $carpentry;

    protected Department $foam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin')->first();
        $this->carpentry = Department::where('code', 'CARPENTRY')->first();
        $this->foam = Department::where('code', 'FOAM')->first();
    }

    public function test_department_tab_lists_only_that_departments_roles(): void
    {
        $response = $this->actingAs($this->admin)->get(route('roles.index', ['department' => $this->carpentry->id]));

        $response->assertOk()
            ->assertSee('carpentry.supervisor')
            ->assertDontSee('foam.supervisor');
    }

    public function test_admin_creates_a_department_role_copying_another_roles_permissions(): void
    {
        $source = Role::where('name', 'carpentry.supervisor')->first();

        $response = $this->actingAs($this->admin)->post(route('roles.store'), [
            'department_id' => $this->carpentry->id,
            'display_name' => 'فني قص النجارة',
            'copy_from_role_id' => $source->id,
        ]);

        $role = Role::where('display_name', 'فني قص النجارة')->firstOrFail();
        $response->assertRedirect(route('roles.edit', $role));
        $this->assertSame($this->carpentry->id, $role->department_id);
        $this->assertEqualsCanonicalizing(
            $source->permissions()->pluck('permissions.id')->all(),
            $role->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_department_role_forms_offer_only_department_scoped_permissions(): void
    {
        $role = Role::where('name', 'carpentry.worker')->first();

        $this->actingAs($this->admin)->get(route('roles.create', ['department_id' => $this->carpentry->id]))
            ->assertOk()
            ->assertSee($role->display_name)
            ->assertViewHas('defaultCopyFromId', (string) $role->id);

        $this->actingAs($this->admin)->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('production.operation.claim')
            ->assertDontSee('production.release');
    }

    public function test_copying_a_role_from_another_department_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('roles.store'), [
            'department_id' => $this->carpentry->id,
            'display_name' => 'دور منسوخ',
            'copy_from_role_id' => Role::where('name', 'foam.worker')->value('id'),
        ])->assertSessionHasErrors(['copy_from_role_id' => 'يمكن النسخ من أدوار نفس القسم فقط.']);

        $this->assertDatabaseMissing('roles', ['display_name' => 'دور منسوخ']);
    }

    public function test_roles_cannot_be_created_for_a_non_production_department(): void
    {
        $this->actingAs($this->admin)->post(route('roles.store'), [
            'department_id' => Department::where('code', 'WAREHOUSE')->value('id'),
            'display_name' => 'دور مستودع',
        ])->assertSessionHasErrors(['department_id' => 'الأدوار المنفصلة متاحة للأقسام الإنتاجية فقط.']);
    }

    public function test_user_without_roles_manage_cannot_create_department_roles(): void
    {
        $customerService = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->value('id'), 'is_active' => true]);

        $this->actingAs($customerService)->post(route('roles.store'), [
            'department_id' => $this->carpentry->id,
            'display_name' => 'دور غير مصرح',
        ])->assertForbidden();
    }

    public function test_updating_a_department_role_drops_permissions_outside_the_department_scope(): void
    {
        $role = Role::where('name', 'carpentry.worker')->first();
        $view = Permission::where('name', 'production.view')->value('id');
        $release = Permission::where('name', 'production.release')->value('id');

        $this->actingAs($this->admin)->put(route('roles.update', $role), [
            'display_name' => 'عامل نجارة',
            'permissions' => [$view, $release],
        ])->assertRedirect(route('roles.index', ['department' => $this->carpentry->id]));

        $this->assertSame([$view], $role->permissions()->pluck('permissions.id')->all());
        $this->assertSame('عامل نجارة', $role->fresh()->display_name);
    }

    public function test_department_role_held_by_users_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'carpentry.worker')->first();
        User::factory()->create(['role_id' => $role->id, 'department_id' => $this->carpentry->id]);

        $this->actingAs($this->admin)->delete(route('roles.destroy', $role))->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_unused_department_role_can_be_deleted_but_global_roles_cannot(): void
    {
        $unused = Role::where('name', 'foam.supervisor')->first();
        $global = Role::where('name', 'sales_user')->first();

        $this->actingAs($this->admin)->delete(route('roles.destroy', $unused))->assertRedirect();
        $this->actingAs($this->admin)->delete(route('roles.destroy', $global))->assertSessionHas('error');

        $this->assertModelMissing($unused);
        $this->assertModelExists($global);
    }

    public function test_user_cannot_be_given_another_departments_role(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'عامل',
            'username' => 'mismatch.worker',
            'role_id' => Role::where('name', 'carpentry.worker')->value('id'),
            'department_id' => $this->foam->id,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['role_id' => 'الدور المختار يخص قسماً آخر، اختر دوراً من أدوار قسم الموظف.']);

        $this->assertDatabaseMissing('users', ['username' => 'mismatch.worker']);
    }

    public function test_legacy_production_workers_move_to_their_departments_worker_role(): void
    {
        $legacy = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->value('id'),
            'department_id' => $this->foam->id,
        ]);

        app(DepartmentRoleService::class)->migrateLegacyWorkers();

        $this->assertSame('foam.worker', $legacy->fresh()->role->name);
    }

    public function test_new_production_department_gets_default_roles(): void
    {
        $this->actingAs($this->admin)->post(route('departments.store'), [
            'code' => 'PAINT',
            'name_ar' => 'الدهان',
            'is_production_department' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('departments.index'));

        $department = Department::where('code', 'PAINT')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['paint.worker', 'paint.supervisor'],
            Role::forDepartment($department->id)->pluck('name')->all()
        );
    }
}
