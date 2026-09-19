<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ProductModel $milanModel;

    protected ProductModel $avalonModel;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Material $velvetFabric;

    protected Material $satinFabric;

    protected Material $woodMaterial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $this->milanModel = ProductModel::create([
            'model_code' => 'MOD-MILAN',
            'name_ar' => 'سرير ميلانو',
            'name_en' => 'Milan Bed',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $this->avalonModel = ProductModel::create([
            'model_code' => 'MOD-AVALON',
            'name_ar' => 'سرير أفالون',
            'name_en' => 'Avalon Bed',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        ProductConfiguration::create([
            'configuration_code' => 'CFG-160X200-MILAN',
            'product_model_id' => $this->milanModel->id,
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'has_storage' => false,
            'is_active' => true,
        ]);

        ProductConfiguration::create([
            'configuration_code' => 'CFG-180X200-MILAN',
            'product_model_id' => $this->milanModel->id,
            'width_cm' => 180.0,
            'length_cm' => 200.0,
            'has_storage' => true,
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'supplier_code' => 'SUP-001',
            'name' => 'شركة الراجحي للأقمشة',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'supplier_code' => 'SUP-002',
            'name' => 'مؤسسة المنسوجات الشرقية',
            'is_active' => true,
        ]);

        $fabricCat = MaterialCategory::firstOrCreate(
            ['code' => 'FABRIC'],
            ['name_ar' => 'أقمشة', 'is_active' => true]
        );

        $unit = UnitOfMeasure::firstOrCreate(
            ['code' => 'METER'],
            ['name_ar' => 'متر', 'unit_type' => 'LENGTH', 'is_active' => true]
        );

        $this->velvetFabric = Material::create([
            'code' => 'MAT-VELVET',
            'material_category_id' => $fabricCat->id,
            'name_ar' => 'مخمل تركي فاخر',
            'base_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->satinFabric = Material::create([
            'code' => 'MAT-SATIN',
            'material_category_id' => $fabricCat->id,
            'name_ar' => 'ساتان إيطالي',
            'base_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        // Supplier A supplies Velvet
        DB::table('material_supplier')->insert([
            'material_id' => $this->velvetFabric->id,
            'supplier_id' => $this->supplierA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Supplier B supplies Satin
        DB::table('material_supplier')->insert([
            'material_id' => $this->satinFabric->id,
            'supplier_id' => $this->supplierB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_product_model_search_endpoint()
    {
        $response = $this->actingAs($this->user)->getJson(route('api.search.product-models', ['q' => 'ميل']));
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->milanModel->id, 'code' => 'MOD-MILAN']);
    }

    public function test_supplier_search_endpoint()
    {
        $response = $this->actingAs($this->user)->getJson(route('api.search.suppliers', ['q' => 'الراج']));
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->supplierA->id, 'code' => 'SUP-001']);
    }

    public function test_fabric_material_search_endpoint_respects_supplier_filter()
    {
        // When Supplier A is selected, only Velvet should be returned
        $responseA = $this->actingAs($this->user)->getJson(route('api.search.fabric-materials', [
            'supplier_id' => $this->supplierA->id,
            'q' => 'مخم',
        ]));
        $responseA->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->velvetFabric->id]);

        // When Supplier A is selected, searching for Satin (supplied by B) should return 0 results
        $responseB = $this->actingAs($this->user)->getJson(route('api.search.fabric-materials', [
            'supplier_id' => $this->supplierA->id,
            'q' => 'ساتان',
        ]));
        $responseB->assertOk()
            ->assertJsonCount(0);
    }

    public function test_product_configuration_search_endpoint()
    {
        $response = $this->actingAs($this->user)->getJson(route('api.search.product-configurations', [
            'model_id' => $this->milanModel->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(2);
    }

    public function test_product_model_search_payload_does_not_duplicate_code_in_label()
    {
        $response = $this->actingAs($this->user)->getJson(route('api.search.product-models', ['q' => 'ميلانو']));
        $response->assertOk();

        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertEquals('سرير ميلانو', $data[0]['label']);
        $this->assertEquals('MOD-MILAN', $data[0]['code']);
        $this->assertStringNotContainsString('MOD-MILAN', $data[0]['label']);
    }

    public function test_fabric_material_search_header_when_supplier_has_no_fabrics()
    {
        $supplierWithoutFabrics = Supplier::create([
            'supplier_code' => 'SUP-EMPTY',
            'name' => 'شركة بدون أقمشة',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('api.search.fabric-materials', [
            'supplier_id' => $supplierWithoutFabrics->id,
            'q' => 'مخم',
        ]));

        $response->assertOk()
            ->assertJsonCount(0)
            ->assertHeader('X-Supplier-Has-Fabrics', '0');
    }

    public function test_product_configurations_endpoint_accepts_both_model_id_and_product_model_id()
    {
        $response1 = $this->actingAs($this->user)->getJson(route('api.search.product-configurations', [
            'model_id' => $this->milanModel->id,
        ]));
        $response1->assertOk()->assertJsonCount(2);

        $response2 = $this->actingAs($this->user)->getJson(route('api.search.product-configurations', [
            'product_model_id' => $this->milanModel->id,
        ]));
        $response2->assertOk()->assertJsonCount(2);
    }

    public function test_unauthenticated_search_requests_are_rejected()
    {
        $response = $this->getJson(route('api.search.product-models', ['q' => 'ميل']));
        $response->assertUnauthorized();
    }
}
