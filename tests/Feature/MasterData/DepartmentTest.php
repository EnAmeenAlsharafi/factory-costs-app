<?php

namespace Tests\Feature\MasterData;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_departments_list_and_seeds_exist(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('departments.index'));

        $response->assertStatus(200);
        $response->assertViewIs('departments.index');
        $response->assertSee('CARPENTRY');
        $response->assertSee('UPHOLSTERY');
        $response->assertSee('PACKAGING');
        $response->assertSee('WAREHOUSE');
    }

    public function test_admin_can_create_new_production_department(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('departments.store'), [
            'code' => 'PAINTING',
            'name_ar' => 'قسم الدهان والطلاء',
            'name_en' => 'Painting & Finishing',
            'description' => 'دهان وتشطيب الأخشاب والأسرة',
            'sort_order' => 10,
            'is_production_department' => 1,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('departments.index'));
        $this->assertDatabaseHas('departments', [
            'code' => 'PAINTING',
            'name_ar' => 'قسم الدهان والطلاء',
            'is_production_department' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_update_department(): void
    {
        $admin = User::where('username', 'admin')->first();
        $dept = Department::where('code', 'CARPENTRY')->first();

        $response = $this->actingAs($admin)->put(route('departments.update', $dept), [
            'code' => 'CARPENTRY',
            'name_ar' => 'قسم النجارة وتفصيل الهياكل الخشبية',
            'sort_order' => 1,
            'is_production_department' => 1,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('departments.index'));
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name_ar' => 'قسم النجارة وتفصيل الهياكل الخشبية',
        ]);
    }

    public function test_admin_can_toggle_department_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $dept = Department::where('code', 'DELIVERY_INSTALLATION')->first();

        $this->assertTrue($dept->is_active);

        // Deactivate
        $this->actingAs($admin)->post(route('departments.toggle-status', $dept));
        $this->assertFalse($dept->fresh()->is_active);

        // Reactivate
        $this->actingAs($admin)->post(route('departments.toggle-status', $dept));
        $this->assertTrue($dept->fresh()->is_active);
    }

    public function test_users_can_be_assigned_to_departments(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'production_worker')->first();
        $carpentry = Department::where('code', 'CARPENTRY')->first();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'نجار ورشة النجارة',
            'username' => 'carpenter.worker',
            'email' => 'worker@sadir.com',
            'role_id' => $role->id,
            'department_id' => $carpentry->id,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => 'carpenter.worker',
            'department_id' => $carpentry->id,
        ]);

        $createdUser = User::where('username', 'carpenter.worker')->first();
        $this->assertEquals($carpentry->id, $createdUser->department->id);
    }
}
