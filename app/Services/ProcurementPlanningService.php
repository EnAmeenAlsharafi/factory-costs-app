<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;

class ProcurementPlanningService
{
    /**
     * Get procurement planning matrix for all raw materials.
     */
    public function getPlanningMatrix(?int $categoryId = null, bool $onlyDeficit = false): array
    {
        $query = Material::with(['category', 'baseUnit']);

        if ($categoryId) {
            $query->where('material_category_id', $categoryId);
        }

        $materials = $query->get();
        $results = [];

        foreach ($materials as $material) {
            // 1. Current Physical Raw Stock Balance
            $currentStock = (float) InventoryLot::where('material_id', $material->id)->where('status', 'ACTIVE')->sum('remaining_quantity');

            // 2. Open Purchase Request Base Quantity (APPROVED or PARTIALLY_ORDERED)
            $openPrQty = (float) PurchaseRequestLine::where('material_id', $material->id)
                ->whereHas('purchaseRequest', fn ($q) => $q->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED']))
                ->sum('requested_quantity');

            // 3. Open Purchase Order Incoming Base Quantity (APPROVED, SENT, PARTIALLY_RECEIVED)
            $openPoIncomingBase = (float) PurchaseOrderLine::where('material_id', $material->id)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED']))
                ->get()
                ->sum(fn ($line) => $line->remaining_base_quantity);

            $minStock = (float) ($material->min_stock_level ?? 0);
            $reorderLevel = (float) ($material->reorder_point ?? 0);

            $effectiveLevel = $currentStock + $openPoIncomingBase;
            $status = 'OK';
            if ($currentStock <= $minStock && $minStock > 0) {
                $status = 'BELOW_MINIMUM';
            } elseif ($currentStock <= $reorderLevel && $reorderLevel > 0) {
                $status = 'REORDER';
            }

            if ($onlyDeficit && $status === 'OK') {
                continue;
            }

            $suggestedReorderQty = max(0.0, round($reorderLevel - $currentStock, 4));

            $results[] = [
                'material_id' => $material->id,
                'material_code' => $material->code,
                'material_name' => $material->name_ar,
                'category_name' => $material->category?->name_ar,
                'unit_name' => $material->baseUnit?->name_ar,
                'current_stock' => $currentStock,
                'min_stock_level' => $minStock,
                'reorder_level' => $reorderLevel,
                'open_pr_quantity' => $openPrQty,
                'open_po_incoming_quantity' => $openPoIncomingBase,
                'suggested_reorder_quantity' => $suggestedReorderQty,
                'status' => $status,
            ];
        }

        return $results;
    }
}
