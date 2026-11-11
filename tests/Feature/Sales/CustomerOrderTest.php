<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::where('name', 'admin')->first();

        $this->user = User::factory()->create([
            'username' => 'sales_ord_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_can_list_customer_orders(): void
    {
        CustomerOrder::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.orders.index'));

        $response->assertStatus(200);
        $response->assertViewHas('orders');
    }

    public function test_ready_product_can_be_created_and_edited_without_manufacturing_specifications(): void
    {
        $material = Material::factory()->create([
            'is_sellable_ready_product' => true,
            'sales_sku' => 'READY-ORDER-1',
            'sales_display_name' => 'مرتبة جاهزة',
            'sales_price' => 250,
        ]);
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => '2026-11-05',
            'priority' => 'NORMAL',
            'lines' => [[
                'line_type' => 'READY_STOCK',
                'material_id' => $material->id,
                'quantity' => 3,
                'unit_price' => 250,
            ]],
        ];
        $this->actingAs($this->user)->get(route('sales.orders.create'))->assertSee('إضافة منتج جاهز');
        $this->post(route('sales.orders.store'), $payload)->assertSessionHasNoErrors();

        $order = CustomerOrder::latest('id')->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->assertDatabaseHas('customer_order_lines', [
            'id' => $line->id, 'line_type' => 'READY_STOCK', 'material_id' => $material->id,
            'quantity' => 3, 'unit_price' => 250, 'line_total' => 750, 'product_model_id' => null,
        ]);
        $this->get(route('sales.orders.show', $order))->assertSee('مرتبة جاهزة')->assertSee('750.00');
        $this->get(route('sales.orders.edit', $order))->assertSee('إضافة منتج جاهز')
            ->assertViewHas('order', fn (CustomerOrder $editingOrder): bool => $editingOrder->lines->first()->material->sales_display_name === 'مرتبة جاهزة');
        $payload['lines'][0]['id'] = $line->id;
        $payload['lines'][0]['quantity'] = 2;
        $payload['lines'][0]['unit_price'] = 240;

        $this->put(route('sales.orders.update', $order), $payload)->assertRedirect(route('sales.orders.show', $order))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_order_lines', ['id' => $line->id, 'material_id' => $material->id, 'quantity' => 2, 'unit_price' => 240, 'line_total' => 480]);
    }

    public function test_ready_product_selection_includes_catalog_price_and_excludes_inactive_products(): void
    {
        $material = Material::factory()->create(['is_sellable_ready_product' => true, 'sales_sku' => 'READY-PRICE-1', 'sales_price' => 325.50]);
        $this->actingAs($this->user)->getJson('/api/search/ready-products?q=READY-PRICE-1')
            ->assertJsonPath('0.id', $material->id)->assertJsonPath('0.sales_price', 325.5);

        $material->update(['is_active' => false]);
        $this->getJson('/api/search/ready-products?q=READY-PRICE-1')->assertExactJson([]);
    }

    public function test_can_create_customer_order(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'customer_reference' => 'PO-2026-88',
            'order_date' => '2026-09-15',
            'requested_delivery_date' => '2026-10-01',
            'priority' => 'NORMAL',
            'commercial_notes' => 'Urgent customer order',
            'lines' => [
                [
                    'custom_design' => 1,
                    'custom_design_name' => 'Custom Dining Table',
                    'requested_width_cm' => 100,
                    'requested_length_cm' => 200,
                    'quantity' => 1,
                    'unit_price' => 2500.00,
                    'notes' => 'Oak wood finish',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.orders.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_orders', [
            'customer_id' => $this->customer->id,
            'customer_reference' => 'PO-2026-88',
            'status' => 'DRAFT',
            'total_amount' => 2500.00,
        ]);

        $this->assertDatabaseHas('customer_order_lines', [
            'custom_design' => 1,
            'custom_design_name' => 'Custom Dining Table',
            'quantity' => 1,
            'unit_price' => 2500.00,
            'line_total' => 2500.00,
        ]);
    }

    public function test_can_submit_order_for_production_review(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);
        CustomerOrderLine::factory()->create(['customer_order_id' => $order->id]);

        $response = $this->actingAs($this->user)->post(route('sales.orders.submit-review', $order));

        $response->assertRedirect(route('sales.orders.show', $order));
        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);
    }

    public function test_can_cancel_customer_order(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->post(route('sales.orders.cancel', $order));

        $response->assertRedirect(route('sales.orders.show', $order));
        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_can_create_customer_order_via_form_fields_with_model_configuration(): void
    {
        $model = ProductModel::firstOrCreate([
            'model_code' => 'MOD-ORDER-TEST',
        ], [
            'name_ar' => 'موديل تجربة طلب',
            'is_active' => true,
        ]);

        $config = ProductConfiguration::firstOrCreate([
            'product_model_id' => $model->id,
            'width_cm' => 180.0,
            'length_cm' => 200.0,
            'has_storage' => true,
        ], [
            'configuration_code' => 'CFG-TEST-ORDER',
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'customer_po_number' => 'PO-FORM-TEST-100',
            'order_date' => '2026-09-15',
            'promised_delivery_date' => '2026-10-05',
            'priority' => 'URGENT',
            'notes' => 'ملاحظات تجارية للطلب التجريبي',
            'lines' => [
                [
                    'custom_design' => 0,
                    'product_model_id' => $model->id,
                    'product_configuration_id' => $config->id,
                    'quantity' => 2,
                    'unit_price' => 1500.00,
                    'notes' => 'بند مع تخزين مزدوج 180x200',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.orders.store'), $payload);

        $order = CustomerOrder::where('external_order_reference', 'PO-FORM-TEST-100')->first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('sales.orders.show', $order));

        $this->assertEquals('URGENT', $order->priority);
        $this->assertEquals(3000.00, (float) $order->total_amount);
        $this->assertEquals('ملاحظات تجارية للطلب التجريبي', $order->commercial_notes);

        $this->assertDatabaseHas('customer_order_lines', [
            'customer_order_id' => $order->id,
            'product_model_id' => $model->id,
            'product_configuration_id' => $config->id,
            'requested_width_cm' => 180.0,
            'requested_length_cm' => 200.0,
            'quantity' => 2,
            'unit_price' => 1500.00,
            'line_total' => 3000.00,
        ]);
    }

    public function test_can_render_show_customer_order_page_with_lines_and_production_orders(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 5,
            'unit_price' => 100,
            'line_total' => 500,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.orders.show', $order));

        $response->assertOk();
        $response->assertViewHas('order');
    }
}
