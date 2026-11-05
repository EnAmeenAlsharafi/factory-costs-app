<?php

namespace Tests\Feature\Sales;

use App\Models\CustomerOrder;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesBundleComponent;
use App\Models\SalesBundleDefinition;
use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Models\SalesItemMapping;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SalesImportConfirmService;
use App\Services\Sales\SalesImportParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesImportConfirmServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $confirmer;

    protected SalesChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->channel = SalesChannel::where('code', 'SADIR_STORE')->first();
        $this->confirmer = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id]);
    }

    protected function setUpMappings(): void
    {
        $unit = UnitOfMeasure::first();
        $readyCategory = MaterialCategory::where('code', 'READY_GOODS')->first();

        $readyMaterial = Material::create([
            'code' => 'MAT-CONFIRM-READY-0001',
            'name_ar' => 'مرتبة طبية فاخرة',
            'material_category_id' => $readyCategory->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        SalesItemMapping::create([
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-test-001',
            'target_type' => 'READY_STOCK',
            'material_id' => $readyMaterial->id,
            'is_active' => true,
        ]);

        $productModel = ProductModel::create([
            'model_code' => 'MOD-CONFIRM-TEST-001',
            'name_ar' => 'سرير اختبار البكج',
            'is_active' => true,
        ]);

        $configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-CONFIRM-TEST-001',
            'product_model_id' => $productModel->id,
            'width_cm' => 160,
            'length_cm' => 200,
            'has_storage' => false,
            'is_standard' => true,
            'is_active' => true,
        ]);

        $bundleMaterial = Material::create([
            'code' => 'MAT-CONFIRM-READY-0002',
            'name_ar' => 'طقم مفارش للبكج',
            'material_category_id' => $readyCategory->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        $bundle = SalesBundleDefinition::create([
            'name' => 'بكج تجريبي اختبار',
            'match_name_normalized' => 'بكج تجريبي اختبار',
            'is_active' => true,
        ]);

        SalesBundleComponent::create([
            'bundle_definition_id' => $bundle->id,
            'component_type' => 'MANUFACTURED',
            'product_model_id' => $productModel->id,
            'product_configuration_id' => $configuration->id,
            'quantity' => 1,
        ]);

        SalesBundleComponent::create([
            'bundle_definition_id' => $bundle->id,
            'component_type' => 'READY_STOCK',
            'material_id' => $bundleMaterial->id,
            'quantity' => 2,
        ]);

        SalesItemMapping::create([
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'bnd-test-001',
            'target_type' => 'BUNDLE',
            'bundle_definition_id' => $bundle->id,
            'is_active' => true,
        ]);
    }

    protected function parseAndConfirm(): SalesImportBatch
    {
        $batch = app(SalesImportParserService::class)->parse(
            base_path('tests/Fixtures/salla_sample_orders.csv'),
            'salla_sample_orders.csv',
            $this->channel,
            $this->confirmer
        );

        return app(SalesImportConfirmService::class)->confirm($batch, $this->confirmer);
    }

    public function test_ready_stock_paid_order_is_created_fully_paid_and_fulfillable(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1001');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $this->assertEquals('APPROVED_FOR_FULFILLMENT', $order->status);
        $this->assertEquals(0.0, (float) $order->outstanding_balance);
        $this->assertEquals(1, $order->lines()->count());
        $this->assertEquals('READY_STOCK', $order->lines()->first()->line_type);
        $this->assertSame('مرتبة طبية فاخرة اختبار', $order->lines()->first()->display_name);
        $this->assertSame(500.0, (float) $order->lines()->first()->unit_price);
        $this->assertSame(1.0, (float) $order->lines()->first()->quantity);
    }

    public function test_manufactured_order_is_created_as_incomplete_draft(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1002');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $this->assertEquals('DRAFT', $order->status);
        $line = $order->lines()->first();
        $this->assertEquals('MANUFACTURED', $line->line_type);
        $this->assertTrue((bool) $line->is_incomplete);
        $this->assertNull($line->requested_width_cm);
    }

    public function test_bundle_order_creates_parent_and_component_lines(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1003');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $lines = $order->lines;

        $this->assertCount(3, $lines); // bundle parent + 2 components
        $parent = $lines->firstWhere('line_type', 'BUNDLE');
        $this->assertNotNull($parent);
        $this->assertEquals(4000, (float) $parent->line_total);

        $manufacturedChild = $lines->firstWhere('line_type', 'MANUFACTURED');
        $this->assertNotNull($manufacturedChild);
        $this->assertEquals(0, (float) $manufacturedChild->unit_price);
        $this->assertEquals(160, (float) $manufacturedChild->requested_width_cm);

        $readyChild = $lines->firstWhere('line_type', 'READY_STOCK');
        $this->assertNotNull($readyChild);
        $this->assertEquals(2, (float) $readyChild->quantity); // component qty 2 * ordered qty 1
    }

    public function test_cancelled_and_courier_only_orders_are_skipped_not_created(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $skip4 = $batch->rows->firstWhere('external_order_reference', 'SALLA-1004');
        $skip5 = $batch->rows->firstWhere('external_order_reference', 'SALLA-1005');

        $this->assertEquals('SKIPPED', $skip4->outcome);
        $this->assertEquals('SKIPPED', $skip5->outcome);
        $this->assertNull($skip4->created_customer_order_id);
    }

    public function test_reimporting_the_same_file_marks_rows_as_duplicate_not_new_orders(): void
    {
        $this->setUpMappings();
        $batch1 = $this->parseAndConfirm();
        $createdCount = $batch1->rows->where('outcome', 'CREATED')->count();
        $ordersBefore = CustomerOrder::count();

        $batch2 = $this->parseAndConfirm();

        $this->assertEquals($ordersBefore, CustomerOrder::count(), 'No new orders should be created on re-import.');
        $duplicates = $batch2->rows->where('outcome', 'DUPLICATE')->count();
        $this->assertEquals($createdCount, $duplicates);
    }

    public function test_unmapped_item_order_is_still_created_as_incomplete_manufactured_placeholder(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1006');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $line = $order->lines()->first();
        $this->assertEquals('MANUFACTURED', $line->line_type);
        $this->assertTrue((bool) $line->is_incomplete);
        $this->assertEquals('منتج غير معروف اختبار', $line->external_product_name);
    }
}
