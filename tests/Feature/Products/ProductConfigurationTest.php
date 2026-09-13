<?php

namespace Tests\Feature\Products;

use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\StandardBedSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected ProductModel $model;

    protected StandardBedSize $size160;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_cfg_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->model = ProductModel::create([
            'model_code' => 'MOD-AVALON-001',
            'name_ar' => 'أڤالون',
            'is_active' => true,
        ]);

        $this->size160 = StandardBedSize::where('code', 'SIZE-160X200')->first() ?? StandardBedSize::create([
            'code' => 'SIZE-160X200',
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'name_ar' => '160 × 200 سم',
        ]);
    }

    public function test_user_can_add_storage_and_non_storage_configurations_to_model(): void
    {
        // 1. Add 160x200 Standard (No Storage)
        $response1 = $this->actingAs($this->adminUser)
            ->post(route('products.configurations.store'), [
                'product_model_id' => $this->model->id,
                'standard_bed_size_id' => $this->size160->id,
                'width_cm' => 160.0,
                'length_cm' => 200.0,
                'has_storage' => false,
                'configuration_name' => '160×200 بدون تخزين',
            ]);

        $response1->assertRedirect(route('products.models.show', $this->model));

        // 2. Add 160x200 Storage
        $response2 = $this->actingAs($this->adminUser)
            ->post(route('products.configurations.store'), [
                'product_model_id' => $this->model->id,
                'standard_bed_size_id' => $this->size160->id,
                'width_cm' => 160.0,
                'length_cm' => 200.0,
                'has_storage' => true,
                'configuration_name' => '160×200 سحارة',
            ]);

        $response2->assertRedirect(route('products.models.show', $this->model));

        $this->assertEquals(2, $this->model->configurations()->count());

        $this->assertDatabaseHas('product_configurations', [
            'product_model_id' => $this->model->id,
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'has_storage' => false,
        ]);

        $this->assertDatabaseHas('product_configurations', [
            'product_model_id' => $this->model->id,
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'has_storage' => true,
        ]);
    }

    public function test_duplicate_same_model_size_and_storage_configuration_is_rejected(): void
    {
        ProductConfiguration::create([
            'configuration_code' => 'CFG-000001',
            'product_model_id' => $this->model->id,
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'has_storage' => false,
        ]);

        // Duplicate submission should redirect with error flash
        $response = $this->actingAs($this->adminUser)
            ->post(route('products.configurations.store'), [
                'product_model_id' => $this->model->id,
                'width_cm' => 160.0,
                'length_cm' => 200.0,
                'has_storage' => false,
            ]);

        $response->assertRedirect(route('products.models.show', $this->model));
        $response->assertSessionHas('error');

        $this->assertEquals(1, $this->model->configurations()->count());
    }

    public function test_admin_can_delete_unlinked_product_configuration(): void
    {
        $config = ProductConfiguration::create([
            'configuration_code' => 'CFG-000099',
            'product_model_id' => $this->model->id,
            'width_cm' => 180.0,
            'length_cm' => 200.0,
            'has_storage' => false,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('products.configurations.destroy', $config));

        $response->assertRedirect(route('products.models.show', $this->model));
        $this->assertDatabaseMissing('product_configurations', [
            'id' => $config->id,
        ]);
    }
}
