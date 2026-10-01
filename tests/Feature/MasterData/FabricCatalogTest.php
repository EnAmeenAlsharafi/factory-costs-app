<?php

namespace Tests\Feature\MasterData;

use App\Models\FabricColor;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use App\Models\SupplierFabricCatalog;
use App\Models\SupplierFabricCatalogColor;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FabricCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_admin_creates_supplier_catalog_with_its_reference_image(): void
    {
        Storage::fake('public');
        $admin = User::where('username', 'admin')->firstOrFail();
        $material = $this->createFabricMaterial('MAT-CHENILLE-001');
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->post(route('supplier-fabric-catalogs.store'), [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'catalog_number' => 'CH-2026',
            'catalog_name' => 'شانيل 2026',
            'supplier_material_code' => 'SUP-CH-88',
            'catalog_image' => UploadedFile::fake()->createWithContent(
                'chenille-catalog.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_fabric_catalogs', [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'catalog_number' => 'CH-2026',
            'supplier_material_code' => 'SUP-CH-88',
        ]);
        $this->assertDatabaseHas('material_supplier', [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
        ]);

        $catalog = SupplierFabricCatalog::where('catalog_number', 'CH-2026')->firstOrFail();
        Storage::disk('public')->assertExists($catalog->image_path);
    }

    public function test_admin_maps_internal_color_to_supplier_catalog_color_code(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $material = $this->createFabricMaterial('MAT-CHENILLE-002');
        $internalColor = FabricColor::factory()->create([
            'material_id' => $material->id,
            'color_code' => '7',
            'color_name_ar' => 'بيج',
        ]);
        $catalog = SupplierFabricCatalog::factory()->create([
            'material_id' => $material->id,
            'catalog_number' => 'CAT-771',
        ]);

        $response = $this->actingAs($admin)->post(route('supplier-fabric-catalog-colors.store'), [
            'supplier_fabric_catalog_id' => $catalog->id,
            'fabric_color_id' => $internalColor->id,
            'supplier_color_code' => 'B-214',
            'supplier_color_name' => 'Sand Beige',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_fabric_catalog_colors', [
            'supplier_fabric_catalog_id' => $catalog->id,
            'fabric_color_id' => $internalColor->id,
            'supplier_color_code' => 'B-214',
        ]);
    }

    public function test_catalog_rejects_internal_color_from_another_fabric(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $catalogMaterial = $this->createFabricMaterial('MAT-CHENILLE-003');
        $otherMaterial = $this->createFabricMaterial('MAT-VELVET-001');
        $otherColor = FabricColor::factory()->create([
            'material_id' => $otherMaterial->id,
            'color_code' => '11',
        ]);
        $catalog = SupplierFabricCatalog::factory()->create([
            'material_id' => $catalogMaterial->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('materials.show', $catalogMaterial))
            ->post(route('supplier-fabric-catalog-colors.store'), [
                'supplier_fabric_catalog_id' => $catalog->id,
                'fabric_color_id' => $otherColor->id,
                'supplier_color_code' => 'SUP-11',
            ]);

        $response->assertRedirect(route('materials.show', $catalogMaterial));
        $response->assertSessionHasErrors([
            'fabric_color_id' => 'اللون الداخلي المختار لا يتبع القماش المرتبط بهذا الكتالوج.',
        ]);
        $this->assertDatabaseMissing('supplier_fabric_catalog_colors', [
            'supplier_fabric_catalog_id' => $catalog->id,
            'supplier_color_code' => 'SUP-11',
        ]);
    }

    public function test_internal_color_number_is_unique_within_each_fabric(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $material = $this->createFabricMaterial('MAT-CHENILLE-004');
        FabricColor::factory()->create([
            'material_id' => $material->id,
            'color_code' => '3',
        ]);

        $response = $this->actingAs($admin)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => '3',
            'supplier_color_code' => 'SUP-3-NEW',
        ]);

        $response->assertSessionHasErrors('color_code');
        $this->assertDatabaseCount('fabric_colors', 1);
    }

    public function test_internal_color_resolves_the_active_supplier_catalog_code(): void
    {
        $material = $this->createFabricMaterial('MAT-CHENILLE-005');
        $supplier = Supplier::factory()->create();
        $internalColor = FabricColor::factory()->create([
            'material_id' => $material->id,
            'color_code' => '9',
        ]);
        $catalog = SupplierFabricCatalog::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'catalog_number' => 'CH-900',
        ]);
        SupplierFabricCatalogColor::create([
            'supplier_fabric_catalog_id' => $catalog->id,
            'fabric_color_id' => $internalColor->id,
            'supplier_color_code' => 'SUP-405',
            'is_available' => true,
        ]);

        $resolvedColor = $internalColor->supplierCatalogColorFor($supplier->id);

        $this->assertSame('SUP-405', $resolvedColor?->supplier_color_code);
        $this->assertSame('CH-900', $resolvedColor?->catalog->catalog_number);
    }

    private function createFabricMaterial(string $code): Material
    {
        return Material::factory()->create([
            'code' => $code,
            'material_category_id' => MaterialCategory::where('code', 'FABRIC')->value('id'),
            'base_unit_id' => UnitOfMeasure::where('code', 'METER')->value('id'),
        ]);
    }
}
