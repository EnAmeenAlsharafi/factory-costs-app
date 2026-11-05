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

    public function test_create_edit_and_import_forms_are_accessible_from_the_list(): void
    {
        $this->actingAs($this->admin)->get(route('sales.ready-stock-products.index'))
            ->assertSee(route('sales.ready-stock-products.create'))
            ->assertSee(route('sales.ready-stock-products.import'))
            ->assertSee(route('sales.ready-stock-products.edit', $this->readyMaterial));
        $this->get(route('sales.ready-stock-products.create'))->assertSee('إضافة منتج جاهز يدويًا');
        $this->get(route('sales.ready-stock-products.edit', $this->readyMaterial))->assertSee('RDY-LIST-0001');
        $this->get(route('sales.ready-stock-products.import'))->assertSee('رفع ومعاينة المنتجات');
    }

    public function test_manual_entry_creates_a_ready_product_without_stock(): void
    {
        $this->actingAs($this->admin)->post(route('sales.ready-stock-products.store'), $this->productData())
            ->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['name_ar' => 'مخدة جديدة', 'salla_product_id' => '1932559050', 'sales_sku' => 'NEW-SKU', 'is_sellable_ready_product' => true]);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_edit_preserves_material_identity_and_base_unit(): void
    {
        $data = $this->productData();
        $data['base_unit_id'] = UnitOfMeasure::where('id', '!=', $this->readyMaterial->base_unit_id)->first()->id;

        $this->actingAs($this->admin)->put(route('sales.ready-stock-products.update', $this->readyMaterial), $data)
            ->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['id' => $this->readyMaterial->id, 'code' => $this->readyMaterial->code, 'base_unit_id' => $this->readyMaterial->base_unit_id, 'sales_sku' => 'NEW-SKU']);
    }

    public function test_duplicate_sku_and_salla_identity_are_rejected(): void
    {
        $this->readyMaterial->update(['salla_product_id' => '1932559050']);
        $data = $this->productData();
        $data['sales_sku'] = $this->readyMaterial->sales_sku;

        $this->actingAs($this->admin)->post(route('sales.ready-stock-products.store'), $data)
            ->assertSessionHasErrors(['sales_sku', 'salla_product_id']);

        $this->assertDatabaseMissing('materials', ['name_ar' => 'مخدة جديدة']);
    }

    public function test_invalid_product_data_is_rejected(): void
    {
        $data = array_merge($this->productData(), ['name_ar' => '', 'sales_price' => -1, 'salla_product_id' => 'not-an-id']);

        $this->actingAs($this->admin)->post(route('sales.ready-stock-products.store'), $data)
            ->assertSessionHasErrors(['name_ar', 'sales_price', 'salla_product_id']);
    }

    public function test_array_identifiers_are_rejected_as_validation_errors(): void
    {
        $data = array_merge($this->productData(), ['sales_sku' => ['BAD'], 'salla_product_id' => ['123']]);

        $this->actingAs($this->admin)->post(route('sales.ready-stock-products.store'), $data)
            ->assertSessionHasErrors(['sales_sku', 'salla_product_id']);

        $this->assertDatabaseMissing('materials', ['name_ar' => 'مخدة جديدة']);
    }

    public function test_worker_cannot_create_edit_or_import_products(): void
    {
        $this->actingAs($this->unauthorizedUser);
        $this->get(route('sales.ready-stock-products.create'))->assertForbidden();
        $this->get(route('sales.ready-stock-products.edit', $this->readyMaterial))->assertForbidden();
        $this->get(route('sales.ready-stock-products.import'))->assertForbidden();
        $this->post(route('sales.ready-stock-products.store'), $this->productData())->assertForbidden();
        $this->put(route('sales.ready-stock-products.update', $this->readyMaterial), $this->productData())->assertForbidden();
        $this->get(route('sales.ready-stock-products.import-preview'))->assertForbidden();
        $this->post(route('sales.ready-stock-products.import-upload'))->assertForbidden();
        $this->post(route('sales.ready-stock-products.import-confirm'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sales.ready-stock-products.create'))->assertRedirect(route('login'));
    }

    public function test_search_finds_salla_number_and_escapes_product_names(): void
    {
        $this->readyMaterial->update(['salla_product_id' => '1932559050', 'sales_display_name' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin)->get(route('sales.ready-stock-products.index', ['search' => '1932559050']))
            ->assertSee('1932559050')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @return array<string, mixed> */
    protected function productData(): array
    {
        return ['name_ar' => 'مخدة جديدة', 'sales_sku' => 'NEW-SKU', 'salla_product_id' => '1932559050', 'base_unit_id' => UnitOfMeasure::where('code', 'PIECE')->first()->id, 'sales_price' => 150, 'salla_stock_quantity' => 10, 'is_active' => 1];
    }
}
