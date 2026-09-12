<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Warehouse $warehouse;

    protected InventoryAdjustmentReason $reason;

    protected UnitOfMeasure $meter;

    protected Material $material;

    protected InventoryLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_adj_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $regularRole = Role::create([
            'name' => 'operator',
            'display_name' => 'مشغل عادي',
        ]);
        $this->regularUser = User::factory()->create([
            'username' => 'operator_adj_test',
            'role_id' => $regularRole->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::first();
        $this->reason = InventoryAdjustmentReason::where('code', 'DAMAGED_IN_WAREHOUSE')->first() ?? InventoryAdjustmentReason::first();
        $this->meter = UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::first();

        $category = MaterialCategory::first();
        $this->material = Material::create([
            'material_category_id' => $category->id,
            'name_ar' => 'خشب زان أبيض 2.5 سم',
            'code' => 'MAT-WD-001',
            'material_type' => 'WOOD',
            'base_unit_id' => $this->meter->id,
            'is_active' => true,
        ]);

        $this->lot = InventoryLot::create([
            'lot_code' => 'LOT-WD-2026-001',
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-09-10',
            'original_quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 150.0,
        ]);
    }

    public function test_non_admin_user_cannot_access_or_post_inventory_adjustments(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('inventory.adjustments.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_post_inventory_adjustment_decrease(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.adjustments.store'), [
                'warehouse_id' => $this->warehouse->id,
                'reason_id' => $this->reason->id,
                'adjustment_date' => date('Y-m-d'),
                'notes' => 'تسوية تلف نتيجة رطوبة بالمخزن',
                'action' => 'post',
                'items' => [
                    [
                        'adjustment_type' => 'DECREASE',
                        'lot_id' => $this->lot->id,
                        'quantity' => 5.0,
                        'notes' => 'تلف 5 أمتار',
                    ],
                ],
            ]);

        $adjustment = InventoryAdjustment::latest()->first();
        $response->assertRedirect(route('inventory.adjustments.show', $adjustment));

        $this->assertEquals('POSTED', $adjustment->status);

        $this->lot->refresh();
        $this->assertEquals(95.0, (float) $this->lot->remaining_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ADJUSTMENT_OUT',
            'direction' => 'OUT',
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 5.0,
            'unit_cost' => 150.0,
            'total_cost' => 750.0,
        ]);
    }

    public function test_admin_can_post_opening_balance_adjustment_creating_lot_and_opening_balance_movement(): void
    {
        $openingReason = InventoryAdjustmentReason::where('code', 'OPENING_STOCK_MIGRATION')->first();

        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.adjustments.store'), [
                'warehouse_id' => $this->warehouse->id,
                'reason_id' => $openingReason->id,
                'adjustment_date' => date('Y-m-d'),
                'notes' => 'قيد رصيد افتتاحي للمادة الخشبية',
                'action' => 'post',
                'lines' => [
                    [
                        'adjustment_type' => 'ADJUSTMENT_IN',
                        'material_id' => $this->material->id,
                        'quantity' => 250.0,
                        'unit_cost' => 145.50,
                        'notes' => 'رصيد مخزون افتتاحي مع حجز التكلفة التاريخية',
                    ],
                ],
            ]);

        $adjustment = InventoryAdjustment::latest()->first();
        $response->assertRedirect(route('inventory.adjustments.show', $adjustment));

        $this->assertEquals('POSTED', $adjustment->status);

        // Verify Inventory Lot created automatically for opening balance
        $createdLot = InventoryLot::where('material_id', $this->material->id)
            ->where('original_quantity', 250.0)
            ->first();

        $this->assertNotNull($createdLot);
        $this->assertEquals(250.0, (float) $createdLot->remaining_quantity);
        $this->assertEquals(145.50, (float) $createdLot->unit_cost);

        // Verify IN Inventory Movement with movement_type = OPENING_BALANCE
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'OPENING_BALANCE',
            'direction' => 'IN',
            'inventory_lot_id' => $createdLot->id,
            'material_id' => $this->material->id,
            'quantity' => 250.0,
            'unit_cost' => 145.50,
            'total_cost' => 36375.0,
        ]);
    }
}
