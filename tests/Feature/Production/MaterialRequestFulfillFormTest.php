<?php

namespace Tests\Feature\Production;

use App\Models\InventoryLot;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionMaterialRequestLine;
use App\Services\ProductionMaterialRequestService;

class MaterialRequestFulfillFormTest extends ProductionMaterialRequestTest
{
    public function test_fulfill_form_preallocates_fifo_without_exceeding_remaining(): void
    {
        [$request] = $this->submittedRequest(120);
        $secondLot = $this->makeLot('LOT-FIFO-B', 50);

        $response = $this->actingAs($this->warehouseKeeper)->get(route('production.material-requests.fulfill.form', $request));

        $response->assertOk()->assertSee('LOT-FIFO-B');
        // First lot (100) is fully used, the second is pre-filled with the 20 still needed (@js escapes JSON quotes).
        $quote = '(?:"|\\\\u0022)';
        $this->assertMatchesRegularExpression("/{$quote}on{$quote}:true,{$quote}qty{$quote}:100,{$quote}max{$quote}:100\\}/", $response->getContent());
        $this->assertMatchesRegularExpression("/{$quote}on{$quote}:true,{$quote}qty{$quote}:20,{$quote}max{$quote}:50\\}/", $response->getContent());
        $this->assertSame(50.0, (float) $secondLot->fresh()->remaining_quantity);
    }

    public function test_issuing_only_selected_lot_fulfills_request(): void
    {
        [$request, $line] = $this->submittedRequest(10);

        $response = $this->actingAs($this->warehouseKeeper)->post(route('production.material-requests.fulfill', $request), [
            'fulfillments' => [
                3 => ['request_line_id' => $line->id, 'inventory_lot_id' => $this->lot->id, 'quantity' => 10],
            ],
        ]);

        $response->assertRedirect(route('production.material-requests.show', $request))->assertSessionHas('success');
        $this->assertSame('FULFILLED', $request->fresh()->status);
        $this->assertSame(90.0, (float) $this->lot->fresh()->remaining_quantity);
    }

    public function test_over_issue_across_lots_returns_readable_error_without_side_effects(): void
    {
        [$request, $line] = $this->submittedRequest(10);
        $secondLot = $this->makeLot('LOT-OVER-B', 50);

        $response = $this->actingAs($this->warehouseKeeper)
            ->from(route('production.material-requests.fulfill.form', $request))
            ->post(route('production.material-requests.fulfill', $request), [
                'fulfillments' => [
                    ['request_line_id' => $line->id, 'inventory_lot_id' => $this->lot->id, 'quantity' => 10],
                    ['request_line_id' => $line->id, 'inventory_lot_id' => $secondLot->id, 'quantity' => 10],
                ],
            ]);

        $response->assertRedirect(route('production.material-requests.fulfill.form', $request))
            ->assertSessionHas('error', 'الكمية المطلوبة (10) تتجاوز الكمية المتبقية في بند الطلب (0).');
        $this->assertSame(0, \DB::table('material_issues')->count());
        $this->assertSame(100.0, (float) $this->lot->fresh()->remaining_quantity);
        $this->assertSame(0.0, (float) $line->fresh()->issued_quantity);
    }

    /**
     * @return array{0: ProductionMaterialRequest, 1: ProductionMaterialRequestLine}
     */
    private function submittedRequest(float $quantity): array
    {
        $service = app(ProductionMaterialRequestService::class);
        $request = $service->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'material_id' => $this->material->id,
                'requested_quantity' => $quantity,
                'approved_quantity' => $quantity,
                'base_unit_id' => $this->material->base_unit_id,
            ]],
        ]);
        $service->submitRequest($request);

        return [$request->fresh(), $request->lines()->first()];
    }

    private function makeLot(string $code, float $quantity): InventoryLot
    {
        return InventoryLot::create([
            'lot_code' => $code,
            'material_id' => $this->material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->addDay(),
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'base_unit_id' => $this->material->base_unit_id,
            'unit_cost' => 10,
            'status' => 'ACTIVE',
        ]);
    }
}
