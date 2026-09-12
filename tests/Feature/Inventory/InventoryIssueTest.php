<?php

namespace Tests\Feature\Inventory;

use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIssueTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Warehouse $warehouse;

    protected Department $department;

    protected UnitOfMeasure $meter;

    protected Material $material;

    protected InventoryLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::where('name', 'admin')->first();
        $this->adminUser = User::factory()->create([
            'username' => 'admin_issue_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('code', 'RAW_MATERIALS')->first() ?? Warehouse::first();
        $this->department = Department::where('code', 'CARPENTRY')->first() ?? Department::first();
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

    public function test_user_can_post_material_issue_and_deduct_lot_quantity(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.issues.store'), [
                'warehouse_id' => $this->warehouse->id,
                'department_id' => $this->department->id,
                'issue_date' => date('Y-m-d'),
                'notes' => 'صرف قسم النجارة لهيكل كنبة 3 مقاعد',
                'action' => 'post',
                'items' => [
                    [
                        'lot_id' => $this->lot->id,
                        'quantity' => 30.0,
                        'unit_id' => $this->meter->id,
                    ],
                ],
            ]);

        $issue = MaterialIssue::latest()->first();
        $response->assertRedirect(route('inventory.issues.show', $issue));

        $this->assertEquals('POSTED', $issue->status);

        // Lot remaining quantity updated: 100 - 30 = 70
        $this->lot->refresh();
        $this->assertEquals(70.0, (float) $this->lot->remaining_quantity);

        // Movement recorded
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ISSUE',
            'direction' => 'OUT',
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 30.0,
            'unit_cost' => 150.0,
            'total_cost' => 4500.0,
        ]);
    }

    public function test_system_prevents_negative_inventory_on_issue_posting(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'ISS-TEST-99',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->adminUser->id,
            'issued_by_user_id' => $this->adminUser->id,
            'issue_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $issue->lines()->create([
            'inventory_lot_id' => $this->lot->id,
            'material_id' => $this->material->id,
            'base_unit_id' => $this->meter->id,
            'requested_quantity' => 200.0,
            'issued_quantity' => 200.0,
            'unit_cost' => 150.0,
            'total_cost' => 30000.0,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->from(route('inventory.issues.show', $issue))
            ->post(route('inventory.issues.post', $issue));

        $response->assertRedirect(route('inventory.issues.show', $issue));
        $response->assertSessionHas('error');

        // Lot remains unchanged
        $this->lot->refresh();
        $this->assertEquals(100.0, (float) $this->lot->remaining_quantity);
        $this->assertEquals('DRAFT', $issue->fresh()->status);
    }
}
