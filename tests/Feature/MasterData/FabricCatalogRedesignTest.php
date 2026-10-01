<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\FabricColor;
use App\Models\FabricMaterialSpec;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FabricCatalogRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MaterialCategory $fabricCategory;

    protected UnitOfMeasure $meterUnit;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin')->first()
            ?? User::factory()->create(['is_active' => true]);

        $this->fabricCategory = MaterialCategory::firstOrCreate(
            ['code' => 'FABRIC'],
            ['name_ar' => 'أقمشة', 'is_active' => true]
        );

        $this->meterUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'METER'],
            ['name_ar' => 'متر', 'unit_type' => 'LENGTH', 'is_active' => true]
        );

        $this->supplier = Supplier::create([
            'supplier_code' => 'SUP-BED-CREATIVE',
            'name' => 'إبداع السرير',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Creating a fabric material auto-generates the standard naming format
     * "قماش {نوع} - {المورد} - {الكتالوج}" and links the supplier & catalog.
     */
    public function test_fabric_creation_auto_generates_standard_name_and_links_catalog(): void
    {
        $response = $this->actingAs($this->admin)->post(route('materials.store'), [
            'material_category_id' => $this->fabricCategory->id,
            'base_unit_id' => $this->meterUnit->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'catalog_name' => 'كتالوج شانيل 2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('materials.index'));

        $expectedName = 'قماش شانيل - إبداع السرير - 2020';
        $material = Material::where('name_ar', $expectedName)->first();
        $this->assertNotNull($material, "Expected material with name [{$expectedName}] was not found.");

        $this->assertDatabaseHas('fabric_material_specs', [
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'catalog_name' => 'كتالوج شانيل 2020',
            'fabric_type' => 'شانيل',
        ]);

        $this->assertDatabaseHas('material_supplier', [
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'is_preferred' => 1,
        ]);
    }

    /**
     * 2. Catalog number must be unique per supplier.
     */
    public function test_unique_catalog_number_per_supplier(): void
    {
        // First catalog 2020 for supplier
        $this->actingAs($this->admin)->post(route('materials.store'), [
            'material_category_id' => $this->fabricCategory->id,
            'base_unit_id' => $this->meterUnit->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
            'is_active' => 1,
        ]);

        // Second attempt with SAME catalog number for SAME supplier must fail validation
        $response = $this->actingAs($this->admin)->post(route('materials.store'), [
            'material_category_id' => $this->fabricCategory->id,
            'base_unit_id' => $this->meterUnit->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors(['catalog_number']);

        // Same catalog number for a DIFFERENT supplier must succeed
        $supplierB = Supplier::create([
            'supplier_code' => 'SUP-AL-RAJHI',
            'name' => 'مفروشات الراجحي',
            'is_active' => true,
        ]);

        $responseB = $this->actingAs($this->admin)->post(route('materials.store'), [
            'material_category_id' => $this->fabricCategory->id,
            'base_unit_id' => $this->meterUnit->id,
            'supplier_id' => $supplierB->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
            'is_active' => 1,
        ]);

        $responseB->assertRedirect(route('materials.index'));
        $this->assertDatabaseHas('materials', [
            'name_ar' => 'قماش شانيل - مفروشات الراجحي - 2020',
        ]);
    }

    /**
     * 3. Internal color code and supplier color code must each be unique per material.
     */
    public function test_unique_color_code_and_supplier_color_code_per_material(): void
    {
        $material = Material::create([
            'code' => 'MAT-TEST-01',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
        ]);

        // Add first color: internal 1, supplier 2020-1
        $res1 = $this->actingAs($this->admin)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => '1',
            'supplier_color_code' => '2020-1',
            'color_name_ar' => 'بيج',
            'is_available' => 1,
            'is_active' => 1,
        ]);
        $res1->assertRedirect();
        $this->assertDatabaseHas('fabric_colors', [
            'material_id' => $material->id,
            'color_code' => '1',
            'supplier_color_code' => '2020-1',
        ]);

        // Attempt duplicate internal color_code "1"
        $res2 = $this->actingAs($this->admin)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => '1',
            'supplier_color_code' => '2020-2',
            'color_name_ar' => 'رمادي',
            'is_available' => 1,
            'is_active' => 1,
        ]);
        $res2->assertSessionHasErrors(['color_code']);

        // Attempt duplicate supplier_color_code "2020-1"
        $res3 = $this->actingAs($this->admin)->post(route('fabric-colors.store'), [
            'material_id' => $material->id,
            'color_code' => '2',
            'supplier_color_code' => '2020-1',
            'color_name_ar' => 'رمادي',
            'is_available' => 1,
            'is_active' => 1,
        ]);
        $res3->assertSessionHasErrors(['supplier_color_code']);
    }

    /**
     * 4. Search API endpoints for fabrics and colors return rich metadata.
     */
    public function test_search_api_fabric_materials_and_colors_endpoints(): void
    {
        $material = Material::create([
            'code' => 'MAT-TEST-API',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'catalog_name' => 'شانيل 2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
        ]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '1',
            'supplier_color_code' => '2020-1',
            'color_name_ar' => 'بيج كريمي',
            'is_available' => true,
            'is_active' => true,
        ]);

        // Search fabrics endpoint
        $resFabrics = $this->actingAs($this->admin)->getJson('/api/search/fabric-materials?q=2020');
        $resFabrics->assertOk();
        $resFabrics->assertJsonFragment([
            'id' => $material->id,
            'catalog_number' => '2020',
            'supplier_name' => $this->supplier->name,
            'fabric_type' => 'شانيل',
        ]);

        // Search colors endpoint for this material
        $resColors = $this->actingAs($this->admin)->getJson("/api/search/fabric-materials/{$material->id}/colors");
        $resColors->assertOk();
        $resColors->assertJsonFragment([
            'id' => $color->id,
            'color_code' => '1',
            'supplier_color_code' => '2020-1',
            'color_name_ar' => 'بيج كريمي',
        ]);
    }

    /**
     * 5. CS order auto-infers supplier from fabric material and snapshots internal & supplier codes.
     */
    public function test_customer_order_auto_infers_supplier_and_snapshots_codes(): void
    {
        $customerType = CustomerType::firstOrCreate(['code' => 'INDIVIDUAL'], ['name_ar' => 'فردي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'name' => 'عبدالله بن أحمد',
            'customer_type_id' => $customerType->id,
            'phone' => '0501234567',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(['code' => 'SHOWROOM'], ['name_ar' => 'المعرض', 'is_active' => true]);

        $model = ProductModel::create([
            'model_code' => 'MOD-SOFA-01',
            'name_ar' => 'كنب مودرن',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $config = ProductConfiguration::create([
            'product_model_id' => $model->id,
            'configuration_code' => 'CFG-200',
            'width_cm' => 200,
            'length_cm' => 90,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $material = Material::create([
            'code' => 'MAT-CHENILLE-2020',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
        ]);

        DB::table('material_supplier')->insert([
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'is_preferred' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '3',
            'supplier_color_code' => '2020-3',
            'color_name_ar' => 'كحلي',
            'is_available' => true,
            'is_active' => true,
        ]);

        // Submit order with ONLY fabric_material_id and fabric_color_id (no supplier, no codes passed)
        $response = $this->actingAs($this->admin)->post(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $model->id,
                    'product_configuration_id' => $config->id,
                    'requested_width_cm' => 200,
                    'requested_length_cm' => 90,
                    'fabric_material_id' => $material->id,
                    'fabric_color_id' => $color->id,
                    'quantity' => 1,
                    'unit_price' => 3500,
                ],
            ],
        ]);

        $response->assertRedirect();

        $line = CustomerOrderLine::where('fabric_material_id', $material->id)->first();
        $this->assertNotNull($line);
        // Supplier must be inferred automatically
        $this->assertEquals($this->supplier->id, $line->fabric_supplier_id);
        // Color codes must be snapshotted
        $this->assertEquals('3', $line->fabric_color_code);
        $this->assertEquals('2020-3', $line->fabric_supplier_color_code);
    }

    /**
     * 6. Server strictly rejects selecting a color that belongs to another fabric material.
     */
    public function test_customer_order_rejects_color_from_different_material(): void
    {
        $customerType = CustomerType::firstOrCreate(['code' => 'INDIVIDUAL'], ['name_ar' => 'فردي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-002',
            'name' => 'سارة الشريف',
            'customer_type_id' => $customerType->id,
            'phone' => '0507654321',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(['code' => 'SHOWROOM'], ['name_ar' => 'المعرض', 'is_active' => true]);

        $model = ProductModel::create([
            'model_code' => 'MOD-BED-02',
            'name_ar' => 'سرير كينج',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $config = ProductConfiguration::create([
            'product_model_id' => $model->id,
            'configuration_code' => 'CFG-180',
            'width_cm' => 180,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $materialA = Material::create([
            'code' => 'MAT-A',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $materialB = Material::create([
            'code' => 'MAT-B',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش مخمل - إبداع السرير - 3030',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $materialA->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $materialB->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '3030',
            'fabric_type' => 'مخمل',
            'width_cm' => 140,
        ]);

        // Color belongs to Material B
        $colorB = FabricColor::create([
            'material_id' => $materialB->id,
            'color_code' => '5',
            'supplier_color_code' => '3030-5',
            'color_name_ar' => 'زيتي',
            'is_available' => true,
            'is_active' => true,
        ]);

        // Submitting with material A and color B must be rejected!
        $response = $this->actingAs($this->admin)->post(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $model->id,
                    'product_configuration_id' => $config->id,
                    'requested_width_cm' => 180,
                    'requested_length_cm' => 200,
                    'fabric_material_id' => $materialA->id,
                    'fabric_color_id' => $colorB->id,
                    'quantity' => 1,
                    'unit_price' => 3000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.fabric_color_id']);
    }

    /**
     * 7. Server rejects unavailable or inactive fabric colors.
     */
    public function test_customer_order_rejects_unavailable_or_inactive_color(): void
    {
        $customerType = CustomerType::firstOrCreate(['code' => 'INDIVIDUAL'], ['name_ar' => 'فردي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-003',
            'name' => 'خالد المهيدب',
            'customer_type_id' => $customerType->id,
            'phone' => '0509988776',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(['code' => 'SHOWROOM'], ['name_ar' => 'المعرض', 'is_active' => true]);

        $model = ProductModel::create([
            'model_code' => 'MOD-BED-03',
            'name_ar' => 'سرير مفرد',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        $config = ProductConfiguration::create([
            'product_model_id' => $model->id,
            'configuration_code' => 'CFG-120',
            'width_cm' => 120,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $material = Material::create([
            'code' => 'MAT-CHENILLE',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        FabricMaterialSpec::create([
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'catalog_number' => '2020',
            'fabric_type' => 'شانيل',
            'width_cm' => 140,
        ]);

        // Unavailable color
        $unavailableColor = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '7',
            'supplier_color_code' => '2020-7',
            'color_name_ar' => 'خردلي',
            'is_available' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $model->id,
                    'product_configuration_id' => $config->id,
                    'requested_width_cm' => 120,
                    'requested_length_cm' => 200,
                    'fabric_material_id' => $material->id,
                    'fabric_color_id' => $unavailableColor->id,
                    'quantity' => 1,
                    'unit_price' => 2000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.fabric_color_id']);
    }

    /**
     * 8. Snapshot propagation to Production Orders.
     */
    public function test_snapshot_propagates_to_production_order(): void
    {
        $material = Material::create([
            'code' => 'MAT-PO-TEST',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '2',
            'supplier_color_code' => '2020-2',
            'color_name_ar' => 'رمادي فاتح',
            'is_available' => true,
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(['code' => 'INDIVIDUAL'], ['name_ar' => 'فردي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-004',
            'name' => 'فهد القحطاني',
            'customer_type_id' => $customerType->id,
            'phone' => '0555555555',
            'is_active' => true,
        ]);
        $salesChannel = SalesChannel::firstOrCreate(['code' => 'SHOWROOM'], ['name_ar' => 'المعرض', 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-PO-01',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'APPROVED_FOR_PRODUCTION',
            'total_amount' => 3000,
            'created_by_user_id' => $this->admin->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplier->id,
            'fabric_material_id' => $material->id,
            'fabric_color_id' => $color->id,
            'fabric_color_code' => '2',
            'fabric_supplier_color_code' => '2020-2',
            'quantity' => 1,
            'unit_price' => 3000,
            'line_total' => 3000,
        ]);

        $poService = app(ProductionOrderService::class);
        $po = $poService->createFromOrderLine($line, []);

        $this->assertNotNull($po);
        $this->assertEquals('2020-2', $po->fabric_supplier_color_code);
        $this->assertEquals('2', $po->fabric_color_code);
    }

    /**
     * 9. Cannot delete a fabric color that is used in customer orders, but can toggle availability.
     */
    public function test_cannot_delete_color_used_in_orders_but_can_toggle_availability(): void
    {
        $material = Material::create([
            'code' => 'MAT-DEL-TEST',
            'material_category_id' => $this->fabricCategory->id,
            'name_ar' => 'قماش شانيل - إبداع السرير - 2020',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $color = FabricColor::create([
            'material_id' => $material->id,
            'color_code' => '4',
            'supplier_color_code' => '2020-4',
            'color_name_ar' => 'بني',
            'is_available' => true,
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(['code' => 'INDIVIDUAL'], ['name_ar' => 'فردي', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-005',
            'name' => 'سلطان الدوسري',
            'customer_type_id' => $customerType->id,
            'phone' => '0544444444',
            'is_active' => true,
        ]);
        $salesChannel = SalesChannel::firstOrCreate(['code' => 'SHOWROOM'], ['name_ar' => 'المعرض', 'is_active' => true]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-DEL-01',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'DRAFT',
            'total_amount' => 1000,
            'created_by_user_id' => $this->admin->id,
        ]);

        CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplier->id,
            'fabric_material_id' => $material->id,
            'fabric_color_id' => $color->id,
            'fabric_color_code' => '4',
            'fabric_supplier_color_code' => '2020-4',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        // Attempt deletion of color used in order lines
        $delResponse = $this->actingAs($this->admin)->delete(route('fabric-colors.destroy', $color));
        $delResponse->assertSessionHas('error');
        $this->assertDatabaseHas('fabric_colors', ['id' => $color->id]);

        // Toggle status / availability succeeds
        $toggleResponse = $this->actingAs($this->admin)->post(route('fabric-colors.toggle-status', $color), [
            'toggle_availability' => 1,
        ]);
        $toggleResponse->assertRedirect();
        $this->assertFalse((bool) $color->fresh()->is_available);
    }

    /**
     * 10. Verification of old tables preservation in schema.
     */
    public function test_old_catalogs_tables_remain_intact_in_schema(): void
    {
        $this->assertTrue(
            Schema::hasTable('supplier_fabric_catalogs'),
            'supplier_fabric_catalogs table must NOT be deleted in this phase.'
        );

        $this->assertTrue(
            Schema::hasTable('supplier_fabric_catalog_colors'),
            'supplier_fabric_catalog_colors table must NOT be deleted in this phase.'
        );
    }
}
