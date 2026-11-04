<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyStockProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $unauthorizedUser;

    protected Material $readyMaterial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id, 'is_active' => true]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);

        $this->readyMaterial = Material::create([
            'code' => 'MAT-READY-LIST-0001',
            'name_ar' => 'مرتبة اختبار القائمة',
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
            'sales_sku' => 'RDY-LIST-0001',
            'sales_display_name' => 'مرتبة فاخرة',
        ]);
    }

    public function test_unauthorized_user_cannot_view_ready_stock_products(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('sales.ready-stock-products.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_sees_sellable_ready_materials_with_availability(): void
    {
        $response = $this->actingAs($this->admin)->get(route('sales.ready-stock-products.index'));

        $response->assertOk();
        $response->assertSee('مرتبة فاخرة');
        $response->assertSee('RDY-LIST-0001');
    }

    public function test_non_sellable_materials_are_excluded_from_the_list(): void
    {
        Material::create([
            'code' => 'MAT-NOT-READY-0001',
            'name_ar' => 'مادة غير معروضة للبيع',
            'material_category_id' => MaterialCategory::first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.ready-stock-products.index'));

        $response->assertOk();
        $response->assertDontSee('مادة غير معروضة للبيع');
    }
}
