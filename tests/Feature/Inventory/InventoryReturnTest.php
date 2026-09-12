<?php

namespace Tests\Feature\Inventory;

use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\MaterialReturn;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReturnTest extends TestCase
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
            'username' => 'admin_return_test',
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
            'remaining_quantity' => 70.0, // 30 were issued earlier
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 150.0,
        ]);
    }

    public function test_user_can_post_material_return_and_increase_lot_quantity(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.returns.store'), [
                'warehouse_id' => $this->warehouse->id,
                'department_id' => $this->department->id,
                'return_date' => date('Y-m-d'),
                'notes' => 'إرجاع الفائض عن حاجة قسم النجارة',
                'action' => 'post',
                'items' => [
                    [
                        'lot_id' => $this->lot->id,
                        'quantity' => 10.0,
                        'unit_id' => $this->meter->id,
                    ],
                ],
            ]);

        $return = MaterialReturn::latest()->first();
        $response->assertRedirect(route('inventory.returns.show', $return));

        $this->assertEquals('POSTED', $return->status);
        $this->assertCount(1, $return->lines);

        // Lot remaining quantity updated: 70 + 10 = 80
        $this->lot->refresh();
        $this->assertEquals(80.0, (float) $this->lot->remaining_quantity);

        // Movement recorded
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RETURN',
            'direction' => 'IN',
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 10.0,
            'unit_cost' => 150.0,
            'total_cost' => 1500.0,
        ]);
    }

    public function test_user_can_create_and_post_multi_line_material_return_document(): void
    {
        $material2 = Material::create([
            'material_category_id' => MaterialCategory::first()->id,
            'name_ar' => 'خشب سويدي 5 سم',
            'code' => 'MAT-WD-002',
            'material_type' => 'WOOD',
            'base_unit_id' => $this->meter->id,
            'is_active' => true,
        ]);

        $lot2 = InventoryLot::create([
            'lot_code' => 'LOT-WD-2026-002',
            'material_id' => $material2->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-09-10',
            'original_quantity' => 50.0,
            'remaining_quantity' => 40.0,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 200.0,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('inventory.returns.store'), [
                'warehouse_id' => $this->warehouse->id,
                'department_id' => $this->department->id,
                'return_date' => date('Y-m-d'),
                'notes' => 'مرتجع متعدد المواد من قسم النجارة',
                'action' => 'post',
                'lines' => [
                    [
                        'inventory_lot_id' => $this->lot->id,
                        'material_id' => $this->material->id,
                        'returned_quantity' => 5.0,
                    ],
                    [
                        'inventory_lot_id' => $lot2->id,
                        'material_id' => $material2->id,
                        'returned_quantity' => 8.0,
                    ],
                ],
            ]);

        $return = MaterialReturn::latest()->first();
        $response->assertRedirect(route('inventory.returns.show', $return));

        $this->assertEquals('POSTED', $return->status);
        $this->assertCount(2, $return->lines);

        $this->lot->refresh();
        $lot2->refresh();
        $this->assertEquals(75.0, (float) $this->lot->remaining_quantity);
        $this->assertEquals(48.0, (float) $lot2->remaining_quantity);
    }

    public function test_return_to_original_lot_and_cumulative_return_limit_validation(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'ISS-TEST-999',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->adminUser->id,
            'issued_by_user_id' => $this->adminUser->id,
            'issue_date' => date('Y-m-d'),
            'status' => 'POSTED',
        ]);

        $issueLine = $issue->lines()->create([
            'inventory_lot_id' => $this->lot->id,
            'material_id' => $this->material->id,
            'base_unit_id' => $this->meter->id,
            'requested_quantity' => 20.0,
            'issued_quantity' => 20.0,
            'unit_cost' => 150.0,
            'total_cost' => 3000.0,
        ]);

        // First return of 15 out of 20
        $return1 = MaterialReturn::create([
            'return_number' => 'RET-TEST-001',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->adminUser->id,
            'return_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $return1->lines()->create([
            'material_id' => $this->material->id,
            'inventory_lot_id' => $this->lot->id,
            'original_issue_line_id' => $issueLine->id,
            'returned_quantity' => 15.0,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 150.0,
            'total_cost' => 2250.0,
        ]);

        app(InventoryService::class)->postReturn($return1, $this->adminUser);

        // Second return attempt of 10 (15 + 10 = 25 > 20 issued) should throw exception
        $return2 = MaterialReturn::create([
            'return_number' => 'RET-TEST-002',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->adminUser->id,
            'return_date' => date('Y-m-d'),
            'status' => 'DRAFT',
        ]);

        $return2->lines()->create([
            'material_id' => $this->material->id,
            'inventory_lot_id' => $this->lot->id,
            'original_issue_line_id' => $issueLine->id,
            'returned_quantity' => 10.0,
            'base_unit_id' => $this->meter->id,
            'unit_cost' => 150.0,
            'total_cost' => 1500.0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('كمية المرتجع الإجمالية تتجاوز المسموح به');

        app(InventoryService::class)->postReturn($return2, $this->adminUser);
    }
}
