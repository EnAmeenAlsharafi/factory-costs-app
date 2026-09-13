<?php

namespace Tests\Feature\Purchasing;

use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceivingService;
use App\Services\PurchaseRequestService;
use App\Services\SupplierQuotationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceivingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Warehouse $warehouse;

    protected Material $material;

    protected UnitOfMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->supplierA = Supplier::create(['supplier_code' => 'SUP-A', 'name' => 'المورد أ (تسليم سريي)', 'is_active' => true]);
        $this->supplierB = Supplier::create(['supplier_code' => 'SUP-B', 'name' => 'المورد ب (تسليم بطيء)', 'is_active' => true]);

        $this->warehouse = Warehouse::create(['code' => 'RAW_MATERIALS', 'name_ar' => 'مستودع الخامات', 'is_active' => true]);
        $category = MaterialCategory::create(['code' => 'WOOD', 'name_ar' => 'أخشاب', 'is_active' => true]);
        $this->unit = UnitOfMeasure::create(['code' => 'BOARD', 'name_ar' => 'لوح', 'is_active' => true]);

        $this->material = Material::create([
            'material_category_id' => $category->id,
            'code' => 'MAT-WOOD-TEST',
            'name_ar' => 'لوح خشب اختبار',
            'base_unit_id' => $this->unit->id,
            'min_stock_level' => 10,
            'reorder_point' => 20,
            'is_active' => true,
        ]);

        // Add initial opening stock of 10 boards
        InventoryLot::create([
            'lot_code' => 'LOT-INIT-01',
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'original_quantity' => 10,
            'remaining_quantity' => 10,
            'base_unit_id' => $this->unit->id,
            'unit_cost' => 15.0,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_full_manual_end_to_end_procurement_scenario(): void
    {
        $prService = app(PurchaseRequestService::class);
        $sqService = app(SupplierQuotationService::class);
        $poService = app(PurchaseOrderService::class);
        $receivingService = app(PurchaseReceivingService::class);

        // 1. Create Purchase Request for 100 boards and approve
        $pr = $prService->createRequest([
            'warehouse_id' => $this->warehouse->id,
            'priority' => 'NORMAL',
            'justification' => 'تغطية نقص رصيد الخامات بعد الوصول لحد الإعادة',
            'lines' => [
                ['material_id' => $this->material->id, 'requested_quantity' => 100],
            ],
        ], $this->manager);
        $prService->submitRequest($pr, $this->manager);
        $prService->reviewRequest($pr, $this->manager, 'APPROVE');
        $this->assertEquals('APPROVED', $pr->status);

        // 2. Record Quotations from Supplier A & Supplier B
        $quoteA = $sqService->recordQuotation([
            'supplier_id' => $this->supplierA->id,
            'quotation_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $pr->lines->first()->id,
                    'material_id' => $this->material->id,
                    'quoted_quantity' => 100,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 30,
                    'lead_time_days' => 4,
                ],
            ],
        ], $this->manager);

        $quoteB = $sqService->recordQuotation([
            'supplier_id' => $this->supplierB->id,
            'quotation_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $pr->lines->first()->id,
                    'material_id' => $this->material->id,
                    'quoted_quantity' => 100,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 29,
                    'lead_time_days' => 10,
                ],
            ],
        ], $this->manager);

        // Select Supplier A manually due to 4-day lead time
        $sqService->selectQuotation($quoteA, $this->manager);
        $this->assertEquals('SELECTED', $quoteA->refresh()->status);

        // 3. Create PO for Supplier A (100 boards × 30 SAR) & Approve
        $po = $poService->createOrder([
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_request_id' => $pr->id,
            'supplier_quotation_id' => $quoteA->id,
            'order_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $pr->lines->first()->id,
                    'supplier_quotation_line_id' => $quoteA->lines->first()->id,
                    'material_id' => $this->material->id,
                    'ordered_quantity' => 100,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 30,
                ],
            ],
        ], $this->manager);

        $poService->approveOrder($po, $this->manager);
        $poService->markSent($po, $this->manager);
        $this->assertEquals('SENT', $po->status);

        // 4. First Receipt: 60 boards @ 30 SAR
        $receipt1Draft = $receivingService->createDraftReceiptFromPO($po, $this->manager, [
            'lines' => [
                $po->lines->first()->id => ['quantity_received' => 60, 'unit_cost_purchase' => 30],
            ],
        ]);
        $receipt1 = $receivingService->postLinkedReceipt($receipt1Draft, $this->manager);

        $po->refresh();
        $this->assertEquals('PARTIALLY_RECEIVED', $po->status);
        $this->assertEquals(60.0, $po->lines->first()->received_base_quantity);

        // Verify Inventory Lot A created with 30 SAR cost and inventory balance updated
        $currentBalance = (float) InventoryLot::where('material_id', $this->material->id)->where('status', 'ACTIVE')->sum('remaining_quantity');
        $this->assertEquals(70.0, $currentBalance);
        $lotA = InventoryLot::where('receipt_line_id', $receipt1->lines->first()->id)->first();
        $this->assertNotNull($lotA);
        $this->assertEquals(30.0, (float) $lotA->unit_cost);

        // 5. Attempt Over-Receipt of 41 boards when remaining is 40 (Must fail)
        $failedOverReceipt = false;
        try {
            $overDraft = $receivingService->createDraftReceiptFromPO($po, $this->manager, [
                'lines' => [
                    $po->lines->first()->id => ['quantity_received' => 41, 'unit_cost_purchase' => 31],
                ],
            ]);
            $receivingService->postLinkedReceipt($overDraft, $this->manager);
        } catch (\Exception $e) {
            $failedOverReceipt = true;
            $this->assertStringContainsString('تتجاوز الكمية المتبقية بأمر الشراء', $e->getMessage());
        }
        $this->assertTrue($failedOverReceipt, 'Over-receipt attempt of 41 when 40 remains must be rejected.');

        // 6. Second Receipt: 40 boards @ actual price 31 SAR
        $receipt2Draft = $receivingService->createDraftReceiptFromPO($po, $this->manager, [
            'lines' => [
                $po->lines->first()->id => ['quantity_received' => 40, 'unit_cost_purchase' => 31],
            ],
        ]);
        $receipt2 = $receivingService->postLinkedReceipt($receipt2Draft, $this->manager);

        $po->refresh();
        $this->assertEquals('RECEIVED', $po->status);
        $this->assertEquals(100.0, $po->lines->first()->received_base_quantity);

        // Verify total inventory balance = 10 (initial) + 60 + 40 = 110 boards
        $totalBalance = (float) InventoryLot::where('material_id', $this->material->id)->where('status', 'ACTIVE')->sum('remaining_quantity');
        $this->assertEquals(110.0, $totalBalance);

        // Verify Inventory Lot B created with actual 31 SAR cost (distinct from Lot A)
        $lotB = InventoryLot::where('receipt_line_id', $receipt2->lines->first()->id)->first();
        $this->assertNotNull($lotB);
        $this->assertEquals(31.0, (float) $lotB->unit_cost);

        // 7. Verify PO agreed price remains 30 SAR and Price Variance = +1 SAR/unit on second receipt (+40 SAR total)
        $this->assertEquals(30.0, (float) $po->lines->first()->unit_price);
        $varianceSummary = $receivingService->getPoPriceVarianceSummary($po);
        $this->assertEquals(3000.0, $varianceSummary['total_expected_cost']); // 100 * 30
        $this->assertEquals(3040.0, $varianceSummary['total_actual_cost']); // (60 * 30) + (40 * 31)
        $this->assertEquals(40.0, $varianceSummary['net_variance']);
    }
}
