<?php

namespace Tests\Feature\Inventory;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialReceipt;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $operatorUser;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected UnitOfMeasure $meter;

    protected UnitOfMeasure $roll;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_receipt_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::first();
        $this->supplier = Supplier::create([
            'name' => 'شركة المورد المتحدة',
            'supplier_code' => 'SUP-TEST-01',
            'is_active' => true,
        ]);

        $this->meter = UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::first();
        $this->roll = UnitOfMeasure::where('code', 'ROLL')->first() ?? UnitOfMeasure::create([
            'name_ar' => 'رول قماش',
            'code' => 'ROLL_TEST',
            'allows_decimal' => false,
            'is_active' => true,
        ]);

        $category = MaterialCategory::first();
        $this->material = Material::create([
            'material_category_id' => $category->id,
            'name_ar' => 'قماش مخمل أزرق',
            'code' => 'MAT-FAB-001',
            'material_type' => 'FABRIC',
            'base_unit_id' => $this->meter->id,
            'purchase_unit_id' => $this->roll->id,
            'is_active' => true,
        ]);

        // Add custom conversion: 1 ROLL = 50 M
        $this->material->conversions()->create([
            'from_unit_id' => $this->roll->id,
            'to_unit_id' => $this->meter->id,
            'conversion_factor' => 50.0,
        ]);
    }

    public function test_user_can_create_material_receipt_draft(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.receipts.store'), [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => date('Y-m-d'),
                'supplier_invoice_number' => 'INV-1001',
                'action' => 'draft',
                'items' => [
                    [
                        'material_id' => $this->material->id,
                        'quantity' => 2,
                        'unit_id' => $this->roll->id,
                        'unit_cost' => 500.0,
                        'lot_number' => 'LOT-CUSTOM-01',
                    ],
                ],
            ]);

        $receipt = MaterialReceipt::latest()->first();

        $response->assertRedirect(route('inventory.receipts.show', $receipt));
        $this->assertEquals('DRAFT', $receipt->status);
        $this->assertEquals(1000.0, (float) $receipt->total_amount);
        $this->assertDatabaseHas('material_receipt_lines', [
            'material_receipt_id' => $receipt->id,
            'material_id' => $this->material->id,
            'quantity_received' => 2,
            'conversion_factor' => 50.0,
            'base_quantity' => 100, // 2 * 50
        ]);
        // Lots are not created until posted
        $this->assertDatabaseMissing('inventory_lots', [
            'lot_code' => 'LOT-CUSTOM-01',
        ]);
    }

    public function test_user_can_post_receipt_and_generate_inventory_lots_and_movements(): void
    {
        $receipt = MaterialReceipt::create([
            'receipt_number' => 'REC-TEST-001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by_user_id' => $this->adminUser->id,
            'received_by_user_id' => $this->adminUser->id,
            'receipt_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $receipt->lines()->create([
            'material_id' => $this->material->id,
            'purchase_unit_id' => $this->roll->id,
            'base_unit_id' => $this->material->base_unit_id,
            'quantity_received' => 2,
            'conversion_factor' => 50.0,
            'base_quantity' => 100,
            'unit_cost_purchase' => 500.0,
            'unit_cost_base' => 10.0,
            'total_cost' => 1000.0,
            'lot_reference' => 'LOT-2026-0001',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->from(route('inventory.receipts.show', $receipt))
            ->post(route('inventory.receipts.post', $receipt));

        $response->assertRedirect(route('inventory.receipts.show', $receipt));

        $receipt->refresh();
        $this->assertEquals('POSTED', $receipt->status);
        $this->assertEquals($this->adminUser->id, $receipt->received_by_user_id);

        // Verify Inventory Lot created with historical cost per base unit
        // total cost 1000 / 100 base units = 10.0 per base unit
        $this->assertDatabaseHas('inventory_lots', [
            'supplier_lot_reference' => 'LOT-2026-0001',
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'original_quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost' => 10.0,
        ]);

        // Verify Inventory Movement Ledger
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'direction' => 'IN',
            'material_id' => $this->material->id,
            'quantity' => 100,
            'unit_cost' => 10.0,
            'total_cost' => 1000.0,
        ]);
    }
}
