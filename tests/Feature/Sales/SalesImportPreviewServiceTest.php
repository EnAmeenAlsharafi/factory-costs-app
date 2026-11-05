<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Models\SalesItemMapping;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SalesImportParserService;
use App\Services\Sales\SalesImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesImportPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function parseFixture(): SalesImportBatch
    {
        $channel = SalesChannel::where('code', 'SADIR_STORE')->first();
        $uploader = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id]);

        return app(SalesImportParserService::class)->parse(
            base_path('tests/Fixtures/salla_sample_orders.csv'),
            'salla_sample_orders.csv',
            $channel,
            $uploader
        );
    }

    protected function mapReadyStockSku(): Material
    {
        $unit = UnitOfMeasure::first();
        $category = MaterialCategory::where('code', 'READY_GOODS')->first();
        $material = Material::create([
            'code' => 'MAT-PREVIEW-READY-0001',
            'name_ar' => 'مرتبة طبية فاخرة',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        SalesItemMapping::create([
            'sales_channel_id' => SalesChannel::where('code', 'SADIR_STORE')->first()->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-test-001',
            'target_type' => 'READY_STOCK',
            'material_id' => $material->id,
            'is_active' => true,
        ]);

        return $material;
    }

    public function test_cancelled_order_is_classified_as_skip(): void
    {
        $this->seed();
        $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row4 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1004');

        $this->assertEquals('SKIP', $row4['decision']);
        $this->assertStringContainsString('ملغي', $row4['skip_reason']);
    }

    public function test_courier_shipped_ready_only_order_is_skipped(): void
    {
        $this->seed();
        $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row5 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1005');

        $this->assertEquals('SKIP', $row5['decision']);
    }

    public function test_ready_stock_order_is_imported_with_mapping_resolved(): void
    {
        $this->seed();
        $material = $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row1 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1001');

        $this->assertEquals('IMPORT', $row1['decision']);
        $this->assertEquals('READY_STOCK', $row1['lines'][0]['target_type']);
        $this->assertSame('مرتبة طبية فاخرة اختبار', $row1['lines'][0]['product_name']);
        $this->assertSame(500.0, $row1['lines'][0]['unit_price']);
        $this->assertEquals(1, $row1['lines'][0]['quantity']);
        $this->assertEquals($material->id, $row1['lines'][0]['mapping']->material_id);
        $this->assertNotNull($row1['lines'][0]['ready_stock_availability']);
        $this->assertTrue($row1['customer']['matched'] === false);
    }

    public function test_cod_manufactured_order_is_imported_with_warning(): void
    {
        $this->seed();
        $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row2 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1002');

        $this->assertEquals('IMPORT', $row2['decision']);
        $this->assertNotEmpty($row2['warnings']);
    }

    public function test_ready_stock_preview_uses_catalog_name_when_the_imported_name_is_missing(): void
    {
        $this->seed();
        $material = $this->mapReadyStockSku();
        $material->update(['sales_display_name' => 'مرتبة جاهزة من الكتالوج', 'sales_price' => 900]);
        $batch = $this->parseFixture();
        $row = $batch->rows()->where('external_order_reference', 'SALLA-1001')->firstOrFail();
        $data = $row->raw_data;
        unset($data['lines'][0]['product_name']);
        $data['lines'][0]['quantity'] = 3;
        $data['lines'][0]['unit_price'] = 419.5;
        $row->update(['raw_data' => $data]);

        $preview = app(SalesImportPreviewService::class)->preview($batch->fresh());
        $readyLine = collect($preview)->firstWhere('external_order_reference', 'SALLA-1001')['lines'][0];

        $this->assertSame('مرتبة جاهزة من الكتالوج', $readyLine['product_name']);
        $this->assertSame(3, $readyLine['quantity']);
        $this->assertSame(419.5, $readyLine['unit_price']);
        $this->actingAs($batch->uploadedByUser)->get(route('sales.imports.preview', $batch))
            ->assertSee('سعر البيع للوحدة')->assertSee('مرتبة جاهزة من الكتالوج')->assertSee('419.50');
    }

    public function test_unmapped_sku_produces_warning_and_is_still_imported_via_internal_shipping(): void
    {
        $this->seed();
        $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row6 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1006');

        $this->assertEquals('IMPORT', $row6['decision']);
        $this->assertSame('MANUAL', $row6['lines'][0]['target_type']);
        $this->assertTrue(collect($row6['warnings'])->contains(fn ($w) => str_contains($w, 'يدويًا')));
    }
}
