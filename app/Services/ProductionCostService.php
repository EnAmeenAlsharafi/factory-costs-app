<?php

namespace App\Services;

use App\Models\MaterialIssueLine;
use App\Models\MaterialReturnLine;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteRecord;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductionCostService
{
    /**
     * Set-based form of calculateOrderMaterialCost() for reporting: one row per production order with
     * issued, returned, actual net (issued − returned), rework-issued subset, waste subset and planned/reference cost.
     *
     * Same definitions as the per-order calculation (verified by the Stage 14 reconciliation tests):
     *  - actual cost = POSTED issue line cost − POSTED return line cost (waste/rework are already inside it);
     *  - rework cost = POSTED issue lines requested for REWORK/REMANUFACTURE (analytical subset);
     *  - waste cost  = all waste records of the order (analytical subset, never added on top);
     *  - planned cost = Σ requirement planned quantity × material weighted average cost of remaining lots.
     */
    public function costSummaryQuery(): Builder
    {
        $issued = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->where('mi.status', 'POSTED')
            ->whereNotNull('mi.production_order_id')
            ->groupBy('mi.production_order_id')
            ->selectRaw("mi.production_order_id, SUM(mil.total_cost) AS issued_cost, SUM(CASE WHEN mil.request_reason IN ('REWORK', 'REMANUFACTURE') THEN mil.total_cost ELSE 0 END) AS rework_cost");

        $returned = DB::table('material_return_lines as mrl')
            ->join('material_returns as mr', 'mr.id', '=', 'mrl.material_return_id')
            ->where('mr.status', 'POSTED')
            ->whereNotNull('mr.production_order_id')
            ->groupBy('mr.production_order_id')
            ->selectRaw('mr.production_order_id, SUM(mrl.total_cost) AS returned_cost');

        $waste = DB::table('production_waste_records')
            ->groupBy('production_order_id')
            ->selectRaw('production_order_id, SUM(total_cost) AS waste_cost');

        $planned = DB::table('production_material_requirements as pmr')
            ->leftJoinSub($this->referenceUnitCostQuery(), 'ref', 'ref.material_id', '=', 'pmr.material_id')
            ->groupBy('pmr.production_order_id')
            ->selectRaw('pmr.production_order_id, SUM(pmr.total_planned_quantity * COALESCE(ref.reference_unit_cost, 0)) AS planned_cost');

        return DB::table('production_orders as cost_po')
            ->leftJoinSub($issued, 'ci', 'ci.production_order_id', '=', 'cost_po.id')
            ->leftJoinSub($returned, 'cr', 'cr.production_order_id', '=', 'cost_po.id')
            ->leftJoinSub($waste, 'cw', 'cw.production_order_id', '=', 'cost_po.id')
            ->leftJoinSub($planned, 'cp', 'cp.production_order_id', '=', 'cost_po.id')
            ->selectRaw('cost_po.id AS production_order_id')
            ->selectRaw('COALESCE(ci.issued_cost, 0) AS issued_cost')
            ->selectRaw('COALESCE(cr.returned_cost, 0) AS returned_cost')
            ->selectRaw('COALESCE(ci.issued_cost, 0) - COALESCE(cr.returned_cost, 0) AS actual_cost')
            ->selectRaw('COALESCE(ci.rework_cost, 0) AS rework_cost')
            ->selectRaw('COALESCE(cw.waste_cost, 0) AS waste_cost')
            ->selectRaw('COALESCE(cp.planned_cost, 0) AS planned_cost');
    }

    /**
     * Reference (planned/standard) unit cost per material: weighted average cost of lots with remaining quantity —
     * the same basis as Material::average_unit_cost used by calculateOrderMaterialCost().
     */
    public function referenceUnitCostQuery(): Builder
    {
        return DB::table('inventory_lots')
            ->where('remaining_quantity', '>', 0)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(remaining_quantity * unit_cost) / SUM(remaining_quantity) AS reference_unit_cost');
    }

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
