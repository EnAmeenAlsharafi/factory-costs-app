<?php

namespace Tests\Feature;

use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\ProductionMaterialRequest;
use App\Models\Warehouse;
use App\Services\ProductionMaterialRequestService;
use Exception;
use Tests\Feature\Production\ProductionMaterialRequestTest;

class ProductionMaterialFulfillmentIntegrityTest extends ProductionMaterialRequestTest
{
    public function test_rejects_line_from_another_request_without_side_effects(): void
    {
        [$request] = $this->submittedRequest(10);
        [, $foreignLine] = $this->submittedRequest(5);

        $this->assertFulfillmentRejected($request, $foreignLine->id, $this->lot, 5);
    }

    public function test_rejects_lot_from_another_warehouse_without_side_effects(): void
    {
        [$request, $line] = $this->submittedRequest(10);
        $warehouse = Warehouse::create(['code' => 'OTHER', 'name_ar' => 'مستودع آخر', 'is_active' => true]);
        $lot = $this->makeLot($this->material, $warehouse, 'LOT-OTHER-WH');

        $this->assertFulfillmentRejected($request, $line->id, $lot, 5);
    }

    public function test_rejects_lot_with_another_material_without_side_effects(): void
    {
        [$request, $line] = $this->submittedRequest(10);
        $material = Material::create([
            'code' => 'MAT-OTHER',
            'name_ar' => 'خامة أخرى',
            'material_category_id' => $this->material->material_category_id,
            'base_unit_id' => $this->material->base_unit_id,
            'is_active' => true,
        ]);
        $lot = $this->makeLot($material, $this->warehouse, 'LOT-OTHER-MAT');

        $this->assertFulfillmentRejected($request, $line->id, $lot, 5);
    }

    public function test_rejects_lot_with_another_fabric_color_without_side_effects(): void
    {
        $requestedColor = FabricColor::create([
            'material_id' => $this->material->id,
            'color_code' => 'RED',
            'color_name_ar' => 'أحمر',
            'is_active' => true,
        ]);
        $otherColor = FabricColor::create([
            'material_id' => $this->material->id,
            'color_code' => 'BLUE',
            'color_name_ar' => 'أزرق',
            'is_active' => true,
        ]);
        [$request, $line] = $this->submittedRequest(10, $requestedColor->id);
        $lot = $this->makeLot($this->material, $this->warehouse, 'LOT-BLUE', $otherColor->id);

        $this->assertFulfillmentRejected($request, $line->id, $lot, 5);
    }

    public function test_rejects_over_fulfillment_without_side_effects(): void
    {
        [$request, $line] = $this->submittedRequest(10);

        $this->assertFulfillmentRejected($request, $line->id, $this->lot, 11);
    }

    public function test_rejects_second_fulfillment_after_request_is_fully_fulfilled(): void
    {
        [$request, $line] = $this->submittedRequest(10);
        $service = app(ProductionMaterialRequestService::class);
        $service->fulfillRequest($request, $this->warehouseKeeper, [[
            'request_line_id' => $line->id,
            'inventory_lot_id' => $this->lot->id,
            'quantity' => 10,
        ]]);

        $movementCount = \DB::table('inventory_movements')->count();
        $remaining = (float) $this->lot->fresh()->remaining_quantity;

        try {
            $service->fulfillRequest($request->fresh(), $this->warehouseKeeper, [[
                'request_line_id' => $line->id,
                'inventory_lot_id' => $this->lot->id,
                'quantity' => 1,
            ]]);
            $this->fail('A fully fulfilled request accepted another issue.');
        } catch (Exception) {
            $this->assertSame($movementCount, \DB::table('inventory_movements')->count());
            $this->assertSame($remaining, (float) $this->lot->fresh()->remaining_quantity);
            $this->assertSame(10.0, (float) $line->fresh()->issued_quantity);
        }
    }

    public function test_partial_fulfillment_across_multiple_lots_reaches_exact_ceiling(): void
    {
        [$request, $line] = $this->submittedRequest(100);
        $lotA = $this->makeLot($this->material, $this->warehouse, 'LOT-A', null, 70);
        $lotB = $this->makeLot($this->material, $this->warehouse, 'LOT-B', null, 30);
        $service = app(ProductionMaterialRequestService::class);

        $service->fulfillRequest($request, $this->warehouseKeeper, [[
            'request_line_id' => $line->id,
            'inventory_lot_id' => $lotA->id,
            'quantity' => 70,
        ]]);
        $this->assertSame('PARTIALLY_FULFILLED', $request->fresh()->status);

        $service->fulfillRequest($request->fresh(), $this->warehouseKeeper, [[
            'request_line_id' => $line->id,
            'inventory_lot_id' => $lotB->id,
            'quantity' => 30,
        ]]);

        $this->assertSame('FULFILLED', $request->fresh()->status);
        $this->assertSame(100.0, (float) $line->fresh()->issued_quantity);
        $this->assertSame(0.0, (float) $lotA->fresh()->remaining_quantity);
        $this->assertSame(0.0, (float) $lotB->fresh()->remaining_quantity);
    }

    private function submittedRequest(float $quantity, ?int $fabricColorId = null): array
    {
        $service = app(ProductionMaterialRequestService::class);
        $request = $service->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'material_id' => $this->material->id,
                'fabric_color_id' => $fabricColorId,
                'requested_quantity' => $quantity,
                'approved_quantity' => $quantity,
                'base_unit_id' => $this->material->base_unit_id,
            ]],
        ]);
        $service->submitRequest($request);

        return [$request->fresh(), $request->lines()->first()];
    }

    private function makeLot(Material $material, Warehouse $warehouse, string $code, ?int $colorId = null, float $quantity = 100): InventoryLot
    {
        return InventoryLot::create([
            'lot_code' => $code,
            'material_id' => $material->id,
            'fabric_color_id' => $colorId,
            'warehouse_id' => $warehouse->id,
            'received_date' => now(),
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'base_unit_id' => $material->base_unit_id,
            'unit_cost' => 10,
            'status' => 'ACTIVE',
        ]);
    }

    private function assertFulfillmentRejected(ProductionMaterialRequest $request, int $lineId, InventoryLot $lot, float $quantity): void
    {
        $movementCount = \DB::table('inventory_movements')->count();
        $issueCount = \DB::table('material_issues')->count();
        $remaining = (float) $lot->fresh()->remaining_quantity;
        $issued = (float) $request->lines()->sum('issued_quantity');

        try {
            app(ProductionMaterialRequestService::class)->fulfillRequest($request, $this->warehouseKeeper, [[
                'request_line_id' => $lineId,
                'inventory_lot_id' => $lot->id,
                'quantity' => $quantity,
            ]]);
            $this->fail('Invalid fulfillment was accepted.');
        } catch (Exception) {
            $this->assertSame($movementCount, \DB::table('inventory_movements')->count());
            $this->assertSame($issueCount, \DB::table('material_issues')->count());
            $this->assertSame($remaining, (float) $lot->fresh()->remaining_quantity);
            $this->assertSame($issued, (float) $request->lines()->sum('issued_quantity'));
        }
    }
}
