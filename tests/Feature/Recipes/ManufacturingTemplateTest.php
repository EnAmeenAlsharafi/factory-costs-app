<?php

namespace Tests\Feature\Recipes;

use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingTemplate;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected ManufacturingTemplate $template;

    protected ProductConfiguration $configuration;

    protected Material $woodMaterial;

    protected UnitOfMeasure $boardUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_tpl_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-TPL-001',
            'name_ar' => 'نموذج قالب',
            'is_active' => true,
        ]);

        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-TPL-160',
            'product_model_id' => $model->id,
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->boardUnit = UnitOfMeasure::where('code', 'BOARD')->first() ?? UnitOfMeasure::create([
            'code' => 'BOARD', 'name_ar' => 'لوح', 'allows_decimal' => true, 'is_active' => true,
        ]);

        $category = MaterialCategory::first();

        $this->woodMaterial = Material::create([
            'code' => 'MAT-WOOD-003',
            'material_category_id' => $category->id,
            'name_ar' => 'خشب سويدي 5 سم',
            'base_unit_id' => $this->boardUnit->id,
            'is_active' => true,
        ]);

        $this->template = ManufacturingTemplate::create([
            'template_code' => 'TPL-GUARDS-01',
            'name_ar' => 'قالب حواجز علب',
            'is_active' => true,
        ]);

        $this->template->items()->create([
            'item_type' => 'MATERIAL',
            'material_id' => $this->woodMaterial->id,
            'quantity' => 4.00,
            'unit_id' => $this->boardUnit->id,
            'waste_percentage' => 5.00,
        ]);
    }

    public function test_can_create_draft_recipe_from_template(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة مشتقة من القالب',
            ]);

        $recipe = ManufacturingRecipe::where('product_configuration_id', $this->configuration->id)->first();
        $response->assertRedirect(route('recipes.show', $recipe));

        $v1 = $recipe->versions->first();
        $this->assertEquals(1, $v1->items()->count());
        $this->assertEquals(4.0000, $v1->items->first()->quantity);
    }

    public function test_recipe_remains_independent_when_template_items_are_modified_later(): void
    {
        // 1. Create recipe from template
        $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة قبل التعديل',
            ]);

        $recipe = ManufacturingRecipe::where('product_configuration_id', $this->configuration->id)->first();
        $v1 = $recipe->versions->first();

        // 2. Modify template item quantity
        $templateItem = $this->template->items->first();
        $templateItem->update(['quantity' => 10.00]);

        // 3. Recipe item quantity must remain 4.00 (Independent copy)
        $this->assertEquals(4.0000, $v1->items()->first()->fresh()->quantity);
    }
}
