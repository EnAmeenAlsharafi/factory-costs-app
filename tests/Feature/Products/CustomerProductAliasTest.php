<?php

namespace Tests\Feature\Products;

use App\Models\Customer;
use App\Models\CustomerProductAlias;
use App\Models\CustomerType;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductAliasTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Customer $customer;

    protected ProductModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_alias_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $customerType = CustomerType::first();

        $this->customer = Customer::create([
            'customer_code' => 'CUST-TEST-001',
            'customer_type_id' => $customerType->id,
            'name' => 'معرض سدير للثاث',
            'is_active' => true,
        ]);

        $this->model = ProductModel::create([
            'model_code' => 'MOD-AVALON-002',
            'name_ar' => 'أڤالون',
            'is_active' => true,
        ]);
    }

    public function test_can_create_customer_alias_for_product_model(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('products.aliases.store'), [
                'customer_id' => $this->customer->id,
                'product_model_id' => $this->model->id,
                'customer_product_code' => 'SADIR-AVALON-BED',
                'customer_product_name' => 'سرير أڤالون سدير 2026',
                'is_default' => true,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('products.models.show', $this->model));

        $this->assertDatabaseHas('customer_product_aliases', [
            'customer_id' => $this->customer->id,
            'product_model_id' => $this->model->id,
            'customer_product_code' => 'SADIR-AVALON-BED',
            'customer_product_name' => 'سرير أڤالون سدير 2026',
            'is_default' => true,
        ]);
    }

    public function test_setting_new_default_alias_unsets_previous_default_for_same_customer_and_model(): void
    {
        $alias1 = CustomerProductAlias::create([
            'customer_id' => $this->customer->id,
            'product_model_id' => $this->model->id,
            'customer_product_code' => 'CODE-1',
            'customer_product_name' => 'المسمى الأول',
            'is_default' => true,
            'is_active' => true,
        ]);

        $alias2 = CustomerProductAlias::create([
            'customer_id' => $this->customer->id,
            'product_model_id' => $this->model->id,
            'customer_product_code' => 'CODE-2',
            'customer_product_name' => 'المسمى الثاني',
            'is_default' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('products.aliases.set-default', $alias2));

        $response->assertRedirect();

        $this->assertFalse((bool) $alias1->fresh()->is_default);
        $this->assertTrue((bool) $alias2->fresh()->is_default);
    }
}
