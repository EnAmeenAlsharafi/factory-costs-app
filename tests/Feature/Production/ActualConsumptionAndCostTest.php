<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductionCostService;
use App\Services\ProductionMaterialRequestService;
use App\Services\QualityIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualConsumptionAndCostTest extends TestCase
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

        $this->manager = User::factory()->create(['role_id' => $prodRole->id, 'is_active' => true]);
        $this->warehouseKeeper = User::factory()->create(['role_id' => $whRole->id, 'is_active' => true]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000201',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 4000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $category = MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true]);
        $unit = UnitOfMeasure::first();
        $this->material = Material::first() ?? Material::create([
            'code' => 'MAT-TEST-002',
            'name_ar' => 'خشب سويدي 2×4',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'average_unit_cost' => 10.00,
            'is_active' => true,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 2,
            'unit_price' => 2000,
            'total_price' => 4000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000201',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 2,
            'released_quantity' => 2,
            'status' => 'RELEASED',
        ]);

        $this->warehouse = Warehouse::where('type', 'RAW_MATERIALS')->first() ?? Warehouse::first();

        $this->lot = InventoryLot::create([
            'lot_code' => 'LOT-COST-001',
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'original_quantity' => 100,
            'remaining_quantity' => 100,
            'base_unit_id' => $this->material->base_unit_id,
            'unit_cost' => 10.00,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_net_actual_cost_equals_issued_cost_minus_returned_cost(): void
    {
        $pmrService = app(ProductionMaterialRequestService::class);
        $inventoryService = app(InventoryService::class);
        $costService = app(ProductionCostService::class);

        // Issue 50 units @ 10.00 = 500.00
        $pmr = $pmrService->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['material_id' => $this->material->id, 'requested_quantity' => 50, 'base_unit_id' => $this->material->base_unit_id]],
        ]);
        $pmrService->submitRequest($pmr);
        $issue = $pmrService->fulfillRequest($pmr, $this->warehouseKeeper, [
            ['request_line_id' => $pmr->lines->first()->id, 'inventory_lot_id' => $this->lot->id, 'quantity' => 50],
        ]);

        // Return 10 unused units @ 10.00 = 100.00 back to warehouse
        $issueLine = $issue->lines->first();
        $ret = $inventoryService->createReturn([
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'items' => [
                ['original_issue_line_id' => $issueLine->id, 'returned_quantity' => 10],
            ],
        ], $this->warehouseKeeper->id);
        $ret->update(['production_order_id' => $this->productionOrder->id]);
        $inventoryService->postReturn($ret, $this->warehouseKeeper);

        // Calculate Cost: Issued 500 - Returned 100 = Net 400
        $summary = $costService->calculateOrderMaterialCost($this->productionOrder);

        $this->assertEquals(500.00, $summary['total_issued_cost']);
        $this->assertEquals(100.00, $summary['total_returned_cost']);
        $this->assertEquals(400.00, $summary['actual_net_material_cost']);
    }

    public function test_waste_cost_is_reported_analytically_without_double_counting(): void
    {
        $pmrService = app(ProductionMaterialRequestService::class);
        $costService = app(ProductionCostService::class);

        // Issue 30 units @ 10.00 = 300.00
        $pmr = $pmrService->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['material_id' => $this->material->id, 'requested_quantity' => 30, 'base_unit_id' => $this->material->base_unit_id]],
        ]);
        $pmrService->submitRequest($pmr);
        $pmrService->fulfillRequest($pmr, $this->warehouseKeeper, [
            ['request_line_id' => $pmr->lines->first()->id, 'inventory_lot_id' => $this->lot->id, 'quantity' => 30],
        ]);

        // Record 5 units waste @ 10.00 = 50.00
        $reason = ProductionWasteReason::first();
        ProductionWasteRecord::create([
            'waste_number' => 'WST-TEST-001',
            'production_order_id' => $this->productionOrder->id,
            'material_id' => $this->material->id,
            'quantity' => 5,
            'unit_id' => $this->material->base_unit_id,
            'unit_cost' => 10.00,
            'total_cost' => 50.00,
            'waste_reason_id' => $reason->id,
            'recorded_by_user_id' => $this->manager->id,
            'occurred_at' => now(),
        ]);

        $summary = $costService->calculateOrderMaterialCost($this->productionOrder);

        // Net actual cost remains 300.00 (the physical issue cost). Waste cost 50.00 is reported analytically subset!
        $this->assertEquals(300.00, $summary['actual_net_material_cost']);
        $this->assertEquals(50.00, $summary['total_waste_cost']);
    }

    public function test_rework_material_cost_is_included_exactly_once_end_to_end(): void
    {
        $materialRequests = app(ProductionMaterialRequestService::class);
        $quality = app(QualityIncidentService::class);

        $normalRequest = $materialRequests->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'material_id' => $this->material->id,
                'requested_quantity' => 10,
                'base_unit_id' => $this->material->base_unit_id,
                'request_reason' => 'PLANNED_PRODUCTION',
            ]],
        ]);
        $materialRequests->submitRequest($normalRequest);
        $materialRequests->fulfillRequest($normalRequest, $this->warehouseKeeper, [[
            'request_line_id' => $normalRequest->lines()->first()->id,
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 10,
        ]]);

        $department = Department::firstOrFail();
        $incident = $quality->createIncident($this->productionOrder, $this->manager, [
            'affected_quantity' => 1,
            'detected_department_id' => $department->id,
            'responsible_department_id' => $department->id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'اختبار تكلفة إعادة التصنيع',
            'severity' => 'HIGH',
            'disposition' => 'REWORK',
        ]);

        $reworkRequest = $materialRequests->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'material_id' => $this->material->id,
                'requested_quantity' => 5,
                'base_unit_id' => $this->material->base_unit_id,
                'request_reason' => 'REWORK',
            ]],
        ]);
        $materialRequests->submitRequest($reworkRequest);
        $materialRequests->fulfillRequest($reworkRequest, $this->warehouseKeeper, [[
            'request_line_id' => $reworkRequest->lines()->first()->id,
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 5,
        ]]);
        $quality->completeReworkAction($incident->reworkActions()->firstOrFail(), $this->manager);

        $summary = app(ProductionCostService::class)->calculateOrderMaterialCost($this->productionOrder);

        $this->assertSame(150.0, $summary['total_issued_cost']);
        $this->assertSame(50.0, $summary['rework_issued_cost']);
        $this->assertSame(150.0, $summary['actual_net_material_cost']);
        $this->assertSame('RESOLVED', $incident->fresh()->status);
    }
}
