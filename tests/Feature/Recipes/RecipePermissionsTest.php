<?php

namespace Tests\Feature\Recipes;

use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $pmUser;

    protected User $workerUser;

    protected User $csUser;

    protected ManufacturingRecipe $recipe;

    protected ManufacturingRecipeVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_perm_test', 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $pmRole = Role::where('name', 'production_manager')->first();
        $this->pmUser = User::factory()->create([
            'username' => 'pm_perm_test', 'role_id' => $pmRole->id, 'is_active' => true,
        ]);

        $workerRole = Role::where('name', 'production_worker')->first();
        $this->workerUser = User::factory()->create([
            'username' => 'worker_perm_test', 'role_id' => $workerRole->id, 'is_active' => true,
        ]);

        $csRole = Role::where('name', 'customer_service')->first();
        $this->csUser = User::factory()->create([
            'username' => 'cs_perm_test', 'role_id' => $csRole->id, 'is_active' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-PERM-01',
            'name_ar' => 'نموذج صلاحيات',
            'is_active' => true,
        ]);

        $config = ProductConfiguration::create([
            'configuration_code' => 'CFG-PERM-160',
            'product_model_id' => $model->id,
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->recipe = ManufacturingRecipe::create([
            'recipe_code' => 'RCP-PERM-01',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $config->id,
            'name' => 'وصفة اختبار الصلاحيات',
            'is_active' => true,
        ]);

        $this->version = ManufacturingRecipeVersion::create([
            'manufacturing_recipe_id' => $this->recipe->id,
            'version_number' => 1,
            'status' => 'DRAFT',
        ]);
    }

    public function test_production_manager_and_admin_can_view_create_and_approve(): void
    {
        $responseIndex = $this->actingAs($this->pmUser)->get(route('recipes.index'));
        $responseIndex->assertOk();

        $responseShow = $this->actingAs($this->pmUser)->get(route('recipes.show', $this->recipe));
        $responseShow->assertOk();

        $responseApprove = $this->actingAs($this->pmUser)
            ->post(route('recipes.versions.approve', [$this->recipe, $this->version]));
        $responseApprove->assertRedirect();
        $this->assertEquals('APPROVED', $this->version->fresh()->status);
    }

    public function test_production_worker_can_view_recipes_but_cannot_manage_or_approve(): void
    {
        $responseShow = $this->actingAs($this->workerUser)->get(route('recipes.show', $this->recipe));
        $responseShow->assertOk();

        $responseCreate = $this->actingAs($this->workerUser)->get(route('recipes.create'));
        $responseCreate->assertStatus(403);

        $responseApprove = $this->actingAs($this->workerUser)
            ->post(route('recipes.versions.approve', [$this->recipe, $this->version]));
        $responseApprove->assertStatus(403);
    }

    public function test_customer_service_cannot_view_or_manage_recipes(): void
    {
        $responseIndex = $this->actingAs($this->csUser)->get(route('recipes.index'));
        $responseIndex->assertStatus(403);

        $responseCreate = $this->actingAs($this->csUser)->get(route('recipes.create'));
        $responseCreate->assertStatus(403);
    }
}
