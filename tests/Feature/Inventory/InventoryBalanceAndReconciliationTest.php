<?php

namespace Tests\Feature\Inventory;

use App\Models\Department;
use App\Models\InventoryAdjustmentReason;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBalanceAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Warehouse $warehouse;

    protected Department $department;

    protected Supplier $supplier;

    protected UnitOfMeasure $meter;

    protected Material $material;

    protected InventoryAdjustmentReason $reason;

    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_recon_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::first();
        $this->department = Department::where('code', 'CARPENTRY')->first() ?? Department::first();
        $this->supplier = Supplier::first() ?? Supplier::create(['name' => 'المورد الأول', 'supplier_code' => 'SUP-001', 'is_active' => true]);
        $this->meter = UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::first();
        $this->reason = InventoryAdjustmentReason::where('code', 'DAMAGED_IN_WAREHOUSE')->first() ?? InventoryAdjustmentReason::first();

        $category = MaterialCategory::first();
        $this->material = Material::create([
            'material_category_id' => $category->id,
            'name_ar' => 'قماش عباية أسود ممتاز',
            'code' => 'MAT-FAB-999',
            'material_type' => 'FABRIC',
            'base_unit_id' => $this->meter->id,
            'is_active' => true,
        ]);

        $this->inventoryService = app(InventoryService::class);
    }

    public function test_full_lifecycle_reconciliation_and_ledger_invariant(): void
    {
        // 1. Receive Receipt 1 -> Lot 1 (100 meters @ 10 SAR)
        $receipt1 = $this->inventoryService->createReceipt([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-09-01',
            'items' => [
                [
                    'material_id' => $this->material->id,
                    'quantity' => 100,
                    'unit_id' => $this->meter->id,
                    'unit_cost' => 10.0,
                    'lot_number' => 'LOT-001',
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postReceipt($receipt1, $this->adminUser);

        // Receive Receipt 2 -> Lot 2 (50 meters @ 12 SAR)
        $receipt2 = $this->inventoryService->createReceipt([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-09-02',
            'items' => [
                [
                    'material_id' => $this->material->id,
                    'quantity' => 50,
                    'unit_id' => $this->meter->id,
                    'unit_cost' => 12.0,
                    'lot_number' => 'LOT-002',
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postReceipt($receipt2, $this->adminUser);

        $receipt1->refresh();
        $receipt2->refresh();
        $lot1 = $receipt1->lines->first()->lot;
        $lot2 = $receipt2->lines->first()->lot;

        // 2. Issue 40 meters from Lot 1
        $issue1 = $this->inventoryService->createIssue([
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'issue_date' => '2026-09-03',
            'items' => [
                [
                    'lot_id' => $lot1->id,
                    'quantity' => 40.0,
                    'unit_id' => $this->meter->id,
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postIssue($issue1, $this->adminUser);

        // 3. Issue 20 meters from Lot 2
        $issue2 = $this->inventoryService->createIssue([
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'issue_date' => '2026-09-04',
            'items' => [
                [
                    'lot_id' => $lot2->id,
                    'quantity' => 20.0,
                    'unit_id' => $this->meter->id,
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postIssue($issue2, $this->adminUser);

        // 4. Return 10 meters back to Lot 1
        $return1 = $this->inventoryService->createReturn([
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'return_date' => '2026-09-05',
            'items' => [
                [
                    'lot_id' => $lot1->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->meter->id,
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postReturn($return1, $this->adminUser);

        // 5. Adjust DECREASE 5 meters from Lot 2
        $adjustment1 = $this->inventoryService->createAdjustment([
            'warehouse_id' => $this->warehouse->id,
            'reason_id' => $this->reason->id,
            'adjustment_date' => '2026-09-06',
            'items' => [
                [
                    'adjustment_type' => 'DECREASE',
                    'lot_id' => $lot2->id,
                    'quantity' => 5.0,
                ],
            ],
        ], $this->adminUser->id);
        $this->inventoryService->postAdjustment($adjustment1, $this->adminUser);

        // Refresh model instances
        $lot1->refresh();
        $lot2->refresh();

        // Check Lot 1 remaining quantity: 100 - 40 + 10 = 70
        $this->assertEquals(70.0, (float) $lot1->remaining_quantity);

        // Check Lot 2 remaining quantity: 50 - 20 - 5 = 25
        $this->assertEquals(25.0, (float) $lot2->remaining_quantity);

        // Check Reconciliation method for both lots
        $recon1 = $this->inventoryService->reconcileLotBalance($lot1);
        $recon2 = $this->inventoryService->reconcileLotBalance($lot2);

        $this->assertTrue($recon1['is_reconciled']);
        $this->assertTrue($recon2['is_reconciled']);

        // Check Material Aggregate Stock Balance: SUM(IN) - SUM(OUT) across all lots
        $stockBalance = $this->inventoryService->calculateMaterialStockBalance($this->material->id);
        $this->assertEquals(95.0, (float) $stockBalance);

        // Check Total Material Valuation: (70 * 10) + (25 * 12) = 700 + 300 = 1000 SAR
        $stockValuation = $this->inventoryService->calculateMaterialValuation($this->material->id);
        $this->assertEquals(1000.0, (float) $stockValuation);

        // Access Stock Balances Index UI route to confirm HTTP rendering
        $response = $this->actingAs($this->adminUser)->get(route('inventory.balances.index'));
        $response->assertOk();
        $response->assertSee('95.00');
        $response->assertSee('1,000.00');
    }

    public function test_conversion_snapshot_immutability_on_posted_receipt_lines(): void
    {
        $rollUnit = UnitOfMeasure::create([
            'name_ar' => 'رول قماش اختبار',
            'code' => 'ROLL_TEST_SNAP',
            'allows_decimal' => false,
            'is_active' => true,
        ]);

        $conv = $this->material->conversions()->create([
            'from_unit_id' => $rollUnit->id,
            'to_unit_id' => $this->meter->id,
            'conversion_factor' => 50.0,
        ]);

        $receipt = $this->inventoryService->createReceipt([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-09-01',
            'items' => [
                [
                    'material_id' => $this->material->id,
                    'quantity' => 2, // 2 rolls * 50 = 100 meters
                    'unit_id' => $rollUnit->id,
                    'unit_cost' => 500.0,
                ],
            ],
        ], $this->adminUser->id);

        $this->inventoryService->postReceipt($receipt, $this->adminUser);

        $line = $receipt->lines->first();
        $this->assertEquals(50.0, (float) $line->conversion_factor);
        $this->assertEquals(100.0, (float) $line->base_quantity);

        // Mutate conversion rule in database later (e.g., 1 Roll = 60 Meters)
        $conv->update(['conversion_factor' => 60.0]);

        // Assert posted receipt line conversion factor and base quantity remain unchanged
        $line->refresh();
        $this->assertEquals(50.0, (float) $line->conversion_factor);
        $this->assertEquals(100.0, (float) $line->base_quantity);
    }
}
