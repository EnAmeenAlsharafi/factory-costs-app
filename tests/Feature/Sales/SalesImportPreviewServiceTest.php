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

    public function test_unmapped_sku_produces_warning_and_is_still_imported_via_internal_shipping(): void
    {
        $this->seed();
        $this->mapReadyStockSku();
        $batch = $this->parseFixture();

        $preview = app(SalesImportPreviewService::class)->preview($batch);
        $row6 = collect($preview)->firstWhere('external_order_reference', 'SALLA-1006');

        $this->assertEquals('IMPORT', $row6['decision']);
        $this->assertNull($row6['lines'][0]['target_type']);
        $this->assertTrue(collect($row6['warnings'])->contains(fn ($w) => str_contains($w, 'غير مطابَقة')));
    }
}
