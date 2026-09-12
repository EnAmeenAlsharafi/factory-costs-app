<?php

namespace Tests\Feature;

use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_generation_is_unique_and_preserves_formats(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 12));

        $issues = collect(range(1, 5))->map(fn () => DocumentNumberService::generateIssueNumber());
        $lots = collect(range(1, 5))->map(fn () => DocumentNumberService::generateLotCode());

        $this->assertSame($issues->count(), $issues->unique()->count());
        $this->assertSame('ISS-2026-000001', $issues->first());
        $this->assertSame('ISS-2026-000005', $issues->last());
        $this->assertSame('LOT-000001', $lots->first());
        $this->assertSame('LOT-000005', $lots->last());
    }

    public function test_yearly_sequence_resets_for_a_new_period(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->assertSame('PMR-2026-000001', DocumentNumberService::generateRequestNumber());

        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('PMR-2027-000001', DocumentNumberService::generateRequestNumber());
    }

    public function test_sequence_initializes_above_existing_data(): void
    {
        $this->seed();
        $material = Material::create([
            'code' => 'MAT-EXISTING',
            'name_ar' => 'خامة اختبار',
            'material_category_id' => MaterialCategory::firstOrFail()->id,
            'base_unit_id' => UnitOfMeasure::firstOrFail()->id,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::firstOrFail();

        InventoryLot::create([
            'lot_code' => 'LOT-000042',
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'received_date' => now(),
            'original_quantity' => 1,
            'remaining_quantity' => 1,
            'base_unit_id' => $material->base_unit_id,
            'unit_cost' => 1,
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('LOT-000043', DocumentNumberService::generateLotCode());
        $this->assertSame('LOT-000044', DocumentNumberService::generateLotCode());
    }

    public function test_master_data_generators_keep_their_public_formats(): void
    {
        $this->assertMatchesRegularExpression('/^MAT-\d{6}$/', DocumentNumberService::generateMaterialCode());
        $this->assertMatchesRegularExpression('/^CUS-\d{6}$/', DocumentNumberService::generateCustomerCode());
        $this->assertMatchesRegularExpression('/^SUP-\d{6}$/', DocumentNumberService::generateSupplierCode());
    }
}
