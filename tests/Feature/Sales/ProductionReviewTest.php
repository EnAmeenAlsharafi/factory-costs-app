<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $productionManager;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::where('name', 'production_manager')->first() ?? Role::where('name', 'admin')->first();

        $this->productionManager = User::factory()->create([
            'username' => 'prod_mgr_user',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::first();
        $this->salesChannel = SalesChannel::first();
    }

    public function test_production_manager_can_review_and_approve_order_for_production(): void
    {
        $order = CustomerOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1200.00,
            'line_total' => 1200.00,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-TEST-888',
            'name_ar' => 'موديل تجريبي',
            'is_active' => true,
        ]);

        $config = ProductConfiguration::create([
            'configuration_code' => 'CFG-TEST-888',
            'product_model_id' => $model->id,
            'width_cm' => 180,
            'length_cm' => 200,
            'is_active' => true,
        ]);

        $recipe = ManufacturingRecipe::create([
            'recipe_code' => 'BOM-TEST-888',
            'name' => 'وصفة موديل تجريبي',
            'target_type' => 'PRODUCT_CONFIGURATION',
            'product_configuration_id' => $config->id,
            'created_by_user_id' => $this->productionManager->id,
            'is_active' => true,
        ]);

        $recipeVersion = ManufacturingRecipeVersion::create([
            'manufacturing_recipe_id' => $recipe->id,
            'version_number' => 1,
            'version_code' => 'V1.0',
            'is_approved' => true,
            'created_by_user_id' => $this->productionManager->id,
        ]);

        $payload = [
            'review_notes' => 'Production feasibility approved',
            'lines' => [
                $line->id => [
                    'reference_width_cm' => 180,
                    'reference_length_cm' => 200,
                    'recipe_version_id' => $recipeVersion->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->productionManager)
            ->post(route('sales.orders.approve-production', $order), $payload);

        $response->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('customer_orders', [
            'id' => $order->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'production_approved_by_user_id' => $this->productionManager->id,
        ]);

        $this->assertDatabaseHas('customer_order_lines', [
            'id' => $line->id,
            'reference_width_cm' => 180,
            'reference_length_cm' => 200,
        ]);
    }
}
