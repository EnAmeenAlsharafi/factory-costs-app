<?php

namespace App\Domain\Reports;

use App\Models\ProductionOrder;
use App\Services\ProductionCostService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manufacturing analytics built on the Stage 10 cost definition. Every production order is analysed with its own
 * released quantity (split production orders are never re-inflated to the full customer-order line quantity).
 */
class ProductionAnalyticsService
{
    public const ACTIVE_STATUSES = ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED', 'ON_HOLD'];

    public function __construct(protected ProductionCostService $costService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function costVariance(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $scope = $filters['scope'] ?? 'completed';
        $dateColumn = $scope === 'completed' ? 'po.completed_at' : 'po.released_at';

        $query = DB::table('production_orders as po')
            ->joinSub($this->costService->costSummaryQuery(), 'c', 'c.production_order_id', '=', 'po.id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->leftJoin('customer_orders as co', 'co.id', '=', 'po.customer_order_id')
            ->whereBetween($dateColumn, $period->timestampRange())
            ->select('po.id', 'po.production_order_number', 'po.status', 'po.released_quantity', 'po.completed_quantity', 'po.is_custom_design', 'po.custom_design_name')
            ->selectRaw('pm.name_ar AS model_name, co.order_number')
            ->selectRaw('c.planned_cost, c.actual_cost, c.issued_cost, c.rework_cost, c.waste_cost')
            ->selectRaw('c.actual_cost - c.planned_cost AS cost_variance');

        match ($scope) {
            'completed' => $query->where('po.status', 'COMPLETED'),
            'active' => $query->whereIn('po.status', self::ACTIVE_STATUSES),
            default => $query->whereNotIn('po.status', ['DRAFT', 'CANCELLED']),
        };

        if (! empty($filters['product_model_id'])) {
            $query->where('po.product_model_id', $filters['product_model_id']);
        }

        $totals = DB::query()->fromSub(clone $query, 't')
            ->selectRaw('COUNT(*) AS po_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN t.planned_cost ELSE 0 END), 0) AS planned_cost")
            ->selectRaw("COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN t.actual_cost ELSE 0 END), 0) AS actual_cost")
            ->selectRaw("SUM(CASE WHEN t.status = 'COMPLETED' AND t.actual_cost > t.planned_cost THEN 1 ELSE 0 END) AS over_count")
            ->first();

        $sort = $filters['sort'] ?? 'variance';
        $sort === 'variance' ? $query->orderByDesc('cost_variance') : $query->orderByDesc($dateColumn);

        return new ReportDataset($query, function (object $row) {
            $completed = $row->status === 'COMPLETED';
            $variance = $completed ? round((float) $row->cost_variance, 2) : null;

            return [
                'production_order' => ['text' => $row->production_order_number, 'url' => route('reports.production.variance.show', $row->id)],
                'customer_order' => $row->order_number,
                'product' => $row->is_custom_design ? ($row->custom_design_name ?: 'تصميم خاص') : $row->model_name,
                'status' => $completed ? ['label' => 'مكتمل', 'tone' => 'success'] : ['label' => 'قيد التنفيذ — تكلفة حتى تاريخه', 'tone' => 'warning'],
                'released_quantity' => (int) $row->released_quantity,
                'completed_quantity' => (int) $row->completed_quantity,
                'planned_cost' => (float) $row->planned_cost > 0 ? (float) $row->planned_cost : null,
                'actual_cost' => (float) $row->actual_cost,
                'cost_variance' => $variance,
                'variance_pct' => $variance !== null ? ReportFormat::ratio($variance, $row->planned_cost) : null,
                'rework_cost' => (float) $row->rework_cost,
                'waste_cost' => (float) $row->waste_cost,
                'is_negative' => $variance !== null && $variance > 0,
            ];
        }, null, [
            'po_count' => (int) $totals->po_count,
            'planned_cost' => (float) $totals->planned_cost,
            'actual_cost' => (float) $totals->actual_cost,
            'cost_variance' => round((float) $totals->actual_cost - (float) $totals->planned_cost, 2),
            'variance_pct' => ReportFormat::ratio((float) $totals->actual_cost - (float) $totals->planned_cost, $totals->planned_cost),
            'over_count' => (int) $totals->over_count,
        ]);
    }

    /**
     * Material-level variance for one production order (quantities are compared only within the same unit).
     *
     * @return array{rows: Collection<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function materialVariance(ProductionOrder $order): array
    {
        $requirements = DB::table('production_material_requirements')
            ->where('production_order_id', $order->id)
            ->whereNotNull('material_id')
            ->groupBy('material_id', 'unit_id')
            ->selectRaw('material_id, unit_id, SUM(required_quantity_per_unit) AS bom_qty, MAX(waste_percentage) AS waste_pct, SUM(total_planned_quantity) AS planned_qty')
            ->get()->keyBy('material_id');

        $issued = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->where('mi.status', 'POSTED')->where('mi.production_order_id', $order->id)
            ->groupBy('mil.material_id', 'mil.base_unit_id')
            ->selectRaw("mil.material_id, mil.base_unit_id, SUM(mil.issued_quantity) AS qty, SUM(mil.total_cost) AS cost, SUM(CASE WHEN mil.request_reason IN ('REWORK', 'REMANUFACTURE') THEN mil.total_cost ELSE 0 END) AS rework_cost")
            ->get()->keyBy('material_id');

        $returned = DB::table('material_return_lines as mrl')
            ->join('material_returns as mr', 'mr.id', '=', 'mrl.material_return_id')
            ->where('mr.status', 'POSTED')->where('mr.production_order_id', $order->id)
            ->groupBy('mrl.material_id')
            ->selectRaw('mrl.material_id, SUM(mrl.returned_quantity) AS qty, SUM(mrl.total_cost) AS cost')
            ->get()->keyBy('material_id');

        $waste = DB::table('production_waste_records')
            ->where('production_order_id', $order->id)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(quantity) AS qty, SUM(total_cost) AS cost')
            ->get()->keyBy('material_id');

        $materialIds = $requirements->keys()->merge($issued->keys())->merge($returned->keys())->merge($waste->keys())->unique()->values();
        $materials = DB::table('materials as m')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->whereIn('m.id', $materialIds)
            ->selectRaw('m.id, m.code, m.name_ar, m.base_unit_id, mc.name_ar AS category')
            ->get()->keyBy('id');
        $units = DB::table('units_of_measure')->pluck('name_ar', 'id');
        $reference = DB::query()->fromSub($this->costService->referenceUnitCostQuery(), 'r')->whereIn('material_id', $materialIds)->pluck('reference_unit_cost', 'material_id');

        $rows = $materialIds->map(function ($materialId) use ($requirements, $issued, $returned, $waste, $materials, $units, $reference) {
            $req = $requirements->get($materialId);
            $iss = $issued->get($materialId);
            $ret = $returned->get($materialId);
            $wst = $waste->get($materialId);
            $material = $materials->get($materialId);

            $issuedQty = (float) ($iss->qty ?? 0);
            $returnedQty = (float) ($ret->qty ?? 0);
            $consumedQty = $issuedQty - $returnedQty;
            $actualCost = (float) ($iss->cost ?? 0) - (float) ($ret->cost ?? 0);
            $plannedQty = $req ? (float) $req->planned_qty : null;
            $referenceCost = (float) ($reference[$materialId] ?? 0);
            $plannedCost = $plannedQty !== null ? round($plannedQty * $referenceCost, 2) : null;
            $unitId = $req->unit_id ?? $iss->base_unit_id ?? $material?->base_unit_id;
            $sameUnit = ! $req || ! $iss || (int) $req->unit_id === (int) $iss->base_unit_id;

            return [
                'material' => $material ? $material->name_ar : 'مادة #'.$materialId,
                'code' => $material?->code,
                'category' => $material?->category,
                'unit' => $units[$unitId] ?? null,
                'bom_qty' => $req ? (float) $req->bom_qty : null,
                'waste_pct' => $req ? (float) $req->waste_pct : null,
                'planned_qty' => $plannedQty,
                'issued_qty' => $issuedQty,
                'returned_qty' => $returnedQty,
                'consumed_qty' => $consumedQty,
                'waste_qty' => $wst ? (float) $wst->qty : null,
                'variance_qty' => $plannedQty !== null && $sameUnit ? round($consumedQty - $plannedQty, 4) : null,
                'planned_cost' => $plannedCost,
                'actual_cost' => round($actualCost, 2),
                'cost_variance' => $plannedCost !== null ? round($actualCost - $plannedCost, 2) : null,
                'rework_cost' => (float) ($iss->rework_cost ?? 0),
                'waste_cost' => $wst ? (float) $wst->cost : null,
                'unit_mismatch' => ! $sameUnit,
                'outside_bom' => ! $req,
                'is_negative' => $plannedCost !== null && $actualCost - $plannedCost > 0,
            ];
        })->sortByDesc(fn ($row) => abs((float) ($row['cost_variance'] ?? $row['actual_cost'])))->values();

        return [
            'rows' => $rows,
            'summary' => $this->costService->calculateOrderMaterialCost($order),
        ];
    }

    /**
     * BOM accuracy by recipe version and material for completed production orders (read-only; BOMs are never modified).
     */
    public function recipeAccuracy(ReportPeriod $period): ReportDataset
    {
        $consumption = $this->consumptionByOrderMaterial();

        $query = DB::table('production_material_requirements as pmr')
            ->join('production_orders as po', 'po.id', '=', 'pmr.production_order_id')
            ->join('materials as m', 'm.id', '=', 'pmr.material_id')
            ->leftJoin('manufacturing_recipe_versions as rv', 'rv.id', '=', 'pmr.manufacturing_recipe_version_id')
            ->leftJoin('manufacturing_recipes as rc', 'rc.id', '=', 'rv.manufacturing_recipe_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'pmr.unit_id')
            ->leftJoinSub($consumption, 'cons', function ($join) {
                $join->on('cons.production_order_id', '=', 'pmr.production_order_id')->on('cons.material_id', '=', 'pmr.material_id');
            })
            ->where('po.status', 'COMPLETED')
            ->whereBetween('po.completed_at', $period->timestampRange())
            ->groupBy('pmr.manufacturing_recipe_version_id', 'rc.name', 'rv.version_number', 'pmr.material_id', 'm.name_ar', 'm.code', 'u.name_ar')
            ->selectRaw('pmr.manufacturing_recipe_version_id, rc.name AS recipe_name, rv.version_number, m.name_ar AS material_name, m.code AS material_code, u.name_ar AS unit_name')
            ->selectRaw('COUNT(DISTINCT pmr.production_order_id) AS po_count')
            ->selectRaw('SUM(pmr.total_planned_quantity) AS planned_qty')
            ->selectRaw('SUM(COALESCE(cons.consumed_qty, 0)) AS consumed_qty')
            ->orderByRaw('ABS(SUM(COALESCE(cons.consumed_qty, 0)) - SUM(pmr.total_planned_quantity)) DESC');

        return new ReportDataset($query, fn (object $row) => [
            'recipe' => trim(($row->recipe_name ?? 'وصفة غير محددة').' — V'.($row->version_number ?? '?')),
            'material' => $row->material_name,
            'code' => $row->material_code,
            'unit' => $row->unit_name,
            'po_count' => (int) $row->po_count,
            'planned_qty' => (float) $row->planned_qty,
            'consumed_qty' => (float) $row->consumed_qty,
            'variance_qty' => round((float) $row->consumed_qty - (float) $row->planned_qty, 4),
            'variance_pct' => ReportFormat::ratio((float) $row->consumed_qty - (float) $row->planned_qty, $row->planned_qty),
            'is_negative' => (float) $row->consumed_qty > (float) $row->planned_qty,
        ]);
    }

    /**
     * Read-only comparison of two versions of the same recipe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function compareRecipeVersions(int $versionA, int $versionB): Collection
    {
        $items = DB::table('manufacturing_recipe_items as ri')
            ->leftJoin('materials as m', 'm.id', '=', 'ri.material_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'ri.unit_id')
            ->whereIn('ri.recipe_version_id', [$versionA, $versionB])
            ->whereNotNull('ri.material_id')
            ->selectRaw('ri.recipe_version_id, ri.material_id, m.name_ar, u.name_ar AS unit_name, ri.quantity, ri.waste_percentage')
            ->get();
        $reference = DB::query()->fromSub($this->costService->referenceUnitCostQuery(), 'r')->pluck('reference_unit_cost', 'material_id');

        return $items->groupBy('material_id')->map(function (Collection $group, $materialId) use ($versionA, $versionB, $reference) {
            $a = $group->firstWhere('recipe_version_id', $versionA);
            $b = $group->firstWhere('recipe_version_id', $versionB);
            $cost = fn ($item) => $item ? round((float) $item->quantity * (1 + (float) $item->waste_percentage / 100) * (float) ($reference[$materialId] ?? 0), 2) : null;
            $qtyA = $a ? (float) $a->quantity : null;
            $qtyB = $b ? (float) $b->quantity : null;

            return [
                'material' => $group->first()->name_ar,
                'unit' => $group->first()->unit_name,
                'qty_a' => $qtyA,
                'waste_a' => $a ? (float) $a->waste_percentage : null,
                'qty_b' => $qtyB,
                'waste_b' => $b ? (float) $b->waste_percentage : null,
                'change' => ! $a ? 'مادة مضافة' : (! $b ? 'مادة محذوفة' : ($qtyA !== $qtyB || (float) $a->waste_percentage !== (float) $b->waste_percentage ? 'كمية معدلة' : 'بدون تغيير')),
                'planned_cost_a' => $cost($a),
                'planned_cost_b' => $cost($b),
            ];
        })->values();
    }

    /**
     * Operational department metrics — counts and quantities only; no employee scoring or ranking.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function departments(ReportPeriod $period): Collection
    {
        [$from, $to] = $period->timestampRange();

        $open = DB::table('production_order_operations as op')
            ->join('work_centers as wc', 'wc.id', '=', 'op.work_center_id')
            ->join('production_orders as po', 'po.id', '=', 'op.production_order_id')
            ->whereIn('op.status', ['READY', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])
            ->whereIn('po.status', self::ACTIVE_STATUSES)
            ->groupBy('wc.department_id')
            ->selectRaw('wc.department_id, COUNT(*) AS open_ops, SUM(op.required_quantity - op.completed_quantity) AS open_qty')
            ->get()->keyBy('department_id');

        $completed = DB::table('production_order_operations as op')
            ->join('work_centers as wc', 'wc.id', '=', 'op.work_center_id')
            ->where('op.status', 'COMPLETED')
            ->whereBetween('op.completed_at', [$from, $to])
            ->groupBy('wc.department_id')
            ->selectRaw('wc.department_id, COUNT(*) AS completed_ops, SUM(op.completed_quantity) AS processed_qty')
            ->get()->keyBy('department_id');

        $rework = DB::table('production_rework_actions')
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->groupBy('assigned_department_id')
            ->selectRaw('assigned_department_id AS department_id, COUNT(*) AS open_rework, SUM(quantity) AS rework_qty')
            ->get()->keyBy('department_id');

        $detected = DB::table('quality_incidents')->whereBetween('created_at', [$from, $to])
            ->groupBy('detected_department_id')->selectRaw('detected_department_id AS department_id, COUNT(*) AS n')->pluck('n', 'department_id');
        $responsible = DB::table('quality_incidents')->whereBetween('created_at', [$from, $to])->whereNotNull('responsible_department_id')
            ->groupBy('responsible_department_id')->selectRaw('responsible_department_id AS department_id, COUNT(*) AS n')->pluck('n', 'department_id');

        return DB::table('departments')->where('is_production_department', true)->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn ($department) => [
                'department' => $department->name_ar,
                'open_ops' => (int) ($open[$department->id]->open_ops ?? 0),
                'open_qty' => (float) ($open[$department->id]->open_qty ?? 0),
                'completed_ops' => (int) ($completed[$department->id]->completed_ops ?? 0),
                'processed_qty' => (float) ($completed[$department->id]->processed_qty ?? 0),
                'open_rework' => (int) ($rework[$department->id]->open_rework ?? 0),
                'incidents_detected' => (int) ($detected[$department->id] ?? 0),
                'incidents_responsible' => (int) ($responsible[$department->id] ?? 0),
            ]);
    }

    /**
     * Current queue per work center. Waiting since = start (or creation) of the oldest open operation — a reliable
     * timestamp; average wait time is intentionally not computed because queue-entry timestamps are not recorded.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function bottlenecks(): Collection
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return DB::table('production_order_operations as op')
            ->join('work_centers as wc', 'wc.id', '=', 'op.work_center_id')
            ->join('departments as d', 'd.id', '=', 'wc.department_id')
            ->join('production_orders as po', 'po.id', '=', 'op.production_order_id')
            ->whereIn('op.status', ['READY', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])
            ->whereIn('po.status', self::ACTIVE_STATUSES)
            ->groupBy('wc.id', 'wc.name_ar', 'd.name_ar')
            ->selectRaw('wc.name_ar AS work_center, d.name_ar AS department, COUNT(*) AS queue_size')
            ->selectRaw('SUM(op.required_quantity - op.completed_quantity) AS queue_qty')
            ->selectRaw('COUNT(DISTINCT op.production_order_id) AS active_pos')
            ->selectRaw('MIN(COALESCE(op.started_at, op.created_at)) AS oldest_since')
            ->orderByDesc('queue_size')
            ->get()
            ->map(fn ($row) => [
                'work_center' => $row->work_center,
                'department' => $row->department,
                'queue_size' => (int) $row->queue_size,
                'queue_qty' => (float) $row->queue_qty,
                'active_pos' => (int) $row->active_pos,
                'oldest_since' => $row->oldest_since ? CarbonImmutable::parse($row->oldest_since, config('app.timezone'))->format('Y-m-d') : null,
                'oldest_days' => $row->oldest_since ? (int) CarbonImmutable::parse($row->oldest_since, config('app.timezone'))->diffInDays($now) : null,
            ]);
    }

    /**
     * Release → completion lead time (days) for production orders completed in the period, by product model.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, overall: array<string, mixed>}
     */
    public function leadTimes(ReportPeriod $period): array
    {
        $orders = DB::table('production_orders as po')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->where('po.status', 'COMPLETED')
            ->whereNotNull('po.released_at')
            ->whereBetween('po.completed_at', $period->timestampRange())
            ->selectRaw("po.released_at, po.completed_at, COALESCE(pm.name_ar, 'تصاميم خاصة') AS model_name")
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model_name,
                'days' => round(CarbonImmutable::parse($row->released_at)->diffInHours(CarbonImmutable::parse($row->completed_at)) / 24, 1),
            ]);

        $summarise = function (Collection $days) {
            $sorted = $days->sort()->values();
            $count = $sorted->count();

            return [
                'count' => $count,
                'average' => $count ? round($sorted->avg(), 1) : null,
                'median' => $count ? round($count % 2 ? $sorted[intdiv($count, 2)] : ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2, 1) : null,
            ];
        };

        return [
            'rows' => $orders->groupBy('model')->map(fn (Collection $group, $model) => ['model' => $model] + $summarise($group->pluck('days')))->sortByDesc('count')->values(),
            'overall' => $summarise($orders->pluck('days')),
        ];
    }

    /**
     * Actual production material cost by material category (net of usable returns) for orders released in the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function costByCategory(ReportPeriod $period, ?string $groupColumn = null): Collection
    {
        $issued = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->join('production_orders as po', 'po.id', '=', 'mi.production_order_id')
            ->join('materials as m', 'm.id', '=', 'mil.material_id')
            ->where('mi.status', 'POSTED')
            ->whereBetween('po.released_at', $period->timestampRange())
            ->groupBy('m.material_category_id')
            ->selectRaw('m.material_category_id, SUM(mil.total_cost) AS cost')
            ->pluck('cost', 'material_category_id');

        $returned = DB::table('material_return_lines as mrl')
            ->join('material_returns as mr', 'mr.id', '=', 'mrl.material_return_id')
            ->join('production_orders as po', 'po.id', '=', 'mr.production_order_id')
            ->join('materials as m', 'm.id', '=', 'mrl.material_id')
            ->where('mr.status', 'POSTED')
            ->whereBetween('po.released_at', $period->timestampRange())
            ->groupBy('m.material_category_id')
            ->selectRaw('m.material_category_id, SUM(mrl.total_cost) AS cost')
            ->pluck('cost', 'material_category_id');

        $categories = DB::table('material_categories')->pluck('name_ar', 'id');
        $rows = $issued->keys()->merge($returned->keys())->unique()->map(fn ($categoryId) => [
            'category' => $categories[$categoryId] ?? 'غير مصنف',
            'actual_cost' => round((float) ($issued[$categoryId] ?? 0) - (float) ($returned[$categoryId] ?? 0), 2),
        ])->sortByDesc('actual_cost')->values();

        $total = $rows->sum('actual_cost');

        return $rows->map(fn ($row) => $row + ['share_pct' => ReportFormat::ratio($row['actual_cost'], $total)]);
    }

    /**
     * Average actual material cost composition per configuration, from completed production orders only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function productCostBreakdown(ReportPeriod $period): Collection
    {
        $net = fn (string $lines, string $header, string $qtyCost, string $headerFk, int $sign) => DB::table("{$lines} as l")
            ->join("{$header} as h", 'h.id', '=', "l.{$headerFk}")
            ->join('production_orders as po', 'po.id', '=', 'h.production_order_id')
            ->join('materials as m', 'm.id', '=', 'l.material_id')
            ->where('h.status', 'POSTED')
            ->where('po.status', 'COMPLETED')
            ->whereBetween('po.completed_at', $period->timestampRange())
            ->groupBy('po.product_model_id', 'po.product_configuration_id', 'm.material_category_id')
            ->selectRaw("po.product_model_id, po.product_configuration_id, m.material_category_id, SUM(l.{$qtyCost}) * {$sign} AS cost");

        $rows = $net('material_issue_lines', 'material_issues', 'total_cost', 'material_issue_id', 1)->get()
            ->concat($net('material_return_lines', 'material_returns', 'total_cost', 'material_return_id', -1)->get());

        $units = DB::table('production_orders')->where('status', 'COMPLETED')->whereBetween('completed_at', $period->timestampRange())
            ->groupBy('product_model_id', 'product_configuration_id')
            ->selectRaw('product_model_id, product_configuration_id, COUNT(*) AS po_count, SUM(completed_quantity) AS units')
            ->get()->keyBy(fn ($row) => $row->product_model_id.'-'.$row->product_configuration_id);

        $names = app(ProfitabilityReportingService::class)->productNames();
        $categories = DB::table('material_categories')->pluck('name_ar', 'id');

        return $rows->groupBy(fn ($row) => $row->product_model_id.'-'.$row->product_configuration_id)
            ->map(function (Collection $group, string $key) use ($units, $names, $categories) {
                $first = $group->first();
                $total = (float) $group->sum('cost');
                $meta = $units->get($key);
                $composition = $group->groupBy('material_category_id')
                    ->map(fn (Collection $items, $categoryId) => ['category' => $categories[$categoryId] ?? 'غير مصنف', 'cost' => round((float) $items->sum('cost'), 2)])
                    ->sortByDesc('cost')->values()
                    ->map(fn ($item) => $item + ['share_pct' => ReportFormat::ratio($item['cost'], $total)]);

                return [
                    'product' => ($first->product_model_id ? ($names['models'][$first->product_model_id] ?? '—') : 'تصاميم خاصة')
                        .($first->product_configuration_id ? ' — '.($names['configurations'][$first->product_configuration_id] ?? '') : ''),
                    'po_count' => (int) ($meta->po_count ?? 0),
                    'units' => (float) ($meta->units ?? 0),
                    'total_cost' => round($total, 2),
                    'cost_per_unit' => ($meta->units ?? 0) > 0 ? round($total / (float) $meta->units, 2) : null,
                    'composition' => $composition,
                ];
            })->sortByDesc('total_cost')->values();
    }

    /**
     * Fabric consumption and cost by fabric material, lot supplier and colour, with the models consuming it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fabricCost(ReportPeriod $period): Collection
    {
        $side = fn (string $lines, string $header, string $headerFk, string $qty, int $sign) => DB::table("{$lines} as l")
            ->join("{$header} as h", 'h.id', '=', "l.{$headerFk}")
            ->join('production_orders as po', 'po.id', '=', 'h.production_order_id')
            ->join('materials as m', 'm.id', '=', 'l.material_id')
            ->join('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('inventory_lots as lot', 'lot.id', '=', 'l.inventory_lot_id')
            ->where('h.status', 'POSTED')
            ->where('mc.code', 'FABRIC')
            ->whereBetween('po.released_at', $period->timestampRange())
            ->groupBy('l.material_id', 'lot.supplier_id', 'l.fabric_color_code')
            ->selectRaw("l.material_id, lot.supplier_id, l.fabric_color_code AS color_code, SUM(l.{$qty}) * {$sign} AS qty, SUM(l.total_cost) * {$sign} AS cost");

        $rows = $side('material_issue_lines', 'material_issues', 'material_issue_id', 'issued_quantity', 1)->get()
            ->concat($side('material_return_lines', 'material_returns', 'material_return_id', 'returned_quantity', -1)->get());

        $waste = DB::table('production_waste_records as w')
            ->join('materials as m', 'm.id', '=', 'w.material_id')
            ->join('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->where('mc.code', 'FABRIC')
            ->whereBetween('w.occurred_at', $period->timestampRange())
            ->groupBy('w.material_id')
            ->selectRaw('w.material_id, SUM(w.quantity) AS qty, SUM(w.total_cost) AS cost')
            ->get()->keyBy('material_id');

        $models = DB::table('material_issue_lines as l')
            ->join('material_issues as h', 'h.id', '=', 'l.material_issue_id')
            ->join('production_orders as po', 'po.id', '=', 'h.production_order_id')
            ->join('materials as m', 'm.id', '=', 'l.material_id')
            ->join('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->where('h.status', 'POSTED')->where('mc.code', 'FABRIC')
            ->whereBetween('po.released_at', $period->timestampRange())
            ->distinct()
            ->selectRaw("l.material_id, COALESCE(pm.name_ar, 'تصاميم خاصة') AS model_name")
            ->get()->groupBy('material_id')->map(fn (Collection $items) => $items->pluck('model_name')->unique()->implode('، '));

        $materials = DB::table('materials')->pluck('name_ar', 'id');
        $suppliers = DB::table('suppliers')->pluck('name', 'id');

        return $rows->groupBy(fn ($row) => $row->material_id.'|'.$row->supplier_id.'|'.$row->color_code)
            ->map(function (Collection $group) use ($materials, $suppliers, $waste, $models) {
                $first = $group->first();

                return [
                    'material_id' => $first->material_id,
                    'fabric' => $materials[$first->material_id] ?? '—',
                    'supplier' => $suppliers[$first->supplier_id] ?? 'غير محدد',
                    'color_code' => $first->color_code,
                    'consumed_qty' => round((float) $group->sum('qty'), 3),
                    'actual_cost' => round((float) $group->sum('cost'), 2),
                    'material_waste_qty' => isset($waste[$first->material_id]) ? (float) $waste[$first->material_id]->qty : null,
                    'material_waste_cost' => isset($waste[$first->material_id]) ? (float) $waste[$first->material_id]->cost : null,
                    'models' => $models[$first->material_id] ?? '—',
                ];
            })->sortByDesc('actual_cost')->values();
    }

    /**
     * Net consumed quantity per production order and material (posted issues − posted returns).
     */
    public function consumptionByOrderMaterial(): Builder
    {
        $issued = DB::table('material_issue_lines as l')
            ->join('material_issues as h', 'h.id', '=', 'l.material_issue_id')
            ->where('h.status', 'POSTED')->whereNotNull('h.production_order_id')
            ->selectRaw('h.production_order_id, l.material_id, l.issued_quantity AS qty');
        $returned = DB::table('material_return_lines as l')
            ->join('material_returns as h', 'h.id', '=', 'l.material_return_id')
            ->where('h.status', 'POSTED')->whereNotNull('h.production_order_id')
            ->selectRaw('h.production_order_id, l.material_id, 0 - l.returned_quantity AS qty');

        return DB::query()->fromSub($issued->unionAll($returned), 'u')
            ->groupBy('u.production_order_id', 'u.material_id')
            ->selectRaw('u.production_order_id, u.material_id, SUM(u.qty) AS consumed_qty');
    }
}
