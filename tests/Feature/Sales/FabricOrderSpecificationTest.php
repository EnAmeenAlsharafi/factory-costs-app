<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CustomerOrderService;
use App\Services\ProductionMaterialRequestService;
use App\Services\ProductionOrderService;
use App\Services\QuotationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FabricOrderSpecificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;

    protected User $admin;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected ProductModel $milanBedModel;

    protected ProductModel $nonFabricModel;

    protected ProductConfiguration $milanConfig;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Material $velvetFabric;

    protected Material $satinFabric;

    protected Material $woodMaterial;

    protected UnitOfMeasure $meterUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $salesRole = Role::where('name', 'sales_user')->first() ?? $adminRole;
        $this->salesUser = User::factory()->create(['role_id' => $salesRole->id, 'is_active' => true]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'WHOLESALE'],
            ['name_ar' => 'عميل جملة', 'is_active' => true]
        );

        $this->customer = Customer::create([
            'customer_code' => 'CUS-200001',
            'customer_type_id' => $customerType->id,
            'name' => 'معرض المفروشات الفاخرة',
            'is_active' => true,
        ]);

        $this->salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'SHOWROOM'],
            ['name_ar' => 'المعارض المباشرة', 'is_active' => true]
        );

        // Bed model requiring fabric
        $this->milanBedModel = ProductModel::create([
            'model_code' => 'MOD-MILAN',
            'name_ar' => 'سرير ميلانو',
            'requires_fabric_selection' => true,
            'is_active' => true,
        ]);

        // Non-fabric model (e.g., wooden table)
        $this->nonFabricModel = ProductModel::create([
            'model_code' => 'MOD-TABLE',
            'name_ar' => 'طاولة طعام خشبية',
            'requires_fabric_selection' => false,
            'is_active' => true,
        ]);

        $this->milanConfig = ProductConfiguration::create([
            'product_model_id' => $this->milanBedModel->id,
            'configuration_code' => 'CFG-160X200',
            'width_cm' => 160.0,
            'length_cm' => 200.0,
            'has_storage' => false,
            'is_active' => true,
        ]);

        $fabricCategory = MaterialCategory::firstOrCreate(
            ['code' => 'FABRIC'],
            ['name_ar' => 'أقمشة', 'is_active' => true]
        );

        $woodCategory = MaterialCategory::firstOrCreate(
            ['code' => 'WOOD'],
            ['name_ar' => 'أخشاب', 'is_active' => true]
        );

        $this->meterUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'METER'],
            ['name_ar' => 'متر', 'unit_type' => 'LENGTH', 'is_active' => true]
        );

        $this->velvetFabric = Material::create([
            'code' => 'MAT-VELVET',
            'material_category_id' => $fabricCategory->id,
            'name_ar' => 'مخمل تركي فاخر',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $this->satinFabric = Material::create([
            'code' => 'MAT-SATIN',
            'material_category_id' => $fabricCategory->id,
            'name_ar' => 'ساتان إيطالي',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $this->woodMaterial = Material::create([
            'code' => 'MAT-WOOD-BEECH',
            'material_category_id' => $woodCategory->id,
            'name_ar' => 'خشب زان ألماني',
            'base_unit_id' => $this->meterUnit->id,
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'supplier_code' => 'SUP-001',
            'name' => 'شركة الأقمشة العالمية (المورد أ)',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'supplier_code' => 'SUP-002',
            'name' => 'شركة المنسوجات الشرقية (المورد ب)',
            'is_active' => true,
        ]);

        // Link Supplier A to Velvet Fabric
        DB::table('material_supplier')->insert([
            'material_id' => $this->velvetFabric->id,
            'supplier_id' => $this->supplierA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link Supplier B to Satin Fabric
        DB::table('material_supplier')->insert([
            'material_id' => $this->satinFabric->id,
            'supplier_id' => $this->supplierB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fabric_fields_required_for_fabric_models()
    {
        // 1. Attempt creating order line with missing fabric supplier
        $response1 = $this->actingAs($this->salesUser)->post(route('sales.orders.store'), [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => null,
                    'fabric_material_id' => $this->velvetFabric->id,
                    'fabric_color_code' => '204',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);
        $response1->assertSessionHasErrors(['lines.0.fabric_supplier_id']);

        // 2. Attempt creating order line with missing color code
        $response2 = $this->actingAs($this->salesUser)->post(route('sales.orders.store'), [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplierA->id,
                    'fabric_material_id' => $this->velvetFabric->id,
                    'fabric_color_code' => '',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);
        $response2->assertSessionHasErrors(['lines.0.fabric_color_code']);

        // 3. Valid creation with all fabric fields
        $response3 = $this->actingAs($this->salesUser)->post(route('sales.orders.store'), [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplierA->id,
                    'fabric_material_id' => $this->velvetFabric->id,
                    'fabric_color_code' => '204',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);
        $response3->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_order_lines', [
            'fabric_supplier_id' => $this->supplierA->id,
            'fabric_material_id' => $this->velvetFabric->id,
            'fabric_color_code' => '204',
        ]);
    }

    public function test_supplier_fabric_mapping_backend_validation()
    {
        // Supplier B does NOT supply Velvet Fabric
        $response = $this->actingAs($this->salesUser)->post(route('sales.orders.store'), [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplierB->id,
                    'fabric_material_id' => $this->velvetFabric->id,
                    'fabric_color_code' => '204',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.fabric_supplier_id']);
    }

    public function test_fabric_category_only_validation()
    {
        // Attempt choosing Wood material as Fabric Material
        $response = $this->actingAs($this->salesUser)->post(route('sales.orders.store'), [
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplierA->id,
                    'fabric_material_id' => $this->woodMaterial->id,
                    'fabric_color_code' => '204',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.fabric_material_id']);
    }

    public function test_production_order_snapshot_integrity()
    {
        $order = CustomerOrder::create([
            'order_number' => 'ORD-TEST-001',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'APPROVED_FOR_PRODUCTION',
            'total_amount' => 2500,
            'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            'created_by_user_id' => $this->admin->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'product_model_id' => $this->milanBedModel->id,
            'product_configuration_id' => $this->milanConfig->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplierA->id,
            'fabric_material_id' => $this->velvetFabric->id,
            'fabric_color_code' => '204',
            'quantity' => 1,
            'unit_price' => 2500,
            'line_total' => 2500,
        ]);

        $poService = app(ProductionOrderService::class);
        $po = $poService->createFromOrderLine($line, [
            'planned_start_date' => now()->toDateString(),
        ]);

        $this->assertEquals($this->supplierA->id, $po->fabric_supplier_id);
        $this->assertEquals($this->velvetFabric->id, $po->fabric_material_id);
        $this->assertEquals('204', $po->fabric_color_code);

        // Rename Supplier & Material master
        $this->supplierA->update(['name' => 'اسم مورد معدل جديد']);
        $this->velvetFabric->update(['name_ar' => 'قماش مخمل ملغي']);

        // Production Order snapshot relationships still resolve accurately
        $po->refresh();
        $this->assertEquals('اسم مورد معدل جديد', $po->fabricSupplier->name);
        $this->assertEquals('204', $po->fabric_color_code);
    }

    public function test_production_material_requirement_resolves_customer_fabric()
    {
        $recipe = ManufacturingRecipe::create([
            'recipe_code' => 'RCP-MILAN',
            'product_configuration_id' => $this->milanConfig->id,
            'name' => 'وصفة تصنيع سرير ميلانو',
            'is_active' => true,
        ]);

        $recipeVersion = ManufacturingRecipeVersion::create([
            'manufacturing_recipe_id' => $recipe->id,
            'version_number' => 1,
            'status' => 'APPROVED',
            'is_current' => true,
            'created_by_user_id' => $this->admin->id,
        ]);

        // Recipe specifies generic fabric requirement (6 meters)
        ManufacturingRecipeItem::create([
            'recipe_version_id' => $recipeVersion->id,
            'material_id' => $this->velvetFabric->id,
            'quantity' => 6.00,
            'waste_percentage' => 0,
            'unit_id' => $this->meterUnit->id,
        ]);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-TEST-002',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'APPROVED_FOR_PRODUCTION',
            'total_amount' => 5000,
            'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            'created_by_user_id' => $this->admin->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'product_model_id' => $this->milanBedModel->id,
            'product_configuration_id' => $this->milanConfig->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplierA->id,
            'fabric_material_id' => $this->velvetFabric->id,
            'fabric_color_code' => 'COLOR-999',
            'quantity' => 2,
            'unit_price' => 2500,
            'line_total' => 5000,
        ]);

        $poService = app(ProductionOrderService::class);
        $po = $poService->createFromOrderLine($line, [
            'manufacturing_recipe_version_id' => $recipeVersion->id,
        ]);

        $reqService = app(ProductionMaterialRequestService::class);
        $reqCount = $reqService->createMaterialRequirementsFromRecipe($po);

        $this->assertEquals(1, $reqCount);
        $requirement = $po->materialRequirements()->first();

        $this->assertEquals($this->velvetFabric->id, $requirement->material_id);
        $this->assertStringContainsString('COLOR-999', $requirement->material_name_snapshot);
        $this->assertStringContainsString($this->supplierA->name, $requirement->material_name_snapshot);
        $this->assertEquals(12.00, (float) $requirement->total_planned_quantity);
    }

    public function test_quote_conversion_preserves_fabric_specs()
    {
        $quote = Quotation::create([
            'quotation_number' => 'QT-100001',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'quotation_date' => now()->toDateString(),
            'status' => 'APPROVED',
            'subtotal' => 3000,
            'discount_total' => 0.00,
            'total_amount' => 3000,
            'created_by_user_id' => $this->admin->id,
        ]);

        QuotationLine::create([
            'quotation_id' => $quote->id,
            'product_model_id' => $this->milanBedModel->id,
            'product_configuration_id' => $this->milanConfig->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplierA->id,
            'fabric_material_id' => $this->velvetFabric->id,
            'fabric_color_code' => 'SATIN-55',
            'quantity' => 1,
            'unit_price' => 3000,
            'line_total' => 3000,
        ]);

        $qService = app(QuotationService::class);
        $order = $qService->convertToOrder($quote, $this->admin);

        $this->assertEquals(1, $order->lines()->count());
        $orderLine = $order->lines()->first();

        $this->assertEquals($this->supplierA->id, $orderLine->fabric_supplier_id);
        $this->assertEquals($this->velvetFabric->id, $orderLine->fabric_material_id);
        $this->assertEquals('SATIN-55', $orderLine->fabric_color_code);
    }

    public function test_approved_order_fabric_change_tracking()
    {
        $order = CustomerOrder::create([
            'order_number' => 'ORD-TEST-003',
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'APPROVED_FOR_PRODUCTION',
            'total_amount' => 2500,
            'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            'created_by_user_id' => $this->admin->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'product_model_id' => $this->milanBedModel->id,
            'product_configuration_id' => $this->milanConfig->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'fabric_supplier_id' => $this->supplierA->id,
            'fabric_material_id' => $this->velvetFabric->id,
            'fabric_color_code' => '204',
            'quantity' => 1,
            'unit_price' => 2500,
            'line_total' => 2500,
        ]);

        $orderService = app(CustomerOrderService::class);

        // Update fabric spec on approved order
        $orderService->updateOrder(
            $order,
            [
                'customer_id' => $this->customer->id,
                'sales_channel_id' => $this->salesChannel->id,
                'order_date' => now()->toDateString(),
                'priority' => 'NORMAL',
                'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            ],
            [
                [
                    'id' => $line->id,
                    'product_model_id' => $this->milanBedModel->id,
                    'product_configuration_id' => $this->milanConfig->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'fabric_supplier_id' => $this->supplierB->id,
                    'fabric_material_id' => $this->satinFabric->id,
                    'fabric_color_code' => '118',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
            $this->admin
        );

        $this->assertEquals('PENDING_PRODUCTION_REVIEW', $order->fresh()->status);
        $this->assertDatabaseHas('customer_order_changes', [
            'customer_order_id' => $order->id,
            'field_name' => 'fabric_and_color',
            'old_value' => "{$this->supplierA->name} / {$this->velvetFabric->name_ar} / 204",
            'new_value' => "{$this->supplierB->name} / {$this->satinFabric->name_ar} / 118",
        ]);
    }
}
