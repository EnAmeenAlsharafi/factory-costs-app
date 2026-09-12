<?php

namespace Tests\Feature\Recipes;

use App\Models\ManufacturingRecipe;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SemiFinishedComponent;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemiFinishedComponentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected SemiFinishedComponent $boxStandard;

    protected SemiFinishedComponent $boxStorage;

    protected ProductConfiguration $configStandard;

    protected ProductConfiguration $configStorage;

    protected Material $woodMaterial;

    protected UnitOfMeasure $boardUnit;

    protected UnitOfMeasure $pieceUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_comp_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-MILAN-001',
            'name_ar' => 'ميلان',
            'is_active' => true,
        ]);

        $this->configStandard = ProductConfiguration::create([
            'configuration_code' => 'CFG-MILAN-160-STD',
            'product_model_id' => $model->id,
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->configStorage = ProductConfiguration::create([
            'configuration_code' => 'CFG-MILAN-160-STR',
            'product_model_id' => $model->id,
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => true,
            'is_active' => true,
        ]);

        $this->boxStandard = SemiFinishedComponent::create([
            'component_code' => 'SFC-BOX-160-STD',
            'name_ar' => 'بوكس 160×200 عادي',
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->boxStorage = SemiFinishedComponent::create([
            'component_code' => 'SFC-BOX-160-STR',
            'name_ar' => 'بوكس 160×200 سحارة',
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => true,
            'is_active' => true,
        ]);

        $this->boardUnit = UnitOfMeasure::where('code', 'BOARD')->first() ?? UnitOfMeasure::create([
            'code' => 'BOARD', 'name_ar' => 'لوح', 'allows_decimal' => true, 'is_active' => true,
        ]);

        $this->pieceUnit = UnitOfMeasure::where('code', 'PCS')->first() ?? UnitOfMeasure::create([
            'code' => 'PCS', 'name_ar' => 'قطعة', 'allows_decimal' => false, 'is_active' => true,
        ]);

        $category = MaterialCategory::first();

        $this->woodMaterial = Material::create([
            'code' => 'MAT-WOOD-002',
            'material_category_id' => $category->id,
            'name_ar' => 'خشب ابلكاش 18 ملم',
            'base_unit_id' => $this->boardUnit->id,
            'is_active' => true,
        ]);
    }

    public function test_can_create_semi_finished_component(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.components.store'), [
                'name_ar' => 'بوكس 180×200 عادي',
                'name_en' => 'Bed Box 180x200 Standard',
                'width_cm' => 180.00,
                'length_cm' => 200.00,
                'has_storage' => false,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('recipes.components.index'));

        $this->assertDatabaseHas('semi_finished_components', [
            'name_ar' => 'بوكس 180×200 عادي',
            'width_cm' => 180.00,
            'length_cm' => 200.00,
            'has_storage' => false,
        ]);
    }

    public function test_recipe_can_contain_semi_finished_component_and_differ_for_storage_config(): void
    {
        // 1. Create Standard Bed Recipe using Standard Box Component
        $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configStandard->id,
                'name' => 'وصفة ميلان 160×200 بدون تخزين',
                'items' => [
                    [
                        'item_type' => 'SEMI_FINISHED_COMPONENT',
                        'semi_finished_component_id' => $this->boxStandard->id,
                        'quantity' => 1.00,
                        'unit_id' => $this->pieceUnit->id,
                    ],
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->woodMaterial->id,
                        'quantity' => 2.00,
                        'unit_id' => $this->boardUnit->id,
                    ],
                ],
            ]);

        $stdRecipe = ManufacturingRecipe::where('product_configuration_id', $this->configStandard->id)->first();
        $this->assertNotNull($stdRecipe);
        $this->assertEquals(1, $stdRecipe->versions->first()->items()->where('item_type', 'SEMI_FINISHED_COMPONENT')->where('semi_finished_component_id', $this->boxStandard->id)->count());

        // 2. Create Storage Bed Recipe using Storage Box Component
        $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configStorage->id,
                'name' => 'وصفة ميلان 160×200 سحارة',
                'items' => [
                    [
                        'item_type' => 'SEMI_FINISHED_COMPONENT',
                        'semi_finished_component_id' => $this->boxStorage->id,
                        'quantity' => 1.00,
                        'unit_id' => $this->pieceUnit->id,
                    ],
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->woodMaterial->id,
                        'quantity' => 2.00,
                        'unit_id' => $this->boardUnit->id,
                    ],
                ],
            ]);

        $strRecipe = ManufacturingRecipe::where('product_configuration_id', $this->configStorage->id)->first();
        $this->assertNotNull($strRecipe);
        $this->assertEquals(1, $strRecipe->versions->first()->items()->where('item_type', 'SEMI_FINISHED_COMPONENT')->where('semi_finished_component_id', $this->boxStorage->id)->count());
    }

    public function test_circular_component_dependency_is_rejected(): void
    {
        // A component recipe trying to include itself in its items should fail
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'SEMI_FINISHED_COMPONENT',
                'semi_finished_component_id' => $this->boxStandard->id,
                'name' => 'وصفة المكون الدائرية',
                'items' => [
                    [
                        'item_type' => 'SEMI_FINISHED_COMPONENT',
                        'semi_finished_component_id' => $this->boxStandard->id, // Self reference!
                        'quantity' => 1.00,
                        'unit_id' => $this->pieceUnit->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
