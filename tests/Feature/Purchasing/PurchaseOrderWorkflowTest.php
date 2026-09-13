<?php

namespace Tests\Feature\Purchasing;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected Material $material;

    protected UnitOfMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->supplier = Supplier::create(['supplier_code' => 'SUP-PO-01', 'name' => 'مورد الخشب التنافسي', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'RAW_MATERIALS', 'name_ar' => 'مستودع الخامات', 'is_active' => true]);
        $category = MaterialCategory::create(['code' => 'WOOD', 'name_ar' => 'أخشاب', 'is_active' => true]);
        $this->unit = UnitOfMeasure::create(['code' => 'BOARD', 'name_ar' => 'لوح', 'is_active' => true]);

        $this->material = Material::create([
            'material_category_id' => $category->id,
            'code' => 'MAT-WOOD-BOARD-100',
            'name_ar' => 'ألواح ابلكاش 18 ملم',
            'base_unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    public function test_purchase_order_creation_approval_and_pr_allocation_protection(): void
    {
        $prService = app(PurchaseRequestService::class);
        $poService = app(PurchaseOrderService::class);

        // 1. Create Approved PR for 100 boards
        $pr = $prService->createRequest([
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['material_id' => $this->material->id, 'requested_quantity' => 100],
            ],
        ], $this->manager);
        $prService->submitRequest($pr, $this->manager);
        $prService->reviewRequest($pr, $this->manager, 'APPROVE');
        $prLine = $pr->lines->first();

        // 2. Create PO A for 60 boards
        $poA = $poService->createOrder([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_request_id' => $pr->id,
            'order_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $prLine->id,
                    'material_id' => $this->material->id,
                    'ordered_quantity' => 60,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 30,
                ],
            ],
        ], $this->manager);

        $poService->approveOrder($poA, $this->manager);
        $this->assertEquals('APPROVED', $poA->status);
        $pr->refresh();
        $this->assertEquals('PARTIALLY_ORDERED', $pr->status);

        // 3. Create PO B for remaining 40 boards
        $poB = $poService->createOrder([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_request_id' => $pr->id,
            'order_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $prLine->id,
                    'material_id' => $this->material->id,
                    'ordered_quantity' => 40,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 30,
                ],
            ],
        ], $this->manager);

        $pr->refresh();
        $this->assertEquals('ORDERED', $pr->status);

        // 4. Attempt extra PO C for 10 boards (Must fail allocation protection)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('تتجاوز الكمية المتاحة بطلب الشراء المعتمد');

        $poService->createOrder([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_request_id' => $pr->id,
            'order_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'purchase_request_line_id' => $prLine->id,
                    'material_id' => $this->material->id,
                    'ordered_quantity' => 10,
                    'purchase_unit_id' => $this->unit->id,
                    'conversion_factor' => 1,
                    'unit_price' => 30,
                ],
            ],
        ], $this->manager);
    }
}
