<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderChangeWarningTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected CustomerOrder $order;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000800',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-TEST-006', 'name' => 'Test Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 10,
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        $poService = app(ProductionOrderService::class);
        $this->po = $poService->createFromOrderLine($line, ['released_quantity' => 10, 'manufacturing_recipe_version_id' => $version->id]);
    }

    public function test_customer_order_post_approval_change_flags_warning_on_production_order(): void
    {
        $this->assertFalse($this->po->has_customer_order_changed);

        // Customer Service or sales requests a revision, changing Customer Order back to PENDING_PRODUCTION_REVIEW
        $this->order->status = 'PENDING_PRODUCTION_REVIEW';
        $this->order->save();

        $this->po->refresh();

        // Flag warning evaluates to true
        $this->assertTrue($this->po->has_customer_order_changed);

        // Production Order snapshot is preserved and not mutated
        $this->assertEquals(160, (int) $this->po->requested_width_cm);
        $this->assertEquals(200, (int) $this->po->requested_length_cm);
        $this->assertEquals(10, $this->po->released_quantity);
    }
}
