<?php

namespace Tests\Feature\Products;

use App\Models\Customer;
use App\Models\CustomerProductAlias;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\StandardBedSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMasterPermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_view_user_can_read_but_cannot_mutate_product_master_routes(): void
    {
        $this->seed();
        $viewer = User::factory()->create([
            'role_id' => Role::where('name', 'customer_service')->firstOrFail()->id,
            'is_active' => true,
        ]);
        $model = ProductModel::create([
            'model_code' => 'PERM-MODEL-1',
            'name_ar' => 'موديل اختبار الصلاحيات',
            'is_active' => true,
        ]);
        $configuration = ProductConfiguration::create([
            'configuration_code' => 'PERM-CFG-1',
            'product_model_id' => $model->id,
            'width_cm' => 160,
            'length_cm' => 200,
            'has_storage' => false,
        ]);
        $alias = CustomerProductAlias::create([
            'customer_id' => Customer::firstOrFail()->id,
            'product_model_id' => $model->id,
            'customer_product_name' => 'اسم عميل',
            'is_active' => true,
        ]);

        $this->actingAs($viewer)->get(route('products.models.index'))->assertOk();
        $this->actingAs($viewer)->get(route('products.models.show', $model))->assertOk();
        $this->actingAs($viewer)->get(route('products.sizes.index'))->assertOk();

        $this->actingAs($viewer)->post(route('products.models.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('products.configurations.store'), [])->assertForbidden();
        $this->actingAs($viewer)->put(route('products.configurations.update', $configuration), [])->assertForbidden();
        $this->actingAs($viewer)->delete(route('products.configurations.destroy', $configuration))->assertForbidden();
        $this->actingAs($viewer)->post(route('products.sizes.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('products.aliases.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('products.aliases.set-default', $alias))->assertForbidden();

        $this->assertModelExists($configuration);
        $this->assertFalse((bool) $alias->fresh()->is_default);
    }

    public function test_products_manage_user_can_mutate_product_master(): void
    {
        $this->seed();
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('products.sizes.store'), [
            'code' => 'PERM-SIZE-1',
            'width_cm' => 150,
            'length_cm' => 200,
            'name_ar' => 'مقاس صلاحيات',
        ])->assertRedirect(route('products.sizes.index'));

        $this->assertModelExists(StandardBedSize::where('code', 'PERM-SIZE-1')->firstOrFail());
    }
}
