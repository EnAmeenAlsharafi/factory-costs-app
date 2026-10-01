<?php

namespace Tests\Feature\Recipes;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SemiFinishedComponent;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeEditAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $workerUser;

    protected ProductConfiguration $configuration;

    protected ProductConfiguration $otherConfiguration;

    protected SemiFinishedComponent $component;

    protected ManufacturingRecipe $recipe;

    protected ManufacturingRecipeVersion $version;

    protected Material $wood;

    protected UnitOfMeasure $pieceUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_recipe_crud',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $workerRole = Role::where('name', 'production_worker')->first();
        $this->workerUser = User::factory()->create([
            'username' => 'worker_recipe_crud',
            'role_id' => $workerRole->id,
            'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-TEST-RECIPE',
            'name_ar' => 'موديل اختبار الوصفات',
            'is_active' => true,
        ]);

        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-TEST-180x200',
            'product_model_id' => $model->id,
            'width_cm' => 180,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->otherConfiguration = ProductConfiguration::create([
            'configuration_code' => 'CFG-TEST-160x200',
            'product_model_id' => $model->id,
            'width_cm' => 160,
            'length_cm' => 200,
            'has_storage' => true,
            'is_active' => true,
        ]);

        $this->component = SemiFinishedComponent::create([
            'component_code' => 'SFC-TEST-BOX',
            'name_ar' => 'صندوق جانبي',
            'is_active' => true,
        ]);

        $cat = MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'أخشاب', 'is_active' => true]);
        $this->pieceUnit = UnitOfMeasure::firstOrCreate(['code' => 'PIECE'], ['name_ar' => 'قطعة', 'unit_type' => 'quantity', 'is_active' => true]);

        $this->wood = Material::create([
            'code' => 'MAT-TEST-MDF',
            'name_ar' => 'ام دي اف 8 ملم',
            'material_category_id' => $cat->id,
            'base_unit_id' => $this->pieceUnit->id,
            'is_active' => true,
        ]);

        $this->recipe = ManufacturingRecipe::create([
            'recipe_code' => 'RCP-TEST-001',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->configuration->id,
            'name' => 'وصفة اختبارية أولية',
            'description' => 'وصف اختباري أولي',
            'is_active' => true,
        ]);

        $this->version = ManufacturingRecipeVersion::create([
            'manufacturing_recipe_id' => $this->recipe->id,
            'version_number' => 1,
            'status' => 'DRAFT',
            'notes' => 'المسودة الأولى',
        ]);

        ManufacturingRecipeItem::create([
            'recipe_version_id' => $this->version->id,
            'item_type' => 'MATERIAL',
            'material_id' => $this->wood->id,
            'quantity' => 2,
            'unit_id' => $this->pieceUnit->id,
            'waste_percentage' => 0,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_view_edit_recipe_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('recipes.edit', $this->recipe));
        $response->assertOk();
        $response->assertViewIs('recipes.edit_recipe');
        $response->assertSee('تعديل بيانات وصفة التصنيع');
        $response->assertSee($this->recipe->recipe_code);
        $response->assertSee('وصفة اختبارية أولية');
    }

    public function test_unauthorized_user_cannot_edit_or_delete_recipe(): void
    {
        $editResponse = $this->actingAs($this->workerUser)->get(route('recipes.edit', $this->recipe));
        $editResponse->assertForbidden();

        $updateResponse = $this->actingAs($this->workerUser)->put(route('recipes.update', $this->recipe), [
            'name' => 'تعديل غير مصرح به',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->configuration->id,
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->actingAs($this->workerUser)->delete(route('recipes.destroy', $this->recipe));
        $deleteResponse->assertForbidden();
    }

    public function test_admin_can_update_recipe_metadata(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('recipes.update', $this->recipe), [
            'name' => 'وصفة اختبارية محدثة',
            'description' => 'تم التحديث بنجاح',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->otherConfiguration->id,
            'is_active' => 0,
        ]);

        $response->assertRedirect(route('recipes.show', $this->recipe));
        $response->assertSessionHas('success');

        $this->recipe->refresh();
        $this->assertEquals('وصفة اختبارية محدثة', $this->recipe->name);
        $this->assertEquals('تم التحديث بنجاح', $this->recipe->description);
        $this->assertEquals($this->otherConfiguration->id, $this->recipe->product_configuration_id);
        $this->assertFalse($this->recipe->is_active);
    }

    public function test_update_recipe_prevents_duplicate_target_configuration(): void
    {
        ManufacturingRecipe::create([
            'recipe_code' => 'RCP-TEST-002',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->otherConfiguration->id,
            'name' => 'وصفة ثانية',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('recipes.update', $this->recipe), [
            'name' => 'محاولة تعيين هدف مكرر',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->otherConfiguration->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('توجد بالفعل وصفة تصنيع أخرى معرفة لهذا التكوين المصنعي', session('error'));
    }

    public function test_cannot_change_target_if_recipe_has_production_history(): void
    {
        $this->version->status = 'APPROVED';
        $this->version->save();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-TEST-REC-01',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $this->version->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        app(ProductionOrderService::class)->createFromOrderLine($line, [
            'released_quantity' => 1,
            'manufacturing_recipe_version_id' => $this->version->id,
        ]);

        // Attempt to change target configuration to semi-finished component
        $response = $this->actingAs($this->adminUser)->put(route('recipes.update', $this->recipe), [
            'name' => 'تعديل اسم',
            'target_type' => 'SEMI_FINISHED_COMPONENT',
            'semi_finished_component_id' => $this->component->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('لا يمكن تغيير الهدف المصنعي', session('error'));

        // Modifying only the name and description succeeds
        $okResponse = $this->actingAs($this->adminUser)->put(route('recipes.update', $this->recipe), [
            'name' => 'اسم معدل مسموح به',
            'description' => 'وصف معدل',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $this->configuration->id,
            'is_active' => 1,
        ]);
        $okResponse->assertSessionHas('success');
        $this->assertEquals('اسم معدل مسموح به', $this->recipe->fresh()->name);
    }

    public function test_admin_can_delete_recipe_when_not_in_use(): void
    {
        $recipeId = $this->recipe->id;
        $versionId = $this->version->id;

        $response = $this->actingAs($this->adminUser)->delete(route('recipes.destroy', $this->recipe));

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('manufacturing_recipes', ['id' => $recipeId]);
        $this->assertDatabaseMissing('manufacturing_recipe_versions', ['id' => $versionId]);
        $this->assertDatabaseMissing('manufacturing_recipe_items', ['recipe_version_id' => $versionId]);
    }

    public function test_cannot_delete_recipe_if_linked_to_production(): void
    {
        $this->version->status = 'APPROVED';
        $this->version->save();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-TEST-REC-02',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $this->version->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        app(ProductionOrderService::class)->createFromOrderLine($line, [
            'released_quantity' => 1,
            'manufacturing_recipe_version_id' => $this->version->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('recipes.destroy', $this->recipe));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('لا يمكن حذف وصفة التصنيع', session('error'));
        $this->assertDatabaseHas('manufacturing_recipes', ['id' => $this->recipe->id]);
    }

    public function test_can_delete_draft_version_when_recipe_has_multiple_versions(): void
    {
        $version2 = ManufacturingRecipeVersion::create([
            'manufacturing_recipe_id' => $this->recipe->id,
            'version_number' => 2,
            'status' => 'DRAFT',
            'notes' => 'المسودة الثانية',
        ]);

        ManufacturingRecipeItem::create([
            'recipe_version_id' => $version2->id,
            'item_type' => 'MATERIAL',
            'material_id' => $this->wood->id,
            'quantity' => 5,
            'unit_id' => $this->pieceUnit->id,
            'waste_percentage' => 0,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('recipes.versions.destroy', [$this->recipe, $version2]));

        $response->assertRedirect(route('recipes.show', $this->recipe));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('manufacturing_recipe_versions', ['id' => $version2->id]);
        $this->assertDatabaseHas('manufacturing_recipe_versions', ['id' => $this->version->id]);
    }

    public function test_cannot_delete_the_only_version_of_recipe(): void
    {
        $this->assertEquals(1, $this->recipe->versions()->count());

        $response = $this->actingAs($this->adminUser)->delete(route('recipes.versions.destroy', [$this->recipe, $this->version]));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('لا يمكن حذف الإصدار الوحيد للوصفة', session('error'));
        $this->assertDatabaseHas('manufacturing_recipe_versions', ['id' => $this->version->id]);
    }
}
