<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_customers_index(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
        $response->assertSee('CUS-000001');
        $response->assertSee('متجر مفروشات سدير');
    }

    public function test_customers_can_be_filtered_by_search_and_type(): void
    {
        $admin = User::where('username', 'admin')->first();
        $storeType = CustomerType::where('code', 'SADIR_STORE')->first();

        $response = $this->actingAs($admin)->get(route('customers.index', [
            'search' => 'سدير',
            'customer_type_id' => $storeType->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('CUS-000001');

        $responseEmpty = $this->actingAs($admin)->get(route('customers.index', [
            'search' => 'NonExistentCustomerNameXYZ',
        ]));

        $responseEmpty->assertStatus(200);
        $responseEmpty->assertSee('لا توجد سجلات عملاء مطابقة للبحث الحالي');
    }

    public function test_admin_can_create_a_cash_customer(): void
    {
        $admin = User::where('username', 'admin')->first();
        $type = CustomerType::first();
        $channel = SalesChannel::first();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'عميل تجريبي جديد',
            'customer_type_id' => $type->id,
            'default_sales_channel_id' => $channel->id,
            'mobile' => '0551234567',
            'city' => 'الرياض',
            'is_credit_customer' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'عميل تجريبي جديد',
            'customer_code' => 'CUS-000002',
            'is_credit_customer' => false,
            'credit_limit' => null,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_a_credit_customer_with_limit(): void
    {
        $admin = User::where('username', 'admin')->first();
        $type = CustomerType::where('code', 'WHOLESALE_CUSTOMER')->first() ?? CustomerType::first();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'معرض الأثاث الحديث للجملة',
            'customer_type_id' => $type->id,
            'mobile' => '0509876543',
            'city' => 'جدة',
            'is_credit_customer' => 1,
            'credit_limit' => 75000.00,
            'opening_balance' => 5000.00,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'معرض الأثاث الحديث للجملة',
            'is_credit_customer' => true,
            'credit_limit' => 75000.00,
            'opening_balance' => 5000.00,
        ]);
    }

    public function test_credit_customer_requires_positive_or_zero_limit(): void
    {
        $admin = User::where('username', 'admin')->first();
        $type = CustomerType::first();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'عميل برصيد سالب غير صالح',
            'customer_type_id' => $type->id,
            'is_credit_customer' => 1,
            'credit_limit' => -500,
        ]);

        $response->assertSessionHasErrors('credit_limit');
    }

    public function test_admin_can_view_customer_show_page(): void
    {
        $admin = User::where('username', 'admin')->first();
        $customer = Customer::where('customer_code', 'CUS-000001')->first();

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.show');
        $response->assertSee($customer->name);
        $response->assertSee($customer->customer_code);
    }

    public function test_admin_can_update_customer(): void
    {
        $admin = User::where('username', 'admin')->first();
        $customer = Customer::where('customer_code', 'CUS-000001')->first();

        $response = $this->actingAs($admin)->put(route('customers.update', $customer), [
            'name' => 'متجر مفروشات سدير المحدث',
            'customer_type_id' => $customer->customer_type_id,
            'default_sales_channel_id' => $customer->default_sales_channel_id,
            'mobile' => '0555555555',
            'city' => 'المجمعة',
            'is_credit_customer' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'متجر مفروشات سدير المحدث',
            'city' => 'المجمعة',
        ]);
    }

    public function test_admin_can_toggle_customer_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $customer = Customer::where('customer_code', 'CUS-000001')->first();
        $this->assertTrue($customer->is_active);

        // Deactivate
        $response = $this->actingAs($admin)->post(route('customers.toggle-status', $customer));
        $response->assertRedirect();
        $this->assertFalse($customer->fresh()->is_active);

        // Reactivate
        $response = $this->actingAs($admin)->post(route('customers.toggle-status', $customer));
        $response->assertRedirect();
        $this->assertTrue($customer->fresh()->is_active);
    }
}
