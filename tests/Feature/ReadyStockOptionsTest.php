<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Material;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyStockOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_options_can_be_created_together_and_updated_without_creating_duplicates(): void
    {
        $this->signIn();
        $parent = $this->product(['sales_display_name' => 'مرتبة جاردن']);
        $url = route('sales.ready-stock-products.options.store', $parent);
        $this->get(route('sales.ready-stock-products.options', $parent))->assertSee('إضافة المقاسات الشائعة');
        $rows = [['label' => '160×200', 'sales_sku' => 'GARDEN-160', 'sales_price' => 460, 'is_active' => 1],
            ['label' => '180×200', 'sales_sku' => 'GARDEN-180', 'sales_price' => 570, 'is_active' => 1]];

        $this->post($url, ['options' => $rows])->assertSessionHasNoErrors();

        $this->assertTrue($parent->fresh()->has_ready_options);
        $options = $parent->readyOptions()->orderBy('id')->get();
        $this->assertCount(2, $options);
        $this->assertSame('مرتبة جاردن — 160×200', $options[0]->sales_display_name);
        $rows[0]['id'] = $options[0]->id;
        $rows[1]['id'] = $options[1]->id;
        $rows[0]['sales_price'] = 490;
        $rows[1]['is_active'] = 0;
        $this->post($url, ['options' => $rows])->assertSessionHasNoErrors();

        $this->assertSame(2, $parent->readyOptions()->count());
        $this->assertSame('490.0000', $options[0]->fresh()->sales_price);
        $this->assertFalse($options[1]->fresh()->is_active);
    }

    public function test_product_search_exposes_parent_and_options_endpoint_only_returns_its_active_options(): void
    {
        $this->signIn();
        $parent = $this->product(['has_ready_options' => true, 'sales_sku' => 'GARDEN']);
        $active = $this->product(['ready_parent_id' => $parent->id, 'ready_option_label' => '160×200', 'sales_price' => 460]);
        $this->product(['ready_parent_id' => $parent->id, 'is_active' => false]);
        $this->product(['ready_parent_id' => $this->product(['has_ready_options' => true])->id]);

        $this->getJson('/api/search/ready-products?q=GARDEN')->assertJsonPath('0.id', $parent->id)->assertJsonPath('0.has_ready_options', true);
        $this->getJson('/api/search/ready-products?parent_id='.$parent->id)
            ->assertJsonCount(1)->assertJsonPath('0.id', $active->id)->assertJsonPath('0.option_label', '160×200')->assertJsonPath('0.sales_price', 460);
    }

    public function test_duplicate_options_and_cross_parent_ids_are_rejected(): void
    {
        $this->signIn();
        $parent = $this->product();
        $foreign = $this->product(['ready_parent_id' => $this->product(['has_ready_options' => true])->id]);
        $row = ['label' => '160×200', 'sales_sku' => 'G-160', 'sales_price' => 460, 'is_active' => 1];
        $url = route('sales.ready-stock-products.options.store', $parent);

        $this->post($url, ['options' => [$row, $row]])->assertSessionHasErrors('options.0.label');
        $this->post($url, ['options' => [array_merge($row, ['id' => $foreign->id])]])->assertSessionHasErrors('options.0.id');
        $foreign->update(['sales_sku' => 'TAKEN-SKU']);
        $this->post($url, ['options' => [array_merge($row, ['sales_sku' => 'TAKEN-SKU'])]])->assertSessionHasErrors('options.0.sales_sku');

        $this->assertSame(0, $parent->readyOptions()->count());
        $this->assertFalse($parent->fresh()->has_ready_options);
    }

    public function test_order_stores_the_selected_option_and_parent_cannot_be_saved_as_the_sold_item(): void
    {
        $this->signIn();
        $parent = $this->product(['has_ready_options' => true]);
        $option = $this->product(['ready_parent_id' => $parent->id, 'ready_option_label' => '160×200', 'sales_price' => 460]);
        $payload = ['customer_id' => Customer::firstOrFail()->id, 'sales_channel_id' => SalesChannel::firstOrFail()->id,
            'order_date' => '2026-11-05', 'priority' => 'NORMAL',
            'lines' => [['line_type' => 'READY_STOCK', 'material_id' => $option->id, 'quantity' => 2, 'unit_price' => 460]]];

        $this->post(route('sales.orders.store'), $payload)->assertSessionHasNoErrors();

        $order = CustomerOrder::latest('id')->firstOrFail();
        $this->assertDatabaseHas('customer_order_lines', ['customer_order_id' => $order->id, 'material_id' => $option->id, 'line_total' => 920]);
        $this->get(route('sales.orders.edit', $order))->assertSee('ready_parent_id', false)->assertSee('ready-option-select', false);
        $payload['lines'][0]['material_id'] = $parent->id;
        $this->post(route('sales.orders.store'), $payload)->assertSessionHasErrors('lines.0.material_id');
    }

    public function test_customer_accounts_cannot_manage_internal_product_options(): void
    {
        $parent = $this->product();
        $client = User::factory()->create(['role_id' => Role::where('name', 'customer_portal')->value('id'), 'customer_id' => Customer::factory()->create()->id, 'is_active' => true]);

        $this->actingAs($client)->post(route('sales.ready-stock-products.options.store', $parent), ['options' => []])->assertForbidden();
        $this->assertSame(0, $parent->readyOptions()->count());
    }

    public function test_staff_without_material_management_permission_cannot_change_options(): void
    {
        $parent = $this->product();
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->value('id'), 'is_active' => true]);
        $this->actingAs($worker)->get(route('sales.ready-stock-products.options', $parent))->assertForbidden();
        $this->post(route('sales.ready-stock-products.options.store', $parent), ['options' => []])->assertForbidden();
        $this->assertSame(0, $parent->readyOptions()->count());
    }

    protected function signIn(): void
    {
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
    }

    /** @param array<string, mixed> $attributes */
    protected function product(array $attributes = []): Material
    {
        return Material::factory()->create(array_merge(['is_sellable_ready_product' => true, 'is_active' => true], $attributes));
    }
}
