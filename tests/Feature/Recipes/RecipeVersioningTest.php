<?php

namespace Tests\Feature\Recipes;

use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $prodManagerUser;

    protected ProductConfiguration $configuration;

    protected Material $woodMaterial;

    protected Material $foamMaterial;

    protected UnitOfMeasure $boardUnit;

    protected UnitOfMeasure $pieceUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_recipe_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $pmRole = Role::where('name', 'production_manager')->first();
        $this->prodManagerUser = User::factory()->create([
            'username' => 'pm_recipe_test',
            'role_id' => $pmRole->id,
            'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-AVALON-100',
            'name_ar' => 'أڤالون',
            'is_active' => true,
        ]);

        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-AVALON-160',
            'product_model_id' => $model->id,
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => false,
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
            'code' => 'MAT-WOOD-001',
            'material_category_id' => $category->id,
            'name_ar' => 'خشب زان 2.5 سم',
            'base_unit_id' => $this->boardUnit->id,
            'is_active' => true,
        ]);

        $this->foamMaterial = Material::create([
            'code' => 'MAT-FOAM-001',
            'material_category_id' => $category->id,
            'name_ar' => 'إسفنج ضغط 30',
            'base_unit_id' => $this->pieceUnit->id,
            'is_active' => true,
        ]);
    }

    public function test_full_recipe_version_lifecycle_draft_approve_copy_update_approve_supersede(): void
    {
        // 1. Create Recipe with V1 DRAFT
        $responseStore = $this->actingAs($this->adminUser)
            ->post(route('recipes.store'), [
                'target_type' => 'PRODUCT_CONFIGURATION',
                'product_configuration_id' => $this->configuration->id,
                'name' => 'وصفة سرير أڤالون 160×200 بدون تخزين',
                'description' => 'المواصفة الأساسية 2026',
                'version_notes' => 'إصدار أولي مسودة V1',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->woodMaterial->id,
                        'quantity' => 3.00,
                        'unit_id' => $this->boardUnit->id,
                        'waste_percentage' => 5.0,
                    ],
                ],
            ]);

        $recipe = ManufacturingRecipe::latest()->first();
        $responseStore->assertRedirect(route('recipes.show', $recipe));

        $this->assertEquals(1, $recipe->versions()->count());
        $v1 = $recipe->versions->first();
        $this->assertEquals(1, $v1->version_number);
        $this->assertEquals('DRAFT', $v1->status);
        $this->assertNull($recipe->currentApprovedVersion);

        // 2. Approve V1
        $responseApproveV1 = $this->actingAs($this->prodManagerUser)
            ->post(route('recipes.versions.approve', [$recipe, $v1]));

        $responseApproveV1->assertRedirect();
        $this->assertEquals('APPROVED', $v1->fresh()->status);
        $this->assertEquals($v1->id, $recipe->fresh()->currentApprovedVersion->id);

        // 3. Approved version cannot be updated directly
        $responseUpdateDirect = $this->actingAs($this->adminUser)
            ->put(route('recipes.versions.update', [$recipe, $v1]), [
                'notes' => 'محاولة تعديل مباشر',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->woodMaterial->id,
                        'quantity' => 5.00,
                        'unit_id' => $this->boardUnit->id,
                    ],
                ],
            ]);

        $responseUpdateDirect->assertRedirect();
        $responseUpdateDirect->assertSessionHas('error');
        $this->assertEquals(1, $v1->fresh()->items()->count());
        $this->assertEquals(3.0000, $v1->fresh()->items->first()->quantity);

        // 4. Copy V1 to V2 DRAFT
        $responseCopy = $this->actingAs($this->adminUser)
            ->post(route('recipes.versions.copy', [$recipe, $v1]));

        $v2 = ManufacturingRecipeVersion::where('manufacturing_recipe_id', $recipe->id)
            ->where('version_number', 2)
            ->first();

        $this->assertNotNull($v2);
        $this->assertEquals('DRAFT', $v2->status);
        $this->assertEquals(1, $v2->items()->count());

        // 5. Update V2 DRAFT
        $responseUpdateV2 = $this->actingAs($this->adminUser)
            ->put(route('recipes.versions.update', [$recipe, $v2]), [
                'notes' => 'تعديل كمية الإسفنج في V2',
                'items' => [
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->woodMaterial->id,
                        'quantity' => 3.00,
                        'unit_id' => $this->boardUnit->id,
                        'waste_percentage' => 5.0,
                    ],
                    [
                        'item_type' => 'MATERIAL',
                        'material_id' => $this->foamMaterial->id,
                        'quantity' => 2.00,
                        'unit_id' => $this->pieceUnit->id,
                        'waste_percentage' => 0.0,
                    ],
                ],
            ]);

        $responseUpdateV2->assertRedirect();
        $this->assertEquals(2, $v2->items()->count());

        // 6. Approve V2 -> V1 becomes SUPERSEDED, V2 becomes APPROVED
        $responseApproveV2 = $this->actingAs($this->prodManagerUser)
            ->post(route('recipes.versions.approve', [$recipe, $v2]));

        $responseApproveV2->assertRedirect();

        $this->assertEquals('SUPERSEDED', $v1->fresh()->status);
        $this->assertEquals('APPROVED', $v2->fresh()->status);
        $this->assertEquals($v2->id, $recipe->fresh()->currentApprovedVersion->id);

        // Historical V1 items remain intact with 1 item
        $this->assertEquals(1, $v1->items()->count());
    }
}
