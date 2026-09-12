<?php

namespace Tests\Feature\MasterData;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_material_categories_and_seeds_exist(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('material-categories.index'));

        $response->assertStatus(200);
        $response->assertViewIs('material-categories.index');
        $response->assertSee('WOOD');
        $response->assertSee('FOAM');
        $response->assertSee('FABRIC');
        $response->assertSee('ACCESSORY');
    }

    public function test_admin_can_create_material_category(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('material-categories.store'), [
            'code' => 'MARBLE',
            'name_ar' => 'رخام وجرانيت',
            'name_en' => 'Marble & Granite',
            'description' => 'أسطح وطاولات الرخام',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('material-categories.index'));
        $this->assertDatabaseHas('material_categories', [
            'code' => 'MARBLE',
            'name_ar' => 'رخام وجرانيت',
        ]);
    }

    public function test_admin_can_create_material_with_both_base_and_purchase_units(): void
    {
        $admin = User::where('username', 'admin')->first();
        $accCategory = MaterialCategory::where('code', 'ACCESSORY')->first();
        $pieceUnit = UnitOfMeasure::where('code', 'PIECE')->first();
        $cartonUnit = UnitOfMeasure::where('code', 'CARTON')->first();

        $response = $this->actingAs($admin)->post(route('materials.store'), [
            'code' => 'MAT-SCRW-001',
            'name_ar' => 'براغي نجارة 5 سم',
            'name_en' => 'Wood Screws 5cm',
            'material_category_id' => $accCategory->id,
            'base_unit_id' => $pieceUnit->id,
            'purchase_unit_id' => $cartonUnit->id,
            'min_stock_level' => 500,
            'reorder_point' => 1000,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('materials.index'));
        $this->assertDatabaseHas('materials', [
            'code' => 'MAT-SCRW-001',
            'base_unit_id' => $pieceUnit->id,
            'purchase_unit_id' => $cartonUnit->id,
        ]);
    }

    public function test_admin_can_create_wood_material_with_specs(): void
    {
        $admin = User::where('username', 'admin')->first();
        $woodCategory = MaterialCategory::where('code', 'WOOD')->first();
        $boardUnit = UnitOfMeasure::where('code', 'BOARD')->first();

        $response = $this->actingAs($admin)->post(route('materials.store'), [
            'code' => 'MAT-WOOD-001',
            'name_ar' => 'خشب زان روماني 25 مم',
            'name_en' => 'Romanian Beech Wood 25mm',
            'material_category_id' => $woodCategory->id,
            'base_unit_id' => $boardUnit->id,
            'min_stock_level' => 10,
            'reorder_point' => 20,
            'is_active' => 1,
            'wood_type' => 'زان',
            'thickness_mm' => 25,
            'width_cm' => 122,
            'length_cm' => 244,
            'grade' => 'فرز أول A',
        ]);

        $response->assertRedirect(route('materials.index'));
        $this->assertDatabaseHas('materials', [
            'code' => 'MAT-WOOD-001',
            'name_ar' => 'خشب زان روماني 25 مم',
        ]);

        $material = Material::where('code', 'MAT-WOOD-001')->first();
        $this->assertNotNull($material->woodSpec);
        $this->assertEquals('زان', $material->woodSpec->wood_type);
        $this->assertEquals(25, $material->woodSpec->thickness_mm);
    }

    public function test_admin_can_create_foam_material_with_numeric_dimensions(): void
    {
        $admin = User::where('username', 'admin')->first();
        $foamCategory = MaterialCategory::where('code', 'FOAM')->first();
        $pieceUnit = UnitOfMeasure::where('code', 'PIECE')->first();

        $response = $this->actingAs($admin)->post(route('materials.store'), [
            'code' => 'MAT-FOAM-001',
            'name_ar' => 'إسفنج ضغط 30 مميز 10 سم',
            'name_en' => 'Foam Density 30 Premium 10cm',
            'material_category_id' => $foamCategory->id,
            'base_unit_id' => $pieceUnit->id,
            'min_stock_level' => 15,
            'reorder_point' => 30,
            'is_active' => 1,
            'foam_type' => 'ضغط 30 مميز',
            'density_kg_m3' => 30.5,
            'hardness_rating' => 'قاسي',
            'thickness_mm' => 100,
            'width_cm' => 120,
            'length_cm' => 200,
            'block_dimensions' => '200x120 بلوك قياسي',
        ]);

        $response->assertRedirect(route('materials.index'));
        $material = Material::where('code', 'MAT-FOAM-001')->first();
        $this->assertNotNull($material->foamSpec);
        $this->assertEquals('ضغط 30 مميز', $material->foamSpec->foam_type);
        $this->assertEquals(30.5, $material->foamSpec->density_kg_m3);
        $this->assertEquals(120, $material->foamSpec->width_cm);
        $this->assertEquals(200, $material->foamSpec->length_cm);
    }

    public function test_admin_can_create_fabric_material_and_add_colors(): void
    {
        $admin = User::where('username', 'admin')->first();
        $fabricCategory = MaterialCategory::where('code', 'FABRIC')->first();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();

        $response = $this->actingAs($admin)->post(route('materials.store'), [
            'code' => 'MAT-FAB-001',
            'name_ar' => 'قماش مخمل فاخر 140 سم',
            'name_en' => 'Luxury Velvet Fabric 140cm',
            'material_category_id' => $fabricCategory->id,
            'base_unit_id' => $meterUnit->id,
            'min_stock_level' => 50,
            'reorder_point' => 100,
            'is_active' => 1,
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
            'pattern_type' => 'سادة',
            'weight_gsm' => 380,
            'composition' => '100% بوليستر',
        ]);

        $response->assertRedirect(route('materials.index'));
        $material = Material::where('code', 'MAT-FAB-001')->first();
        $this->assertNotNull($material->fabricSpec);

        // Add Color
        $colorResponse = $this->actingAs($admin)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => 'COL-BLUE-01',
            'color_name_ar' => 'كحلي كلاسيك',
            'color_name_en' => 'Classic Navy',
            'hex_code' => '#000080',
            'is_active' => 1,
        ]);

        $colorResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fabric_colors', [
            'material_id' => $material->id,
            'color_code' => 'COL-BLUE-01',
            'color_name_ar' => 'كحلي كلاسيك',
        ]);
    }

    public function test_admin_can_link_supplier_to_material_without_storing_price(): void
    {
        $admin = User::where('username', 'admin')->first();
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->post(route('material-suppliers.store'), [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'supplier_item_code' => 'SUP-MAT-99',
            'lead_time_days' => 5,
            'minimum_order_qty' => 10,
            'is_preferred' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('material_supplier', [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'supplier_item_code' => 'SUP-MAT-99',
            'lead_time_days' => 5,
            'is_preferred' => 1,
        ]);
    }

    public function test_admin_can_add_material_unit_conversion(): void
    {
        $admin = User::where('username', 'admin')->first();
        $meterUnit = UnitOfMeasure::where('code', 'METER')->first();
        $rollUnit = UnitOfMeasure::where('code', 'ROLL')->first();
        $material = Material::factory()->create(['base_unit_id' => $meterUnit->id]);

        $response = $this->actingAs($admin)->post(route('material-conversions.store'), [
            'material_id' => $material->id,
            'from_unit_id' => $rollUnit->id,
            'to_unit_id' => $meterUnit->id,
            'conversion_factor' => 50,
            'notes' => 'الرول الواحد يحتوي على 50 متر طولي',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('material_unit_conversions', [
            'material_id' => $material->id,
            'from_unit_id' => $rollUnit->id,
            'to_unit_id' => $meterUnit->id,
            'conversion_factor' => 50,
        ]);
    }

    public function test_material_code_generation_is_sequential_and_unique(): void
    {
        $code1 = Material::generateNextCode();
        $mat1 = Material::factory()->create(['code' => $code1]);

        $code2 = Material::generateNextCode();
        $this->assertNotEquals($code1, $code2);
    }
}
