<?php

namespace Tests\Feature\Purchasing;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseRequestService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Warehouse $warehouse;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->warehouse = Warehouse::create([
            'code' => 'RAW_MATERIALS',
            'name_ar' => 'مستودع الخامات',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create(['code' => 'WOOD', 'name_ar' => 'أخشاب', 'is_active' => true]);
        $unit = UnitOfMeasure::create(['code' => 'BOARD', 'name_ar' => 'لوح', 'is_active' => true]);
        $this->material = Material::create([
            'material_category_id' => $category->id,
            'code' => 'MAT-WOOD-001',
            'name_ar' => 'خشب زان ألماني',
            'base_unit_id' => $unit->id,
            'min_stock_level' => 20,
            'reorder_point' => 50,
            'is_active' => true,
        ]);
    }

    public function test_can_create_and_submit_and_approve_purchase_request(): void
    {
        $service = app(PurchaseRequestService::class);

        // 1. Create PR
        $pr = $service->createRequest([
            'warehouse_id' => $this->warehouse->id,
            'priority' => 'URGENT',
            'justification' => 'تغطية نقص خامات المزارع',
            'lines' => [
                [
                    'material_id' => $this->material->id,
                    'requested_quantity' => 100,
                ],
            ],
        ], $this->manager);

        $this->assertNotNull($pr);
        $this->assertEquals('DRAFT', $pr->status);
        $this->assertStringStartsWith('PRQ-', $pr->request_number);

        // 2. Submit PR
        $service->submitRequest($pr, $this->manager);
        $this->assertEquals('SUBMITTED', $pr->status);

        // 3. Approve PR
        $service->reviewRequest($pr, $this->manager, 'APPROVE');
        $this->assertEquals('APPROVED', $pr->status);
        $this->assertEquals($this->manager->id, $pr->approved_by_user_id);
    }

    public function test_can_create_pr_from_low_stock_planning(): void
    {
        $service = app(PurchaseRequestService::class);

        $pr = $service->createFromLowStock([
            [
                'material_id' => $this->material->id,
                'requested_quantity' => 80,
            ],
        ], $this->manager, $this->warehouse->id);

        $this->assertEquals('LOW_STOCK', $pr->source_type);
        $this->assertEquals(80, $pr->lines->first()->requested_quantity);
    }
}
