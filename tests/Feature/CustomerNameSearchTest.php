<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionRouting;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production lists show and search the customer by its real `name` column (customers have no name_ar).
 */
class CustomerNameSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->customer = Customer::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000980',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 500.00,
            'created_by_user_id' => $this->admin->id,
        ]);
        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-980', 'name' => 'Test Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
        ]);

        $orders = app(ProductionOrderService::class);
        $this->po = $orders->createFromOrderLine($line, ['released_quantity' => 1, 'manufacturing_recipe_version_id' => $version->id]);
        $orders->releaseProductionOrder($this->po, ProductionRouting::where('routing_code', 'RTG-000001')->first(), $this->admin);
    }

    public function test_production_orders_list_shows_the_customer_name(): void
    {
        $this->actingAs($this->admin)->get(route('production.orders.index'))
            ->assertOk()
            ->assertSee($this->customer->name);
    }

    public function test_production_orders_can_be_searched_by_customer_name(): void
    {
        $response = $this->actingAs($this->admin)->get(route('production.orders.index', ['search' => $this->customer->name]));

        $response->assertOk();
        $this->assertContains($this->po->id, $response->viewData('orders')->pluck('id'));
    }

    public function test_finished_goods_can_be_searched_by_customer_name(): void
    {
        $this->actingAs($this->admin)->get(route('finished-goods.index', ['search' => $this->customer->name]))
            ->assertOk();
    }
}
