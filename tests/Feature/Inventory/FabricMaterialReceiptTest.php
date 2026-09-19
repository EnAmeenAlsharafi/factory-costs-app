<?php

namespace Tests\Feature\Inventory;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialReceipt;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductionMaterialRequestService;
use App\Services\PurchaseReceivingService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricMaterialReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Warehouse $warehouse;

    protected Supplier $supplierGulfCorner;

    protected UnitOfMeasure $meter;

    protected MaterialCategory $fabricCategory;

    protected MaterialCategory $woodCategory;

    protected Material $boucleFabric;

    protected Material $mdfWood;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'warehouse_keeper_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::first();

        // 1. Supplier: ركن الخليج
        $this->supplierGulfCorner = Supplier::create([
            'name' => 'ركن الخليج',
            'supplier_code' => 'SUP-GULF-01',
            'is_active' => true,
        ]);

        $this->meter = UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::first();
        $this->fabricCategory = MaterialCategory::where('code', 'FABRIC')->firstOrFail();
        $this->woodCategory = MaterialCategory::where('code', 'WOOD')->firstOrFail();

        // 2. Material: قماش بوكلية (FABRIC)
        $this->boucleFabric = Material::create([
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش بوكلية',
            'code' => 'MAT-FAB-BOUCLE',
            'material_type' => 'FABRIC',
            'base_unit_id' => $this->meter->id,
            'purchase_unit_id' => $this->meter->id,
            'is_active' => true,
        ]);

        // 3. Non-fabric: خشب MDF
        $this->mdfWood = Material::create([
            'material_category_id' => $this->woodCategory->id,
            'name_ar' => 'خشب MDF 18مم',
            'code' => 'MAT-WOOD-MDF18',
            'material_type' => 'WOOD',
            'base_unit_id' => $this->meter->id,
            'purchase_unit_id' => $this->meter->id,
            'is_active' => true,
        ]);
    }

    /**
     * Section 22: Fabric Receipt Requires Color
     * Attempting to post a fabric receipt without color code must fail with clear Arabic validation
     * and must not create any InventoryLot or InventoryMovement.
     */
    public function test_fabric_receipt_requires_color_before_posting(): void
    {
        // Create draft receipt with null color
        $receipt = MaterialReceipt::create([
            'receipt_number' => 'REC-BOUCLE-001',
            'supplier_id' => $this->supplierGulfCorner->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by_user_id' => $this->adminUser->id,
            'received_by_user_id' => $this->adminUser->id,
            'receipt_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $line = $receipt->lines()->create([
            'material_id' => $this->boucleFabric->id,
            'purchase_unit_id' => $this->meter->id,
            'base_unit_id' => $this->meter->id,
            'quantity_received' => 10,
            'conversion_factor' => 1.0,
            'base_quantity' => 10,
            'unit_cost_purchase' => 85.0,
            'unit_cost_base' => 85.0,
            'total_cost' => 850.0,
            'fabric_color_code' => null,
            'fabric_color_id' => null,
        ]);

        // Attempt direct posting via service
        $service = app(InventoryService::class);
        try {
            $service->postReceipt($receipt, $this->adminUser);
            $this->fail('Expected exception for missing fabric color code was not thrown.');
        } catch (Exception $e) {
            $this->assertStringContainsString('يلزم إدخال رقم أو كود اللون لمادة القماش: قماش بوكلية', $e->getMessage());
        }

        // Also test HTTP post endpoint redirects with error flash
        $response = $this->actingAs($this->adminUser)
            ->from(route('inventory.receipts.show', $receipt))
            ->post(route('inventory.receipts.post', $receipt));

        $response->assertRedirect(route('inventory.receipts.show', $receipt));
        $response->assertSessionHas('error', 'يلزم إدخال رقم أو كود اللون لمادة القماش: قماش بوكلية');

        // Confirm receipt remains DRAFT and NO lots or movements are created
        $receipt->refresh();
        $this->assertEquals('DRAFT', $receipt->status);
        $this->assertEquals(0, InventoryLot::where('material_id', $this->boucleFabric->id)->count());
        $this->assertEquals(0, $receipt->lines()->first()->lot()->count());
    }

    /**
     * Section 23: Fabric Receipt With Color
     * Material: قماش بوكلية, Color: 204, Quantity: 10
     * Post succeeds -> Receipt POSTED, Lot created with color 204, Movement IN created.
     */
    public function test_fabric_receipt_with_color_posts_and_preserves_color_on_lot_and_movement(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.receipts.store'), [
                'supplier_id' => $this->supplierGulfCorner->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => date('Y-m-d'),
                'action' => 'post',
                'lines' => [
                    [
                        'material_id' => $this->boucleFabric->id,
                        'fabric_color_code' => '204',
                        'quantity_received' => 10,
                        'purchase_unit_id' => $this->meter->id,
                        'conversion_factor' => 1.0,
                        'unit_cost_purchase' => 85.0,
                        'lot_reference' => 'LOT-GULF-204',
                    ],
                ],
            ]);

        $receipt = MaterialReceipt::latest()->first();
        $this->assertNotNull($receipt);
        $response->assertRedirect(route('inventory.receipts.show', $receipt));

        $receipt->refresh();
        $this->assertEquals('POSTED', $receipt->status);

        // Verify receipt line holds color code
        $line = $receipt->lines()->first();
        $this->assertEquals('204', $line->fabric_color_code);

        // Verify Inventory Lot created with color 204
        $lot = InventoryLot::where('receipt_line_id', $line->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals($this->boucleFabric->id, $lot->material_id);
        $this->assertEquals($this->supplierGulfCorner->id, $lot->supplier_id);
        $this->assertEquals('204', $lot->fabric_color_code);
        $this->assertEquals(10, (float) $lot->original_quantity);
        $this->assertEquals(10, (float) $lot->remaining_quantity);
        $this->assertEquals(85.0, (float) $lot->unit_cost);

        // Verify Inventory Movement IN created with color 204
        $this->assertDatabaseHas('inventory_movements', [
            'reference_id' => $receipt->id,
            'movement_type' => 'RECEIPT',
            'direction' => 'IN',
            'material_id' => $this->boucleFabric->id,
            'fabric_color_code' => '204',
            'quantity' => 10,
            'unit_cost' => 85.0,
        ]);
    }

    /**
     * Section 24: Non-Fabric Receipt
     * Creating and posting wood receipt without color is allowed.
     */
    public function test_non_fabric_receipt_does_not_require_color(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.receipts.store'), [
                'supplier_id' => $this->supplierGulfCorner->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => date('Y-m-d'),
                'action' => 'post',
                'lines' => [
                    [
                        'material_id' => $this->mdfWood->id,
                        'fabric_color_code' => null,
                        'quantity_received' => 20,
                        'purchase_unit_id' => $this->meter->id,
                        'conversion_factor' => 1.0,
                        'unit_cost_purchase' => 120.0,
                    ],
                ],
            ]);

        $receipt = MaterialReceipt::latest()->first();
        $this->assertEquals('POSTED', $receipt->status);

        $lot = InventoryLot::where('material_id', $this->mdfWood->id)->first();
        $this->assertNotNull($lot);
        $this->assertNull($lot->fabric_color_code);
        $this->assertEquals(20, (float) $lot->remaining_quantity);
    }

    /**
     * Section 25: PO Prefill
     * PO line with Fabric Color 204 prefills the receipt line color code when receiving from PO.
     */
    public function test_po_line_prefills_fabric_color_code_on_receipt_line(): void
    {
        // Master color 204
        $masterColor = FabricColor::create([
            'material_id' => $this->boucleFabric->id,
            'color_code' => '204',
            'color_name_ar' => 'بيج بوكلية 204',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'purchase_order_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplierGulfCorner->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => date('Y-m-d'),
            'status' => 'APPROVED',
            'total_amount' => 850.0,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $poLine = $po->lines()->create([
            'material_id' => $this->boucleFabric->id,
            'fabric_color_id' => $masterColor->id,
            'ordered_quantity' => 10,
            'purchase_unit_id' => $this->meter->id,
            'conversion_factor' => 1.0,
            'ordered_base_quantity' => 10,
            'unit_price' => 85.0,
            'line_total' => 850.0,
            'received_base_quantity' => 0,
        ]);

        $receivingService = app(PurchaseReceivingService::class);
        $receipt = $receivingService->createReceiptFromPurchaseOrder($po, $this->adminUser);

        $this->assertNotNull($receipt);
        $this->assertEquals('DRAFT', $receipt->status);
        $this->assertEquals(1, $receipt->lines()->count());

        $rcptLine = $receipt->lines()->first();
        $this->assertEquals('204', $rcptLine->fabric_color_code);
        $this->assertEquals($masterColor->id, $rcptLine->fabric_color_id);

        // Now post the linked receipt
        $receivingService->postLinkedReceipt($receipt, $this->adminUser);
        $receipt->refresh();
        $this->assertEquals('POSTED', $receipt->status);

        $lot = InventoryLot::where('receipt_line_id', $rcptLine->id)->first();
        $this->assertEquals('204', $lot->fabric_color_code);
    }

    /**
     * Section 26: Production Lot Matching
     * Production requirement requires color 204.
     * Lot A (204) is eligible, Lot B (118) is rejected by fulfillment check.
     */
    public function test_production_lot_matching_by_fabric_color(): void
    {
        $order = CustomerOrder::factory()->create(['status' => 'APPROVED_FOR_PRODUCTION']);
        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        $prodOrder = ProductionOrder::create([
            'production_order_number' => 'PRD-TEST-FABRIC',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 1,
            'produced_quantity' => 0,
            'status' => 'RELEASED',
            'fabric_material_id' => $this->boucleFabric->id,
            'fabric_color_code' => '204',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $pmrService = app(ProductionMaterialRequestService::class);
        $matRequest = $pmrService->createRequest($prodOrder, $this->adminUser, [
            'warehouse_id' => $this->warehouse->id,
            'request_date' => date('Y-m-d'),
            'notes' => 'طلب قماش بوكلية لون 204',
            'lines' => [
                [
                    'material_id' => $this->boucleFabric->id,
                    'requested_quantity' => 5,
                    'base_unit_id' => $this->meter->id,
                    'fabric_color_code' => '204',
                ],
            ],
        ]);

        $matRequest->update(['status' => 'SUBMITTED']);
        $reqLine = $matRequest->lines()->first();
        $this->assertEquals('204', $reqLine->fabric_color_code);

        // Create Lot A with color 204
        $lotA = InventoryLot::create([
            'lot_code' => 'LOT-A-204',
            'material_id' => $this->boucleFabric->id,
            'fabric_color_code' => '204',
            'supplier_id' => $this->supplierGulfCorner->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => date('Y-m-d'),
            'original_quantity' => 10,
            'remaining_quantity' => 10,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 85.0,
            'status' => 'ACTIVE',
        ]);

        // Create Lot B with color 118
        $lotB = InventoryLot::create([
            'lot_code' => 'LOT-B-118',
            'material_id' => $this->boucleFabric->id,
            'fabric_color_code' => '118',
            'supplier_id' => $this->supplierGulfCorner->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => date('Y-m-d'),
            'original_quantity' => 10,
            'remaining_quantity' => 10,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 85.0,
            'status' => 'ACTIVE',
        ]);

        // Attempt fulfill with mismatched Lot B (118) -> must be rejected
        try {
            $pmrService->fulfillRequest($matRequest, $this->adminUser, [
                [
                    'request_line_id' => $reqLine->id,
                    'inventory_lot_id' => $lotB->id,
                    'quantity' => 5,
                ],
            ]);
            $this->fail('Expected exception for mismatched fabric color lot was not thrown.');
        } catch (Exception $e) {
            $this->assertStringContainsString('لون اللوت المحدد لا يطابق لون بند الطلب', $e->getMessage());
        }

        // Fulfill with matching Lot A (204) -> must succeed
        $issue = $pmrService->fulfillRequest($matRequest, $this->adminUser, [
            [
                'request_line_id' => $reqLine->id,
                'inventory_lot_id' => $lotA->id,
                'quantity' => 5,
            ],
        ]);

        $this->assertNotNull($issue);
        $lotA->refresh();
        $this->assertEquals(5, (float) $lotA->remaining_quantity);
    }

    /**
     * Section 27: Edit Draft Receipt
     * Draft Fabric Receipt: Color 204 -> Edit: Color 118 -> Save -> 118 used on post.
     * Once POSTED, normal edit is prohibited.
     */
    public function test_edit_draft_receipt_updates_color_code_and_prevents_edit_after_posted(): void
    {
        // 1. Create draft receipt with Color 204
        $receipt = MaterialReceipt::create([
            'receipt_number' => 'REC-EDIT-001',
            'supplier_id' => $this->supplierGulfCorner->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by_user_id' => $this->adminUser->id,
            'received_by_user_id' => $this->adminUser->id,
            'receipt_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $receipt->lines()->create([
            'material_id' => $this->boucleFabric->id,
            'purchase_unit_id' => $this->meter->id,
            'base_unit_id' => $this->meter->id,
            'quantity_received' => 10,
            'conversion_factor' => 1.0,
            'base_quantity' => 10,
            'unit_cost_purchase' => 85.0,
            'unit_cost_base' => 85.0,
            'total_cost' => 850.0,
            'fabric_color_code' => '204',
        ]);

        // 2. Edit draft receipt via HTTP PUT: update color to 118
        $response = $this->actingAs($this->adminUser)
            ->put(route('inventory.receipts.update', $receipt), [
                'supplier_id' => $this->supplierGulfCorner->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => date('Y-m-d'),
                'action' => 'draft',
                'lines' => [
                    [
                        'material_id' => $this->boucleFabric->id,
                        'fabric_color_code' => '118',
                        'quantity_received' => 10,
                        'purchase_unit_id' => $this->meter->id,
                        'conversion_factor' => 1.0,
                        'unit_cost_purchase' => 85.0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('inventory.receipts.show', $receipt));

        $receipt->refresh();
        $this->assertEquals('DRAFT', $receipt->status);
        $this->assertEquals('118', $receipt->lines()->first()->fabric_color_code);

        // 3. Post receipt -> lot should have color 118
        $postResponse = $this->actingAs($this->adminUser)
            ->from(route('inventory.receipts.show', $receipt))
            ->post(route('inventory.receipts.post', $receipt));

        $postResponse->assertRedirect(route('inventory.receipts.show', $receipt));
        $receipt->refresh();
        $this->assertEquals('POSTED', $receipt->status);

        $lot = InventoryLot::where('receipt_line_id', $receipt->lines()->first()->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals('118', $lot->fabric_color_code);

        // 4. Attempting to edit after POSTED must fail (400)
        $editAttemptResponse = $this->actingAs($this->adminUser)
            ->put(route('inventory.receipts.update', $receipt), [
                'supplier_id' => $this->supplierGulfCorner->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => date('Y-m-d'),
                'lines' => [
                    [
                        'material_id' => $this->boucleFabric->id,
                        'fabric_color_code' => '999',
                        'quantity_received' => 10,
                        'purchase_unit_id' => $this->meter->id,
                        'conversion_factor' => 1.0,
                        'unit_cost_purchase' => 85.0,
                    ],
                ],
            ]);

        $editAttemptResponse->assertStatus(400);
    }
}
