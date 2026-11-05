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
        $this->confirmer = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id, 'is_active' => true]);
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

    public function test_mixed_order_imports_only_simple_products_and_requires_explicit_manual_completion(): void
    {
        $this->setUpMappings();
        $parent = Material::factory()->create(['is_active' => true, 'is_sellable_ready_product' => true, 'has_ready_options' => true, 'sales_sku' => 'PARENT-MATTRESS']);
        $option = Material::factory()->create(['is_active' => true, 'is_sellable_ready_product' => true, 'ready_parent_id' => $parent->id, 'ready_option_label' => '160×200', 'sales_sku' => 'LOCAL-MATTRESS-160']);
        $batch = app(SalesImportParserService::class)->parse(
            base_path('tests/Fixtures/salla_sample_orders.csv'), 'salla_sample_orders.csv', $this->channel, $this->confirmer
        );
        $row = $batch->rows()->where('external_order_reference', 'SALLA-1001')->firstOrFail();
        $data = $row->raw_data;
        $data['lines'][] = ['product_name' => 'مرتبة بمقاسات', 'sku' => 'PARENT-MATTRESS', 'quantity' => 1, 'unit_price' => 700];
        $data['lines'][0]['line_discount'] = 50;
        $data['lines'][] = ['product_name' => 'سرير يحتاج مواصفات', 'sku' => 'UNKNOWN-BED', 'quantity' => 1, 'unit_price' => 1200];
        $data['lines'][] = ['product_name' => 'مقاس محلي', 'sku' => 'LOCAL-MATTRESS-160', 'quantity' => 1, 'unit_price' => 700];
        $row->update(['raw_data' => $data]);

        app(SalesImportConfirmService::class)->confirm($batch->fresh(), $this->confirmer);

        $order = CustomerOrder::findOrFail($row->fresh()->created_customer_order_id);
        $this->assertSame('DRAFT', $order->status);
        $this->assertSame(1, $order->lines()->count());
        $this->assertCount(3, $order->manual_import_lines);
        $this->assertSame(0.0, (float) $order->confirmed_paid_amount);
        $this->actingAs($this->confirmer)->get(route('sales.incomplete-imports.index'))->assertSee($order->order_number);
        $this->get(route('sales.orders.edit', $order))->assertSee('سرير يحتاج مواصفات');
        $this->post(route('sales.orders.submit-review', $order))->assertSessionHas('error');
        $existing = $order->lines()->firstOrFail();
        $payload = [
            'customer_id' => $order->customer_id, 'sales_channel_id' => $order->sales_channel_id,
            'order_date' => $order->order_date->toDateString(), 'priority' => 'NORMAL',
            'lines' => [
                ['id' => $existing->id, 'line_type' => 'READY_STOCK', 'material_id' => $existing->material_id, 'quantity' => 1, 'unit_price' => 500],
                ['line_type' => 'READY_STOCK', 'material_id' => $option->id, 'quantity' => 2, 'unit_price' => 700],
                ['line_type' => 'MANUFACTURED', 'custom_design' => 1, 'custom_design_name' => 'سرير مخصص', 'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 1, 'unit_price' => 1200],
            ],
        ];
        $this->put(route('sales.orders.update', $order), $payload)->assertSessionHasNoErrors();
        $this->assertCount(3, $order->fresh()->manual_import_lines);
        $this->assertSame('3050.00', $order->fresh()->total_amount);
        $payload['import_completion_confirmed'] = 1;

        $this->put(route('sales.orders.update', $order), $payload)->assertSessionHasNoErrors();

        $this->assertNull($order->fresh()->manual_import_lines);
        $this->post(route('sales.orders.submit-review', $order))->assertRedirect(route('sales.orders.show', $order));
        $this->assertSame('PENDING_PRODUCTION_REVIEW', $order->fresh()->status);
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
        $this->assertSame(0, $order->lines()->count());
        $this->assertCount(1, $order->manual_import_lines);
        $this->assertSame($row->raw_data['lines'][0]['product_name'], $order->manual_import_lines[0]['product_name']);
    }

    public function test_ready_stock_import_preserves_order_price_and_quantity_instead_of_catalog_values(): void
    {
        $this->setUpMappings();
        Material::where('code', 'MAT-CONFIRM-READY-0001')->update(['sales_price' => 900]);
        $batch = app(SalesImportParserService::class)->parse(
            base_path('tests/Fixtures/salla_sample_orders.csv'), 'salla_sample_orders.csv', $this->channel, $this->confirmer
        );
        $row = $batch->rows()->where('external_order_reference', 'SALLA-1001')->firstOrFail();
        $data = $row->raw_data;
        $data['lines'][0]['quantity'] = 3;
        $data['lines'][0]['unit_price'] = 419.5;
        $row->update(['raw_data' => $data]);

        app(SalesImportConfirmService::class)->confirm($batch->fresh(), $this->confirmer);

        $order = CustomerOrder::findOrFail($row->fresh()->created_customer_order_id);
        $this->assertDatabaseHas('customer_order_lines', [
            'customer_order_id' => $order->id, 'line_type' => 'READY_STOCK',
            'external_product_name' => 'مرتبة طبية فاخرة اختبار', 'quantity' => 3,
            'unit_price' => 419.5, 'line_total' => 1258.5,
        ]);
        $this->assertSame(1258.5, (float) $order->total_amount);
    }

    public function test_bundle_order_waits_for_manual_entry_without_creating_component_lines(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1003');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $lines = $order->lines;

        $this->assertCount(0, $lines);
        $this->assertCount(1, $order->manual_import_lines);
        $this->assertSame('DRAFT', $order->status);
        $this->assertSame(0.0, (float) $order->confirmed_paid_amount);
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

    public function test_unmapped_item_is_kept_for_manual_entry_without_assuming_it_is_manufactured(): void
    {
        $this->setUpMappings();
        $batch = $this->parseAndConfirm();

        $row = $batch->rows->firstWhere('external_order_reference', 'SALLA-1006');
        $this->assertEquals('CREATED', $row->outcome);

        $order = CustomerOrder::find($row->created_customer_order_id);
        $this->assertSame(0, $order->lines()->count());
        $this->assertSame('منتج غير معروف اختبار', $order->manual_import_lines[0]['product_name']);
    }
}
