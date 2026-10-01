<?php

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Inventory analytics based on the lot ledger (remaining quantity of ACTIVE lots — the same basis the operational
 * dashboard and low-stock alerts use). Values are lot-specific cost (Σ remaining × lot unit cost): an operational
 * valuation, not an accounting inventory valuation.
 */
class InventoryAnalyticsService
{
    public const OPEN_PO_STATUSES = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'];

    public function stockByMaterial(): Builder
    {
        return DB::table('inventory_lots')
            ->where('status', 'ACTIVE')
            ->where('remaining_quantity', '>', 0)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(remaining_quantity) AS stock_qty, SUM(remaining_quantity * unit_cost) AS stock_value, COUNT(*) AS lot_count');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function stock(array $filters = []): ReportDataset
    {
        $incoming = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as pur', 'pur.id', '=', 'pol.purchase_order_id')
            ->whereIn('pur.status', self::OPEN_PO_STATUSES)
            ->groupBy('pol.material_id')
            ->selectRaw('pol.material_id, SUM(CASE WHEN pol.ordered_base_quantity > pol.received_base_quantity THEN pol.ordered_base_quantity - pol.received_base_quantity ELSE 0 END) AS incoming_qty');

        $lastReceipt = DB::table('material_receipt_lines as mrl')
            ->join('material_receipts as mr', 'mr.id', '=', 'mrl.material_receipt_id')
            ->where('mr.status', 'POSTED')
            ->groupBy('mrl.material_id')
            ->selectRaw('mrl.material_id, MAX(mr.receipt_date) AS last_receipt_date');

        $lastIssue = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->where('mi.status', 'POSTED')
            ->groupBy('mil.material_id')
            ->selectRaw('mil.material_id, MAX(mi.issue_date) AS last_issue_date');

        $query = DB::table('materials as m')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'm.base_unit_id')
            ->leftJoinSub($this->stockByMaterial(), 's', 's.material_id', '=', 'm.id')
            ->leftJoinSub($incoming, 'inc', 'inc.material_id', '=', 'm.id')
            ->leftJoinSub($lastReceipt, 'lr', 'lr.material_id', '=', 'm.id')
            ->leftJoinSub($lastIssue, 'li', 'li.material_id', '=', 'm.id')
            ->where('m.is_active', true)
            ->select('m.id', 'm.code', 'm.name_ar', 'm.min_stock_level', 'm.reorder_point')
            ->selectRaw('mc.name_ar AS category, u.name_ar AS unit')
            ->selectRaw('COALESCE(s.stock_qty, 0) AS stock_qty, COALESCE(s.stock_value, 0) AS stock_value, COALESCE(inc.incoming_qty, 0) AS incoming_qty')
            ->selectRaw('lr.last_receipt_date, li.last_issue_date');

        if (! empty($filters['material_category_id'])) {
            $query->where('m.material_category_id', $filters['material_category_id']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('m.name_ar', 'like', "%{$search}%")->orWhere('m.code', 'like', "%{$search}%"));
        }
        if (($filters['state'] ?? '') === 'below_reorder') {
            $query->where('m.reorder_point', '>', 0)->whereRaw('COALESCE(s.stock_qty, 0) <= m.reorder_point');
        } elseif (($filters['state'] ?? '') === 'below_minimum') {
            $query->where('m.min_stock_level', '>', 0)->whereRaw('COALESCE(s.stock_qty, 0) < m.min_stock_level');
        }

        $query->orderByRaw('CASE WHEN m.reorder_point > 0 AND COALESCE(s.stock_qty, 0) <= m.reorder_point THEN 0 ELSE 1 END')->orderBy('m.name_ar');

        $totals = DB::query()->fromSub(clone $query, 't')->selectRaw('COUNT(*) AS materials, COALESCE(SUM(t.stock_value), 0) AS stock_value')->first();

        return new ReportDataset($query, function (object $row) {
            $stock = (float) $row->stock_qty;
            $belowReorder = (float) $row->reorder_point > 0 && $stock <= (float) $row->reorder_point;
            $belowMinimum = (float) $row->min_stock_level > 0 && $stock < (float) $row->min_stock_level;

            return [
                'material' => $row->name_ar,
                'code' => $row->code,
                'category' => $row->category,
                'unit' => $row->unit,
                'stock_qty' => $stock,
                'min_stock_level' => (float) $row->min_stock_level > 0 ? (float) $row->min_stock_level : null,
                'reorder_point' => (float) $row->reorder_point > 0 ? (float) $row->reorder_point : null,
                'incoming_qty' => (float) $row->incoming_qty > 0 ? (float) $row->incoming_qty : null,
                'available_plus_incoming' => $stock + (float) $row->incoming_qty,
                'stock_value' => (float) $row->stock_value,
                'last_receipt_date' => $row->last_receipt_date,
                'last_issue_date' => $row->last_issue_date,
                'state' => $belowMinimum ? ['label' => 'أقل من الحد الأدنى', 'tone' => 'danger'] : ($belowReorder ? ['label' => 'عند حد إعادة الطلب', 'tone' => 'warning'] : ['label' => 'كافٍ', 'tone' => 'success']),
            ];
        }, null, ['materials' => (int) $totals->materials, 'stock_value' => (float) $totals->stock_value]);
    }

