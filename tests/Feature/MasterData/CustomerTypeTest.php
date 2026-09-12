<?php

namespace Tests\Feature\MasterData;

use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_customer_types_list_and_seeds_exist(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('customer-types.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customer-types.index');
        $response->assertSee('DIRECT_CUSTOMER');
        $response->assertSee('WHOLESALE_CUSTOMER');
        $response->assertSee('SADIR_STORE');
        $response->assertSee('BUSINESS_CUSTOMER');
    }

    public function test_admin_can_create_customer_type(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('customer-types.store'), [
            'code' => 'GOVERNMENT',
            'name_ar' => 'قطاع حكومي وتعليمي',
            'name_en' => 'Government & Education',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customer-types.index'));
        $this->assertDatabaseHas('customer_types', [
            'code' => 'GOVERNMENT',
            'name_ar' => 'قطاع حكومي وتعليمي',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_customer_type(): void
    {
        $admin = User::where('username', 'admin')->first();
        $type = CustomerType::where('code', 'DIRECT_CUSTOMER')->first();

        $response = $this->actingAs($admin)->put(route('customer-types.update', $type), [
            'code' => 'DIRECT_CUSTOMER',
            'name_ar' => 'عميل تجزئة مباشر للأفراد',
            'name_en' => 'Direct Retail Customer',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customer-types.index'));
        $this->assertDatabaseHas('customer_types', [
            'id' => $type->id,
            'name_ar' => 'عميل تجزئة مباشر للأفراد',
        ]);
    }

    public function test_admin_can_toggle_customer_type_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $type = CustomerType::where('code', 'BUSINESS_CUSTOMER')->first();

        $this->assertTrue($type->is_active);

        // Deactivate
        $this->actingAs($admin)->post(route('customer-types.toggle-status', $type));
        $this->assertFalse($type->fresh()->is_active);

        // Reactivate
        $this->actingAs($admin)->post(route('customer-types.toggle-status', $type));
        $this->assertTrue($type->fresh()->is_active);
    }
}
