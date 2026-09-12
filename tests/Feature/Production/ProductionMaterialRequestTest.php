<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionMaterialRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $warehouseKeeper;

    protected ProductionOrder $productionOrder;

    protected Material $material;

    protected Warehouse $warehouse;

    protected InventoryLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $prodRole = Role::where('name', 'production_manager')->first();
        $whRole = Role::where('name', 'warehouse_keeper')->first();

        $this->manager = User::factory()->create([
            'role_id' => $prodRole->id,
            'is_active' => true,
        ]);

        $this->warehouseKeeper = User::factory()->create([
            'role_id' => $whRole->id,
            'is_active' => true,
        ]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000200',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 3000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $category = MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true]);
        $unit = UnitOfMeasure::first();
        $this->material = Material::first() ?? Material::create([
            'code' => 'MAT-TEST-001',
            'name_ar' => 'خشب سويدي 2×4',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'average_unit_cost' => 10.00,
            'is_active' => true,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 5,
            'unit_price' => 600,
            'total_price' => 3000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000200',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 5,
            'released_quantity' => 5,
            'status' => 'RELEASED',
        ]);

        $this->warehouse = Warehouse::where('type', 'RAW_MATERIALS')->first() ?? Warehouse::first();

        // Create inventory lot for testing fulfillment
        $this->lot = InventoryLot::create([
            'lot_code' => 'LOT-TEST-001',
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'original_quantity' => 100,
            'remaining_quantity' => 100,
            'base_unit_id' => $this->material->base_unit_id,
            'unit_cost' => 50.00,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_can_create_material_request_for_production_order(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.material-requests.store'), [
            'production_order_id' => $this->productionOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'request_date' => now()->toDateString(),
            'notes' => 'طلب خامات أولي للورشة',
            'lines' => [
                [
                    'material_id' => $this->material->id,
                    'requested_quantity' => 20,
                    'base_unit_id' => $this->material->base_unit_id,
                    'request_reason' => 'PLANNED_PRODUCTION',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('production_material_requests', 1);

        $request = ProductionMaterialRequest::first();
        $this->assertEquals('DRAFT', $request->status);
        $this->assertCount(1, $request->lines);
        $this->assertEquals(20, (float) $request->lines->first()->requested_quantity);
    }

    public function test_can_submit_and_fulfill_material_request(): void
    {
        $service = app(ProductionMaterialRequestService::class);
        $pmr = $service->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                [
                    'material_id' => $this->material->id,
                    'requested_quantity' => 10,
                    'base_unit_id' => $this->material->base_unit_id,
                ],
            ],
        ]);

        $service->submitRequest($pmr);
        $this->assertEquals('SUBMITTED', $pmr->fresh()->status);

        $line = $pmr->lines->first();
        $issue = $service->fulfillRequest($pmr, $this->warehouseKeeper, [
            [
                'request_line_id' => $line->id,
                'inventory_lot_id' => $this->lot->id,
                'quantity' => 10,
            ],
        ]);

        $this->assertEquals('FULFILLED', $pmr->fresh()->status);
        $this->assertEquals(10, (float) $pmr->fresh()->lines->first()->issued_quantity);
        $this->assertEquals(90, (float) $this->lot->fresh()->remaining_quantity);
        $this->assertEquals('POSTED', $issue->status);
    }
}