    /**
     * Total operational stock value (Σ remaining × lot unit cost of ACTIVE lots).
     */
    public function totalStockValue(): float
    {
        return round((float) DB::table('inventory_lots')->where('status', 'ACTIVE')->where('remaining_quantity', '>', 0)->sum(DB::raw('remaining_quantity * unit_cost')), 2);
    }

    /**
     * Fabric lots with supplier and colour provenance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function fabricLots(array $filters = []): ReportDataset
    {
        $query = DB::table('inventory_lots as lot')
            ->join('materials as m', 'm.id', '=', 'lot.material_id')
            ->join('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'lot.supplier_id')
            ->leftJoin('fabric_colors as fc', 'fc.id', '=', 'lot.fabric_color_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'lot.base_unit_id')
            ->where('mc.code', 'FABRIC')
            ->where('lot.status', 'ACTIVE')
            ->where('lot.remaining_quantity', '>', 0)
            ->select('lot.id', 'lot.lot_code', 'lot.remaining_quantity', 'lot.unit_cost', 'lot.received_date', 'lot.fabric_supplier_color_code')
            ->selectRaw('m.name_ar AS fabric, s.name AS supplier, COALESCE(lot.fabric_color_code, fc.color_code) AS color_code, fc.color_name_ar, u.name_ar AS unit')
            ->orderBy('m.name_ar')->orderBy('s.name')->orderByRaw('COALESCE(lot.fabric_color_code, fc.color_code)')->orderBy('lot.received_date');

        foreach (['material_id' => 'lot.material_id', 'supplier_id' => 'lot.supplier_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['color'])) {
            $color = $filters['color'];
            $query->where(fn ($q) => $q->where('lot.fabric_color_code', 'like', "%{$color}%")->orWhere('fc.color_code', 'like', "%{$color}%")->orWhere('lot.fabric_supplier_color_code', 'like', "%{$color}%"));
        }

        $summary = DB::query()->fromSub(clone $query, 'f')
            ->groupBy('f.fabric', 'f.supplier', 'f.color_code', 'f.unit')
            ->selectRaw('f.fabric, f.supplier, f.color_code, f.unit, SUM(f.remaining_quantity) AS available_qty, COUNT(*) AS lot_count')
            ->orderBy('f.fabric')->limit(200)->get();

        return new ReportDataset($query, fn (object $row) => [
            'fabric' => $row->fabric,
            'supplier' => $row->supplier ?? 'غير محدد',
            'color_code' => $row->color_code,
            'color_name' => $row->color_name_ar,
            'supplier_color_code' => $row->fabric_supplier_color_code,
            'lot' => $row->lot_code,
            'available_qty' => (float) $row->remaining_quantity,
            'unit' => $row->unit,
            'received_date' => $row->received_date,
            'lot_value' => round((float) $row->remaining_quantity * (float) $row->unit_cost, 2),
        ], null, ['summary' => $summary]);
    }

    /**
     * Materials with positive stock and no posted issue within the threshold. Labelled slow-moving, never obsolete.
     */
    public function slowMoving(int $thresholdDays): ReportDataset
    {
        $cutoff = CarbonImmutable::now(config('app.timezone'))->subDays($thresholdDays)->toDateString();

        $lastIssue = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->where('mi.status', 'POSTED')
            ->groupBy('mil.material_id')
            ->selectRaw('mil.material_id, MAX(mi.issue_date) AS last_issue_date');

        $query = DB::table('materials as m')
            ->joinSub($this->stockByMaterial(), 's', 's.material_id', '=', 'm.id')
            ->leftJoinSub($lastIssue, 'li', 'li.material_id', '=', 'm.id')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'm.base_unit_id')
            ->where(fn ($q) => $q->whereNull('li.last_issue_date')->orWhere('li.last_issue_date', '<', $cutoff))
            ->select('m.id', 'm.code', 'm.name_ar')
            ->selectRaw('mc.name_ar AS category, u.name_ar AS unit, s.stock_qty, s.stock_value, li.last_issue_date')
            ->orderByDesc('s.stock_value');

