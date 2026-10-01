<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\Department;
use App\Models\FabricColor;
use App\Models\FabricMaterialSpec;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductModel;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FabricRedesignRemedyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_legacy_catalog_tables_are_preserved(): void
    {
        $this->assertTrue(Schema::hasTable('supplier_fabric_catalogs'), 'Table supplier_fabric_catalogs must not be dropped.');
        $this->assertTrue(Schema::hasTable('supplier_fabric_catalog_colors'), 'Table supplier_fabric_catalog_colors must not be dropped.');
    }

    public function test_customer_order_overwrites_tampered_supplier_and_color_codes(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $customer = Customer::firstOrFail();
        $channel = SalesChannel::first();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();

        $realSupplier = Supplier::factory()->create(['name' => 'المورد الحقيقي']);
        $fakeSupplier = Supplier::factory()->create(['name' => 'مورد وهمي مزيف']);

        $material = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
            'is_active' => true,
        ]);
        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $realSupplier->id,
            'catalog_number' => 'CAT-LEGIT-100',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
        ]);
        $material->suppliers()->attach($realSupplier->id, ['is_preferred' => true]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => 'COL-REAL-77',
            'supplier_color_code' => 'SUP-REAL-999',
            'color_name_ar' => 'كحلي حقيقي',
            'is_active' => true,
            'is_available' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-SOFA-01',
            'name_ar' => 'كنب مودرن',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'order_date' => now()->format('Y-m-d'),
            'delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'lines' => [
                [
                    'product_model_id' => $model->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'requested_width_cm' => 180,
                    'requested_length_cm' => 200,
                    'fabric_material_id' => $material->id,
                    'fabric_color_id' => $color->id,
                    // Attacker attempts to forge supplier and codes
                    'fabric_supplier_id' => $fakeSupplier->id,
                    'fabric_color_code' => 'COL-FAKE-HACK',
                    'fabric_supplier_color_code' => 'SUP-FAKE-HACK',
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('sales.orders.store'), $payload);
        $response->assertSessionHasNoErrors();

        // Verify in DB that DB-resolved real values were enforced
        $this->assertDatabaseHas('customer_order_lines', [
            'fabric_material_id' => $material->id,
            'fabric_color_id' => $color->id,
            'fabric_supplier_id' => $realSupplier->id,
            'fabric_color_code' => 'COL-REAL-77',
            'fabric_supplier_color_code' => 'SUP-REAL-999',
        ]);

        $this->assertDatabaseMissing('customer_order_lines', [
            'fabric_supplier_id' => $fakeSupplier->id,
        ]);
        $this->assertDatabaseMissing('customer_order_lines', [
            'fabric_color_code' => 'COL-FAKE-HACK',
        ]);
    }

    public function test_customer_order_rejects_incomplete_legacy_fabric_lacking_supplier(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $customer = Customer::firstOrFail();
        $channel = SalesChannel::first();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();

        // Incomplete fabric: has no supplier attached
        $incompleteMaterial = Material::factory()->create([
            'code' => 'MAT-INCOMPLETE-01',
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
            'is_active' => true,
        ]);
        FabricMaterialSpec::create([
            'material_id' => $incompleteMaterial->id,
            'catalog_number' => 'CAT-UNKNOWN',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
        ]);
        $color = FabricColor::create([
            'material_id' => $incompleteMaterial->id,
            'color_code' => '1',
            'supplier_color_code' => '101',
            'color_name_ar' => 'لون',
            'is_active' => true,
            'is_available' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-SOFA-02',
            'name_ar' => 'كنب مودرن 2',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'order_date' => now()->format('Y-m-d'),
            'delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'lines' => [
                [
                    'product_model_id' => $model->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'requested_width_cm' => 180,
                    'requested_length_cm' => 200,
                    'fabric_material_id' => $incompleteMaterial->id,
                    'fabric_color_id' => $color->id,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('sales.orders.store'), $payload);
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('customer_orders', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_quotation_preserves_fabric_supplier_color_code_when_converted_to_order(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $customer = Customer::firstOrFail();
        $channel = SalesChannel::first();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();
        $supplier = Supplier::factory()->create();

        $material = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
            'is_active' => true,
        ]);
        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'catalog_number' => 'CAT-QUOTE-500',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
        ]);
        $material->suppliers()->attach($supplier->id, ['is_preferred' => true]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => 'COL-Q-1',
            'supplier_color_code' => 'SUP-Q-888',
            'color_name_ar' => 'أحمر قاني',
            'is_active' => true,
            'is_available' => true,
        ]);

        $model = ProductModel::create([
            'model_code' => 'MOD-SOFA-03',
            'name_ar' => 'كنب مودرن 3',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $quotationService = app(QuotationService::class);
        $quotation = $quotationService->createQuotation([
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'quotation_date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(14)->format('Y-m-d'),
        ], [
            [
                'product_model_id' => $model->id,
                'quantity' => 2,
                'unit_price' => 1500,
                'requested_width_cm' => 180,
                'requested_length_cm' => 200,
                'fabric_material_id' => $material->id,
                'fabric_color_id' => $color->id,
            ],
        ], $admin);

        // Verify quotation line snapshotted supplier and color codes
        $quoteLine = $quotation->lines->first();
        $this->assertEquals($supplier->id, $quoteLine->fabric_supplier_id);
        $this->assertEquals('COL-Q-1', $quoteLine->fabric_color_code);
        $this->assertEquals('SUP-Q-888', $quoteLine->fabric_supplier_color_code);

        // Approve Quotation
        $quotationService->approveQuotation($quotation, $admin);

        // Convert to Customer Order
        $order = $quotationService->convertToOrder($quotation, $admin);

        $this->assertNotNull($order);
        $orderLine = $order->lines->first();
        $this->assertEquals($supplier->id, $orderLine->fabric_supplier_id);
        $this->assertEquals($material->id, $orderLine->fabric_material_id);
        $this->assertEquals($color->id, $orderLine->fabric_color_id);
        $this->assertEquals('COL-Q-1', $orderLine->fabric_color_code);
        $this->assertEquals('SUP-Q-888', $orderLine->fabric_supplier_color_code);
    }

    public function test_fabric_color_request_rejects_material_id_tampering(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();

        $materialA = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
        ]);
        $materialB = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
        ]);

        $color = FabricColor::create([
            'material_id' => $materialA->id,
            'color_code' => 'COL-ORIGINAL',
            'supplier_color_code' => 'SUP-ORIGINAL',
            'color_name_ar' => 'أصلي',
            'is_active' => true,
        ]);

        // Attempt to update color while changing material_id to materialB
        $response = $this->actingAs($admin)->put(route('fabric-colors.update', $color), [
            'material_id' => $materialB->id,
            'color_code' => 'COL-ORIGINAL',
            'supplier_color_code' => 'SUP-ORIGINAL-UPDATED',
        ]);

        $response->assertSessionHasErrors('material_id');
        $this->assertEquals($materialA->id, $color->fresh()->material_id);
    }

    public function test_fabric_color_request_requires_materials_manage_permission(): void
    {
        $workerRole = Role::where('name', 'production_worker')->first();
        $regularUser = User::factory()->create([
            'role_id' => $workerRole?->id,
            'is_active' => true,
        ]);
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();

        $material = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
        ]);

        $response = $this->actingAs($regularUser)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => 'COL-UNAUTH',
            'supplier_color_code' => 'SUP-UNAUTH',
        ]);

        $response->assertForbidden();
    }

    public function test_inventory_traceability_retains_fabric_color_and_supplier_color_code_through_receipt_issue_return_adjustment(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::firstOrFail();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();

        $material = Material::factory()->create([
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
            'is_active' => true,
        ]);
        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'catalog_number' => 'CAT-TRACE-1',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
        ]);
        $material->suppliers()->attach($supplier->id, ['is_preferred' => true]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '10',
            'supplier_color_code' => 'SUP-2020-1',
            'color_name_ar' => 'أزرق',
            'is_active' => true,
        ]);

        $inventoryService = app(InventoryService::class);

        // 1. Receipt
        $receipt = $inventoryService->createReceipt([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $material->id,
                    'fabric_color_id' => $color->id,
                    'quantity_received' => 100,
                    'purchase_unit_id' => $meterUnit->id,
                    'conversion_factor' => 1,
                    'unit_cost_purchase' => 50,
                ],
            ],
        ], $admin);

        $postedReceipt = $inventoryService->postReceipt($receipt, $admin);

        // Verify Lot and Movement snapshots
        $lot = InventoryLot::where('receipt_line_id', $postedReceipt->lines->first()->id)->firstOrFail();
        $this->assertEquals($color->id, $lot->fabric_color_id);
        $this->assertEquals('10', $lot->fabric_color_code);
        $this->assertEquals('SUP-2020-1', $lot->fabric_supplier_color_code);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'material_id' => $material->id,
            'fabric_color_id' => $color->id,
            'fabric_color_code' => '10',
            'fabric_supplier_color_code' => 'SUP-2020-1',
        ]);

        $department = Department::firstOrFail();

        // 2. Issue
        $issue = $inventoryService->createIssue([
            'warehouse_id' => $warehouse->id,
            'department_id' => $department->id,
            'issue_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $material->id,
                    'inventory_lot_id' => $lot->id,
                    'issued_quantity' => 20,
                    'base_unit_id' => $meterUnit->id,
                ],
            ],
        ], $admin->id);

        $postedIssue = $inventoryService->postIssue($issue, $admin);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ISSUE',
            'inventory_lot_id' => $lot->id,
            'fabric_color_id' => $color->id,
            'fabric_color_code' => '10',
            'fabric_supplier_color_code' => 'SUP-2020-1',
        ]);

        // 3. Return
        $return = $inventoryService->createReturn([
            'warehouse_id' => $warehouse->id,
            'return_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $material->id,
                    'inventory_lot_id' => $lot->id,
                    'returned_quantity' => 5,
                    'base_unit_id' => $meterUnit->id,
                    'unit_cost' => 50,
                    'original_issue_line_id' => $postedIssue->lines->first()->id,
                ],
            ],
        ], $admin->id);

        $postedReturn = $inventoryService->postReturn($return, $admin);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RETURN',
            'inventory_lot_id' => $lot->id,
            'fabric_color_id' => $color->id,
            'fabric_color_code' => '10',
            'fabric_supplier_color_code' => 'SUP-2020-1',
        ]);

        // 4. Positive Adjustment (Opening balance / new lot)
        $reasonIn = InventoryAdjustmentReason::where('code', 'OPENING_STOCK_MIGRATION')->first() ?? InventoryAdjustmentReason::firstOrFail();
        $adjIn = $inventoryService->createAdjustment([
            'warehouse_id' => $warehouse->id,
            'reason_id' => $reasonIn->id,
            'adjustment_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $material->id,
                    'fabric_color_id' => $color->id,
                    'quantity' => 15,
                    'base_unit_id' => $meterUnit->id,
                    'unit_cost' => 50,
                    'adjustment_type' => 'ADJUSTMENT_IN',
                ],
            ],
        ], $admin->id);

        $postedAdjIn = $inventoryService->postAdjustment($adjIn, $admin);

        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => InventoryAdjustment::class,
            'reference_id' => $adjIn->id,
            'direction' => 'IN',
            'fabric_color_code' => '10',
            'fabric_supplier_color_code' => 'SUP-2020-1',
        ]);
    }
}
