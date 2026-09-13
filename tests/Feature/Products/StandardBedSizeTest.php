<?php

namespace Tests\Feature\Products;

use App\Models\Role;
use App\Models\StandardBedSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardBedSizeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $csUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_size_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $csRole = Role::where('name', 'customer_service')->first();
        $this->csUser = User::factory()->create([
            'username' => 'cs_size_test',
            'role_id' => $csRole->id,
            'is_active' => true,
        ]);
    }

    public function test_can_view_standard_bed_sizes(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('products.sizes.index'));

        $response->assertOk();
        $response->assertSee('160 × 200 سم');
    }

    public function test_admin_can_create_new_standard_bed_size(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('products.sizes.store'), [
                'code' => 'SIZE-220X200',
                'name_ar' => '220 × 200 سم (جامبو)',
                'name_en' => '220 x 200 cm Jumbo',
                'width_cm' => 220.0,
                'length_cm' => 200.0,
                'sort_order' => 7,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('products.sizes.index'));

        $this->assertDatabaseHas('standard_bed_sizes', [
            'code' => 'SIZE-220X200',
            'width_cm' => 220.0,
            'length_cm' => 200.0,
        ]);
    }

    public function test_admin_can_toggle_standard_bed_size_status(): void
    {
        $size = StandardBedSize::where('code', 'SIZE-160X200')->first();
        $this->assertTrue((bool) $size->is_active);

        $response = $this->actingAs($this->adminUser)
            ->post(route('products.sizes.toggle-status', $size));

        $response->assertRedirect();
        $this->assertFalse((bool) $size->fresh()->is_active);
    }

    public function test_admin_can_update_standard_bed_size(): void
    {
        $size = StandardBedSize::where('code', 'SIZE-160X200')->first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('products.sizes.update', $size), [
                'code' => 'SIZE-160X200-UPDATED',
                'name_ar' => 'مزدوج كوين معدل',
                'width_cm' => 165.0,
                'length_cm' => 205.0,
                'sort_order' => 15,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('products.sizes.index'));

        $this->assertDatabaseHas('standard_bed_sizes', [
            'id' => $size->id,
            'code' => 'SIZE-160X200-UPDATED',
            'name_ar' => 'مزدوج كوين معدل',
            'width_cm' => 165.0,
            'length_cm' => 205.0,
            'sort_order' => 15,
        ]);
    }

    public function test_can_sort_standard_bed_sizes(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('products.sizes.index', ['sort' => 'width_cm', 'direction' => 'desc']));

        $response->assertOk();
    }
}