        $today = CarbonImmutable::now(config('app.timezone'));

        return new ReportDataset($query, fn (object $row) => [
            'material' => $row->name_ar,
            'code' => $row->code,
            'category' => $row->category,
            'stock_qty' => (float) $row->stock_qty,
            'unit' => $row->unit,
            'stock_value' => (float) $row->stock_value,
            'last_issue_date' => $row->last_issue_date,
            'days_without_issue' => $row->last_issue_date ? (int) CarbonImmutable::parse($row->last_issue_date)->diffInDays($today) : null,
        ]);
    }

    /**
     * Shortages: materials below minimum / reorder and open production requirements not covered by stock.
     * Remaining requirement per order/material = max(0, planned − net issued) for active production orders.
     */
    public function shortages(): ReportDataset
    {
        $consumption = app(ProductionAnalyticsService::class)->consumptionByOrderMaterial();

        $requirements = DB::table('production_material_requirements as pmr')
            ->join('production_orders as po', 'po.id', '=', 'pmr.production_order_id')
            ->leftJoinSub($consumption, 'cons', function ($join) {
                $join->on('cons.production_order_id', '=', 'pmr.production_order_id')->on('cons.material_id', '=', 'pmr.material_id');
            })
            ->whereIn('po.status', ProductionAnalyticsService::ACTIVE_STATUSES)
            ->whereNotNull('pmr.material_id')
            ->groupBy('pmr.production_order_id', 'pmr.material_id', 'cons.consumed_qty')
            ->selectRaw('pmr.production_order_id, pmr.material_id, SUM(pmr.total_planned_quantity) AS planned, COALESCE(cons.consumed_qty, 0) AS consumed');

        $open = DB::query()->fromSub($requirements, 'r')
            ->whereRaw('r.planned > r.consumed')
            ->groupBy('r.material_id')
            ->selectRaw('r.material_id, SUM(r.planned - r.consumed) AS open_requirement, COUNT(DISTINCT r.production_order_id) AS affected_pos');

        $query = DB::table('materials as m')
            ->leftJoinSub($this->stockByMaterial(), 's', 's.material_id', '=', 'm.id')
            ->leftJoinSub($open, 'req', 'req.material_id', '=', 'm.id')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'm.base_unit_id')
            ->where('m.is_active', true)
            ->where(function ($q) {
                $q->whereRaw('COALESCE(req.open_requirement, 0) > COALESCE(s.stock_qty, 0)')
                    ->orWhereRaw('m.min_stock_level > 0 AND COALESCE(s.stock_qty, 0) < m.min_stock_level')
                    ->orWhereRaw('m.reorder_point > 0 AND COALESCE(s.stock_qty, 0) <= m.reorder_point');
            })
            ->select('m.id', 'm.code', 'm.name_ar', 'm.min_stock_level', 'm.reorder_point')
            ->selectRaw('mc.name_ar AS category, mc.code AS category_code, u.name_ar AS unit')
            ->selectRaw('COALESCE(s.stock_qty, 0) AS stock_qty, COALESCE(req.open_requirement, 0) AS open_requirement, COALESCE(req.affected_pos, 0) AS affected_pos')
            ->orderByRaw('COALESCE(req.open_requirement, 0) - COALESCE(s.stock_qty, 0) DESC');

        return new ReportDataset($query, function (object $row) {
            $shortfall = (float) $row->open_requirement - (float) $row->stock_qty;

            return [
                'material' => ['text' => $row->name_ar, 'url' => route('reports.inventory', ['tab' => 'stock', 'search' => $row->code])],
                'code' => $row->code,
                'category' => $row->category,
                'unit' => $row->unit,
                'stock_qty' => (float) $row->stock_qty,
                'min_stock_level' => (float) $row->min_stock_level > 0 ? (float) $row->min_stock_level : null,
                'reorder_point' => (float) $row->reorder_point > 0 ? (float) $row->reorder_point : null,
                'open_requirement' => (float) $row->open_requirement > 0 ? (float) $row->open_requirement : null,
                'production_shortfall' => $shortfall > 0 ? round($shortfall, 3) : null,
                'affected_pos' => (int) $row->affected_pos,
                'is_fabric' => $row->category_code === 'FABRIC',
            ];
        });
    }
}
