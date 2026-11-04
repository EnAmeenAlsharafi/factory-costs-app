<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesBundleDefinition;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SallaValueNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBundleDefinitionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $unauthorizedUser;

    protected Material $readyMaterial;

    protected ProductConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id, 'is_active' => true]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);

        $this->readyMaterial = Material::create([
            'code' => 'MAT-BUNDLE-READY-0001',
            'name_ar' => 'طقم مفارش',
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        $model = ProductModel::create(['model_code' => 'MOD-BUNDLE-TEST-001', 'name_ar' => 'سرير اختبار البكج', 'is_active' => true]);
        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-BUNDLE-TEST-001',
            'product_model_id' => $model->id,
            'width_cm' => 160,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_view_bundles(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('sales.bundles.index'));

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_create_bundle(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->post(route('sales.bundles.store'), [
            'name' => 'بكج غير مصرح',
            'components' => [
                ['component_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'quantity' => 1],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_authorized_user_creates_bundle_with_mixed_components(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sales.bundles.store'), [
            'name' => 'بكج سرير وطقم',
            'sales_sku' => 'BND-TEST-0001',
            'components' => [
                ['component_type' => 'MANUFACTURED', 'product_model_id' => $this->configuration->product_model_id, 'product_configuration_id' => $this->configuration->id, 'quantity' => 1],
                ['component_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('sales.bundles.index'));
        $bundle = SalesBundleDefinition::where('name', 'بكج سرير وطقم')->first();
        $this->assertNotNull($bundle);
        $this->assertEquals(2, $bundle->components()->count());
        $this->assertEquals(SallaValueNormalizer::normalizeMatchValue('بكج سرير وطقم'), $bundle->match_name_normalized);
    }

    public function test_update_replaces_components_instead_of_appending(): void
    {
        $bundle = SalesBundleDefinition::create(['name' => 'بكج قبل التعديل', 'match_name_normalized' => 'بكج قبل التعديل', 'is_active' => true]);
        $bundle->components()->create(['component_type' => 'READY_STOCK', 'material_id' => $this->readyMaterial->id, 'quantity' => 1]);

        $response = $this->actingAs($this->admin)->put(route('sales.bundles.update', $bundle), [
            'name' => 'بكج بعد التعديل',
            'is_active' => '1',
            'components' => [
                ['component_type' => 'MANUFACTURED', 'product_model_id' => $this->configuration->product_model_id, 'product_configuration_id' => $this->configuration->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect(route('sales.bundles.index'));
        $bundle->refresh();
        $this->assertEquals(1, $bundle->components()->count());
        $this->assertEquals('MANUFACTURED', $bundle->components()->first()->component_type);
    }

    public function test_toggle_status_requires_manage_permission(): void
    {
        $bundle = SalesBundleDefinition::create(['name' => 'بكج للتبديل', 'match_name_normalized' => 'بكج للتبديل', 'is_active' => true]);

        $this->actingAs($this->unauthorizedUser)->post(route('sales.bundles.toggle-status', $bundle))->assertForbidden();

        $this->actingAs($this->admin)->post(route('sales.bundles.toggle-status', $bundle))->assertRedirect();
        $this->assertFalse($bundle->fresh()->is_active);
    }
}
