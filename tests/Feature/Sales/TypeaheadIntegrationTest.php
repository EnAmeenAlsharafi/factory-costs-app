<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerType;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypeaheadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected ProductModel $model;

    protected ProductConfiguration $config;

    protected Supplier $supplier;

    protected Material $fabric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'WHOLESALE'],
            ['name_ar' => 'عميل جملة', 'is_active' => true]
        );

        $this->customer = Customer::create([
            'customer_code' => 'CUS-TYPEAHEAD-001',
            'customer_type_id' => $customerType->id,
            'name' => 'شركة النجاح',
            'is_active' => true,
        ]);

        $this->salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'SHOWROOM'],
            ['name_ar' => 'المعارض المباشرة', 'is_active' => true]
        );

        $this->model = ProductModel::create([
            'model_code' => 'MOD-TH-001',
            'name_ar' => 'سرير رويال تايب أهيد',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $this->config = ProductConfiguration::create([
            'configuration_code' => 'CFG-TH-200x200',
            'product_model_id' => $this->model->id,
            'width_cm' => 200,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'supplier_code' => 'SUP-TH-001',
            'name' => 'مورد أقمشة تايب أهيد',
            'is_active' => true,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'METER'],
            ['name_ar' => 'متر طولي', 'unit_type' => 'LENGTH', 'is_active' => true]
        );

        $fabricCategory = MaterialCategory::firstOrCreate(
            ['code' => 'FABRIC'],
            ['name_ar' => 'أقمشة', 'is_active' => true]
        );

        $this->fabric = Material::create([
            'code' => 'MAT-TH-001',
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $uom->id,
            'name_ar' => 'مخمل رويال تايب أهيد',
            'is_active' => true,
        ]);

        $this->supplier->materials()->attach($this->fabric->id);
    }

    public function test_create_order_using_typeahead_selection_inputs(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->format('Y-m-d'),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->model->id,
                    'product_configuration_id' => $this->config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplier->id,
                    'fabric_material_id' => $this->fabric->id,
                    'fabric_color_code' => 'TH-901',
                    'quantity' => 2,
                    'unit_price' => 1500.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.orders.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_orders', [
            'customer_id' => $this->customer->id,
        ]);

        $this->assertDatabaseHas('customer_order_lines', [
            'product_model_id' => $this->model->id,
            'product_configuration_id' => $this->config->id,
            'fabric_supplier_id' => $this->supplier->id,
            'fabric_material_id' => $this->fabric->id,
            'fabric_color_code' => 'TH-901',
            'quantity' => 2,
        ]);
    }

    public function test_edit_order_form_renders_with_preloaded_typeahead_labels(): void
    {
        $order = CustomerOrder::create([
            'order_number' => 'ORD-TH-999',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'created_by_user_id' => $this->user->id,
            'order_date' => now(),
            'status' => 'DRAFT',
            'priority' => 'NORMAL',
            'subtotal' => 3000,
            'tax_amount' => 450,
            'total_amount' => 3450,
        ]);

        $order->lines()->create([
            'product_model_id' => $this->model->id,
            'product_configuration_id' => $this->config->id,
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplier->id,
            'fabric_material_id' => $this->fabric->id,
            'fabric_color_code' => 'TH-901',
            'quantity' => 2,
            'unit_price' => 1500.00,
            'subtotal' => 3000.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.orders.edit', $order));

        $response->assertOk();
        $response->assertSee('MOD-TH-001');
        $response->assertSee('SUP-TH-001');
        $response->assertSee('MAT-TH-001');
    }

    public function test_create_quotation_using_typeahead_selection_inputs(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'quotation_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'product_model_id' => $this->model->id,
                    'product_configuration_id' => $this->config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 200,
                    'quantity' => 1,
                    'unit_price' => 1800.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.quotations.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('quotations', [
            'customer_id' => $this->customer->id,
        ]);

        $this->assertDatabaseHas('quotation_lines', [
            'product_model_id' => $this->model->id,
            'product_configuration_id' => $this->config->id,
            'quantity' => 1,
        ]);
    }

    public function test_create_order_with_multiple_lines(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->format('Y-m-d'),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->model->id,
                    'product_configuration_id' => $this->config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplier->id,
                    'fabric_material_id' => $this->fabric->id,
                    'fabric_color_code' => 'RED-101',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                ],
                [
                    'product_model_id' => $this->model->id,
                    'product_configuration_id' => $this->config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplier->id,
                    'fabric_material_id' => $this->fabric->id,
                    'fabric_color_code' => 'BLUE-202',
                    'quantity' => 2,
                    'unit_price' => 1100.00,
                ],
                [
                    'product_model_id' => $this->model->id,
                    'product_configuration_id' => $this->config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplier->id,
                    'fabric_material_id' => $this->fabric->id,
                    'fabric_color_code' => 'GREEN-303',
                    'quantity' => 3,
                    'unit_price' => 1200.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.orders.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseCount('customer_order_lines', 3);
        $this->assertDatabaseHas('customer_order_lines', ['fabric_color_code' => 'RED-101']);
        $this->assertDatabaseHas('customer_order_lines', ['fabric_color_code' => 'BLUE-202']);
        $this->assertDatabaseHas('customer_order_lines', ['fabric_color_code' => 'GREEN-303']);
    }

    public function test_validation_failure_preserves_old_lines_and_labels(): void
    {
        // Omitting required customer_id triggers validation error
        $payload = [
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->format('Y-m-d'),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->model->id,
                    'product_model_label' => 'سرير رويال تايب أهيد',
                    'product_configuration_id' => $this->config->id,
                    'fabric_supplier_id' => $this->supplier->id,
                    'fabric_supplier_label' => 'مورد أقمشة تايب أهيد',
                    'fabric_material_id' => $this->fabric->id,
                    'fabric_material_label' => 'مخمل رويال تايب أهيد',
                    'fabric_color_code' => 'TH-901',
                    'quantity' => 2,
                    'unit_price' => 1500.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->from(route('sales.orders.create'))
            ->followingRedirects()
            ->post(route('sales.orders.store'), $payload);

        $response->assertOk();
        $response->assertSee('سرير رويال تايب أهيد');
        $response->assertSee('مورد أقمشة تايب أهيد');
        $response->assertSee('مخمل رويال تايب أهيد');
    }
}
