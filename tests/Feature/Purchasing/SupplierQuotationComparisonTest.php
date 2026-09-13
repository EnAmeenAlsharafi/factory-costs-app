<?php

namespace Tests\Feature\Purchasing;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\SupplierQuotationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierQuotationComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Material $material;

    protected UnitOfMeasure $baseUnit;

    protected UnitOfMeasure $cartonUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $managerRole = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->supplierA = Supplier::create(['supplier_code' => 'SUP-A', 'name' => 'المورد أ', 'is_active' => true]);
        $this->supplierB = Supplier::create(['supplier_code' => 'SUP-B', 'name' => 'المورد ب', 'is_active' => true]);

        $category = MaterialCategory::create(['code' => 'ACC', 'name_ar' => 'إكسسوارات', 'is_active' => true]);
        $this->baseUnit = UnitOfMeasure::create(['code' => 'PIECE', 'name_ar' => 'قطعة', 'is_active' => true]);
        $this->cartonUnit = UnitOfMeasure::create(['code' => 'CARTON', 'name_ar' => 'كرتونة', 'is_active' => true]);

        $this->material = Material::create([
            'material_category_id' => $category->id,
            'code' => 'MAT-SCREW-01',
            'name_ar' => 'مسامير تجميع 5 سم',
            'base_unit_id' => $this->baseUnit->id,
            'is_active' => true,
        ]);
    }

    public function test_supplier_quotation_normalized_unit_price_comparison(): void
    {
        $service = app(SupplierQuotationService::class);

        // Supplier A: 1 Carton = 1000 Pieces for 100 SAR -> Base unit price = 0.10 SAR/Piece
        $quoteA = $service->recordQuotation([
            'supplier_id' => $this->supplierA->id,
            'quotation_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $this->material->id,
                    'quoted_quantity' => 1,
                    'purchase_unit_id' => $this->cartonUnit->id,
                    'conversion_factor' => 1000,
                    'unit_price' => 100,
                    'lead_time_days' => 4,
                ],
            ],
        ], $this->manager);

        // Supplier B: 1 Carton = 500 Pieces for 55 SAR -> Base unit price = 0.11 SAR/Piece
        $quoteB = $service->recordQuotation([
            'supplier_id' => $this->supplierB->id,
            'quotation_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'material_id' => $this->material->id,
                    'quoted_quantity' => 1,
                    'purchase_unit_id' => $this->cartonUnit->id,
                    'conversion_factor' => 500,
                    'unit_price' => 55,
                    'lead_time_days' => 10,
                ],
            ],
        ], $this->manager);

        $lineA = $quoteA->lines->first();
        $lineB = $quoteB->lines->first();

        $this->assertEquals(0.10, $lineA->base_unit_equivalent_price);
        $this->assertEquals(0.11, $lineB->base_unit_equivalent_price);

        // Supplier A is cheaper per base piece
        $this->assertTrue($lineA->base_unit_equivalent_price < $lineB->base_unit_equivalent_price);
    }
}
