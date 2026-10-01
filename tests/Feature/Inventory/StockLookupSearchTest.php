<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryLot;
use Tests\Feature\Production\ProductionMaterialRequestTest;

class StockLookupSearchTest extends ProductionMaterialRequestTest
{
    public function test_stock_lookup_finds_lots_and_materials_by_fabric_colour_code(): void
    {
        InventoryLot::create([
            'lot_code' => 'LOT-COLOUR-777',
            'material_id' => $this->material->id,
            'fabric_color_code' => '777',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'original_quantity' => 12,
            'remaining_quantity' => 12,
            'base_unit_id' => $this->material->base_unit_id,
            'unit_cost' => 40,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->warehouseKeeper)->get(route('inventory.balances.index', ['search' => '777']));

        $response->assertOk()->assertSee('LOT-COLOUR-777');
        $this->assertSame([$this->material->id], $response->viewData('materials')->pluck('id')->all());
        $this->assertSame(['LOT-COLOUR-777'], $response->viewData('lots')->pluck('lot_code')->all());
    }

    public function test_stock_lookup_colour_search_excludes_other_colours(): void
    {
        $response = $this->actingAs($this->warehouseKeeper)->get(route('inventory.balances.index', ['search' => 'no-such-colour']));

        $response->assertOk()->assertDontSee('LOT-TEST-001');
        $this->assertSame(0, $response->viewData('lots')->count());
    }
}
