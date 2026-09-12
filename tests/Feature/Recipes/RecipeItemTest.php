<?php

namespace Tests\Feature\Recipes;

use App\Models\ManufacturingRecipeItem;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected ProductConfiguration $configuration;

    protected Material $fabricMaterial;

    protected Material $inactiveMaterial;

    protected UnitOfMeasure $meterUnit;

    protected UnitOfMeasure $kilogramUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_item_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-RIVA-100',
            'name_ar' => 'ريڤا',
            'is_active' => true,
        ]);

        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-RIVA-180',
            'product_model_id' => $model->id,
            'width_cm' => 180.00,
            'length_cm' => 200.00,
            'has_storage' => true,
            'is_active' => true,
        ]);

        $this->meterUnit = UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::create([
            'code' => 'METER', 'name_ar' => 'متر', 'allows_decimal' => true, 'is_active' => true,
        ]);

        $this->kilogramUnit = UnitOfMeasure::where('code', 'KG')->first() ?? UnitOfMeasure::create([
            'code' => 'KG', 'name_ar' => 'كيلوجرام', 'allows_decimal' => true, 'is_active' => true,
        ]);

        $category = MaterialCategory::first();

        $this->fabricMaterial = Material::create([
            'code' => 'MAT-FABRIC-001',
            'material_category_id' => $category->id,
            'name_ar' => 'قماش مخمل تركيا',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $this->inactiveMaterial = Material::create([
            'code' => 'MAT-OLD-001',
            'material_category_id' => $category->id,
            'name_ar' => 'خامة قديمة ملغاة',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => false,
        ]);
    }

    public function test_recipe_item_planned_quantity_calculated_correctly_with_waste(): void
    {
        $item = new ManufacturingRecipeItem([
            'quantity' => 14.0000,
            'waste_percentage' => 5.00,
        ]);

        // Planned = 14 * 1.05 = 14.7
        $this->assertEqualsWithDelta(14.7000, $item->planned_quantity, 0.0001);
    }

    public function test_duplicate_material_in_same_recipe_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة مكررة الخامة',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->fabricMaterial->id,
                        'quantity' => 10.00,
                        'unit_id' => $this->meterUnit->id,
                    ],
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->fabricMaterial->id,
                        'quantity' => 4.50,
                        'unit_id' => $this->meterUnit->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_adding_inactive_material_to_new_recipe_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة بخامة معطلة',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->inactiveMaterial->id,
                        'quantity' => 5.00,
                        'unit_id' => $this->meterUnit->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_unit_incompatibility_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة بوحدة غير متوافقة',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->fabricMaterial->id,
                        'quantity' => 5.00,
                        'unit_id' => $this->kilogramUnit->id, // Fabric is METER, KG is incompatible without conversion
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_recipe_references_fabric_material_only_without_fabric_color_dependency(): void
    {
        // Verified: Fabric materials in recipes do not require color attachment
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة قماش بدون لون صلب',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->fabricMaterial->id,
                        'quantity' => 14.50,
                        'unit_id' => $this->meterUnit->id,
                        'waste_percentage' => 5.0,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('manufacturing_recipe_items', [
            'material_id' => $this->fabricMaterial->id,
            'quantity' => 14.5000,
        ]);
    }
}
