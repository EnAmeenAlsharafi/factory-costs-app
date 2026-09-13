<?php

namespace Tests\Feature\Products;

use App\Models\ProductModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $prodManagerUser;

    protected User $csUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_product_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $prodManagerRole = Role::where('name', 'production_manager')->first();
        $this->prodManagerUser = User::factory()->create([
            'username' => 'pm_product_test',
            'role_id' => $prodManagerRole->id,
            'is_active' => true,
        ]);

        $csRole = Role::where('name', 'customer_service')->first();
        $this->csUser = User::factory()->create([
            'username' => 'cs_product_test',
            'role_id' => $csRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_and_production_manager_can_view_product_models_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('products.models.index'));
        $response->assertOk();

        $responsePm = $this->actingAs($this->prodManagerUser)
            ->get(route('products.models.index'));
        $responsePm->assertOk();
    }

    public function test_admin_and_production_manager_can_create_product_model_with_safe_code_and_image(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->create('avalon_model.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->post(route('products.models.store'), [
                'name_ar' => 'أڤالون',
                'name_en' => 'Avalon Bed Model',
                'description' => 'موديل أڤالون بتصميم إيطالي فاخر',
                'design_notes' => 'قواعد تجميع خشب زان 2.5 سم مع إسفنج ضغط 30',
                'reference_image' => $image,
                'is_custom_template' => false,
                'is_active' => true,
            ]);

        $model = ProductModel::latest()->first();

        $response->assertRedirect(route('products.models.show', $model));
        $this->assertEquals('أڤالون', $model->name_ar);
        $this->assertStringStartsWith('MOD-', $model->model_code);
        $this->assertNotNull($model->reference_image_path);

        Storage::disk('public')->assertExists($model->reference_image_path);
    }

    public function test_customer_service_can_view_models_but_cannot_create_or_modify(): void
    {
        $model = ProductModel::create([
            'model_code' => 'MOD-TEST-001',
            'name_ar' => 'ميلان',
            'is_active' => true,
        ]);

        $responseShow = $this->actingAs($this->csUser)
            ->get(route('products.models.show', $model));
        $responseShow->assertOk();

        $responseCreate = $this->actingAs($this->csUser)
            ->get(route('products.models.create'));
        $responseCreate->assertStatus(403);

        $responseStore = $this->actingAs($this->csUser)
            ->post(route('products.models.store'), [
                'name_ar' => 'موديل مرفوض',
            ]);
        $responseStore->assertStatus(403);
    }

    public function test_model_search_and_active_filtering(): void
    {
        ProductModel::create([
            'model_code' => 'MOD-000001',
            'name_ar' => 'أڤالون',
            'is_active' => true,
        ]);

        ProductModel::create([
            'model_code' => 'MOD-000002',
            'name_ar' => 'ريڤا',
            'is_active' => false,
        ]);

        $responseSearch = $this->actingAs($this->adminUser)
            ->get(route('products.models.index', ['search' => 'أڤالون']));
        $responseSearch->assertOk();
        $responseSearch->assertSee('MOD-000001');
        $responseSearch->assertDontSee('MOD-000002');

        $responseActive = $this->actingAs($this->adminUser)
            ->get(route('products.models.index', ['status' => '0']));
        $responseActive->assertOk();
        $responseActive->assertSee('MOD-000002');
        $responseActive->assertDontSee('MOD-000001');
    }

    public function test_admin_can_create_and_update_product_model_with_image_url(): void
    {
        $imageUrl = 'https://images.unsplash.com/photo-1540518614846-7ede433c5173?w=800';

        $responseStore = $this->actingAs($this->adminUser)
            ->post(route('products.models.store'), [
                'name_ar' => 'موديل مع رابط صورة',
                'name_en' => 'Model With Image URL',
                'image_source' => 'url',
                'reference_image_url' => $imageUrl,
                'is_active' => true,
            ]);

        $model = ProductModel::where('name_ar', 'موديل مع رابط صورة')->first();
        $this->assertNotNull($model);
        $this->assertEquals($imageUrl, $model->reference_image_path);
        $this->assertEquals($imageUrl, $model->image_url);

        // Test updating with a new URL
        $newUrl = 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800';
        $responseUpdate = $this->actingAs($this->adminUser)
            ->put(route('products.models.update', $model), [
                'name_ar' => 'موديل مع رابط صورة محدث',
                'image_source' => 'url',
                'reference_image_url' => $newUrl,
                'is_active' => true,
            ]);

        $this->assertEquals($newUrl, $model->fresh()->reference_image_path);
        $this->assertEquals($newUrl, $model->fresh()->image_url);
    }
}
