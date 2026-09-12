<?php

namespace App\Services;

use App\Models\MaterialIssueLine;
use App\Models\MaterialReturnLine;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteRecord;

class ProductionCostService
{
    /**
     * Calculate comprehensive actual material cost, waste cost subset, and cost variances for a production order.
     */
    public function calculateOrderMaterialCost(ProductionOrder $order): array
    {
        // 1. Total Issued Material Cost (POSTED issues for this production order)
        $issuedLines = MaterialIssueLine::whereHas('issue', function ($q) use ($order) {
            $q->where('production_order_id', $order->id)
                ->where('status', 'POSTED');
        })->get();

        $totalIssuedCost = (float) $issuedLines->sum('total_cost');

        // 2. Total Returned Material Cost (POSTED returns for this production order)
        $returnedLines = MaterialReturnLine::whereHas('return', function ($q) use ($order) {
            $q->where('production_order_id', $order->id)
                ->where('status', 'POSTED');
        })->get();

        $totalReturnedCost = (float) $returnedLines->sum('total_cost');

        // 3. Net Actual Material Consumption Cost
        $actualNetMaterialCost = round($totalIssuedCost - $totalReturnedCost, 4);

        // 4. Waste Analytical Cost Subset (Reported analytically, NOT added on top to avoid double counting)
        $wasteRecords = ProductionWasteRecord::where('production_order_id', $order->id)->get();
        $totalWasteCost = (float) $wasteRecords->sum('total_cost');

        // 5. Rework / Remanufacture Issued Cost
        $reworkLines = $issuedLines->filter(function ($line) {
            return in_array($line->request_reason, ['REWORK', 'REMANUFACTURE'], true);
        });
        $reworkIssuedCost = (float) $reworkLines->sum('total_cost');

        // 6. Planned Cost from BOM Material Requirements (estimated via latest lot or material average cost)
        $plannedBomCost = 0.0;
        foreach ($order->materialRequirements as $req) {
            $unitCost = $req->material ? (float) $req->material->average_unit_cost : 0.0;
            $plannedBomCost += (float) $req->total_planned_quantity * $unitCost;
        }

        $costVariance = round($actualNetMaterialCost - $plannedBomCost, 4);
        $costVariancePercentage = $plannedBomCost > 0 ? round(($costVariance / $plannedBomCost) * 100, 2) : 0.0;

        return [
            'production_order_id' => $order->id,
            'production_order_number' => $order->production_order_number,
            'total_issued_cost' => $totalIssuedCost,
            'total_returned_cost' => $totalReturnedCost,
            'actual_net_material_cost' => $actualNetMaterialCost,
            'total_waste_cost' => $totalWasteCost,
            'rework_issued_cost' => $reworkIssuedCost,
            'planned_bom_cost' => round($plannedBomCost, 4),
            'cost_variance' => $costVariance,
            'cost_variance_percentage' => $costVariancePercentage,
            'is_over_budget' => $costVariance > 0,
        ];
    }
}
