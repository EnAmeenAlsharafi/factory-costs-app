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

    public function test_can_render_edit_template_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('recipes.templates.edit', $this->template));

        $response->assertOk();
        $response->assertViewIs('recipes.templates.edit');
        $response->assertViewHas('template');
    }

    public function test_can_update_manufacturing_template(): void
    {
        $payload = [
            'name_ar' => 'قالب معدل بالكامل',
            'name_en' => 'Updated Template',
            'description' => 'وصف القالب بعد التعديل',
            'is_active' => '1',
            'items' => [
                [
                    'item_type' => 'MATERIAL',
                    'material_id' => $this->woodMaterial->id,
                    'quantity' => 8.5,
                    'unit_id' => $this->boardUnit->id,
                    'waste_percentage' => 7.5,
                    'notes' => 'تعديل البند الأول',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('recipes.templates.update', $this->template), $payload);

        $response->assertRedirect(route('recipes.templates.index'));
        $this->assertDatabaseHas('manufacturing_templates', [
            'id' => $this->template->id,
            'name_ar' => 'قالب معدل بالكامل',
            'description' => 'وصف القالب بعد التعديل',
        ]);

        $this->assertDatabaseHas('manufacturing_template_items', [
            'manufacturing_template_id' => $this->template->id,
            'material_id' => $this->woodMaterial->id,
            'quantity' => 8.5,
            'waste_percentage' => 7.5,
        ]);
    }

    public function test_recipe_created_from_template_appears_in_bom_index(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة للمعاينة في الفهرس',
            ]);

        $response = $this->actingAs($this->adminUser)->get(route('recipes.index'));

        $response->assertOk();
        $response->assertSee('وصفة للمعاينة في الفهرس');
        $response->assertSee('نموذج قالب');
    }

    public function test_create_recipe_from_template_with_invalid_target_fails(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => 99999,
                'name' => 'وصفة بهدف غير صحيح',
            ]);

        $response->assertSessionHasErrors('product_configuration_id');
        $this->assertDatabaseMissing('manufacturing_recipes', [
            'name' => 'وصفة بهدف غير صحيح',
        ]);
    }

    public function test_duplicate_recipe_protection_prevents_duplicate_recipes_for_same_target(): void
    {
        // First creation succeeds
        $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'الوصفة الأولى',
            ]);

        // Second creation for same configuration must fail with duplicate warning message
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.create-recipe', $this->template), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'الوصفة الثانية المكررة',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('manufacturing_recipes', [
            'name' => 'الوصفة الثانية المكررة',
        ]);
    }

    public function test_incomplete_template_line_reports_which_line_and_field_in_plain_language(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('recipes.templates.store'), [
                'name_ar' => 'قالب ببند ناقص',
                'items' => [
                    ['item_type' => 'MATERIAL', 'material_id' => $this->woodMaterial->id, 'quantity' => 2, 'unit_id' => $this->boardUnit->id],
                    ['item_type' => 'MATERIAL', 'material_id' => '', 'quantity' => 0, 'unit_id' => $this->boardUnit->id],
                ],
            ]);

        $response->assertSessionHasErrors([
            'items.1.material_id' => 'البند 2: اختر المادة الخام.',
            'items.1.quantity' => 'البند 2: الكمية يجب أن تكون أكبر من صفر.',
        ]);
        $this->assertDatabaseMissing('manufacturing_templates', ['name_ar' => 'قالب ببند ناقص']);
    }

    public function test_create_recipe_dialog_offers_searchable_models_with_their_photos(): void
    {
        $this->configuration->productModel->update(['reference_image_path' => 'https://images.example.test/tpl-bed.jpg']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('recipes.templates.show', [$this->template, 'open_modal' => 1]));

        $response->assertOk()
            ->assertSee('configurationPicker(', false)
            ->assertSee('نموذج قالب')
            ->assertSee('MOD-TPL-001')
            ->assertSee('images.example.test', false)
            ->assertSee('tpl-bed.jpg', false);
    }

    public function test_model_picker_marks_sizes_that_already_have_a_recipe(): void
    {
        $openSize = ProductConfiguration::create([
            'configuration_code' => 'CFG-TPL-180',
            'product_model_id' => $this->configuration->product_model_id,
            'width_cm' => 180.00,
            'length_cm' => 200.00,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)->post(route('recipes.templates.create-recipe', $this->template), [
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->configuration->id,
            'name' => 'وصفة المقاس الأول',
        ]);
        $recipe = ManufacturingRecipe::where('product_configuration_id', $this->configuration->id)->firstOrFail();

        $pickerSizes = collect($this->actingAs($this->adminUser)
            ->get(route('recipes.create'))
            ->assertOk()
            ->viewData('configurations'))
            ->keyBy('id');

        $this->assertSame($recipe->recipe_code, $pickerSizes[$this->configuration->id]->manufacturingRecipe?->recipe_code);
        $this->assertNull($pickerSizes[$openSize->id]->manufacturingRecipe);

        $this->actingAs($this->adminUser)
            ->get(route('recipes.templates.show', [$this->template, 'open_modal' => 1]))
            ->assertOk()
            ->assertSee($recipe->recipe_code);
    }
}
