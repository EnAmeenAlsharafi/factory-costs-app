<?php

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Waste, rework and quality analytics. Waste and rework costs are analytical subsets of actual material cost;
 * they are reported here and never added on top of production cost. Detected ≠ responsible department throughout.
 */
class QualityAnalyticsService
{
    public const WASTE_GROUPS = [
        'material' => 'حسب المادة',
        'model' => 'حسب الموديل',
        'department' => 'حسب القسم المسؤول',
        'reason' => 'حسب سبب الهدر',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function wasteRecords(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $query = $this->wasteBase($period, $filters)
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'w.unit_id')
            ->leftJoin('departments as dd', 'dd.id', '=', 'w.detected_department_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'w.responsible_department_id')
            ->select('w.id', 'w.waste_number', 'w.quantity', 'w.total_cost', 'w.occurred_at', 'w.production_order_id')
            ->selectRaw("m.name_ar AS material, mc.name_ar AS category, u.name_ar AS unit, po.production_order_number, COALESCE(pm.name_ar, 'تصاميم خاصة') AS model")
            ->selectRaw('dd.name_ar AS detected_department, rd.name_ar AS responsible_department, wr.name_ar AS reason')
            ->orderByDesc('w.occurred_at');

        return new ReportDataset($query, fn (object $row) => [
            'waste_number' => $row->waste_number,
            'material' => $row->material,
            'category' => $row->category,
            'quantity' => (float) $row->quantity,
            'unit' => $row->unit,
            'waste_cost' => (float) $row->total_cost,
            'production_order' => ['text' => $row->production_order_number, 'url' => route('reports.production.variance.show', $row->production_order_id)],
            'model' => $row->model,
            'detected_department' => $row->detected_department,
            'responsible_department' => $row->responsible_department,
            'reason' => $row->reason,
            'date' => CarbonImmutable::parse($row->occurred_at)->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function wasteGrouped(ReportPeriod $period, string $groupBy, array $filters = []): Collection
    {
        [$keyExpression, $labelExpression] = match ($groupBy) {
            'model' => ['po.product_model_id', "COALESCE(pm.name_ar, 'تصاميم خاصة')"],
            'department' => ['w.responsible_department_id', "COALESCE(rd.name_ar, 'غير محدد')"],
            'reason' => ['w.waste_reason_id', "COALESCE(wr.name_ar, 'غير محدد')"],
            default => ['w.material_id', 'm.name_ar'],
        };

        $rows = $this->wasteBase($period, $filters)
            ->leftJoin('departments as rd', 'rd.id', '=', 'w.responsible_department_id')
            ->groupByRaw("{$keyExpression}, {$labelExpression}".($groupBy === 'material' ? ', w.unit_id' : ''))
            ->selectRaw("{$labelExpression} AS label, COUNT(*) AS records, SUM(w.total_cost) AS waste_cost")
            ->selectRaw($groupBy === 'material' ? 'SUM(w.quantity) AS quantity, w.unit_id' : 'NULL AS quantity, NULL AS unit_id')
            ->orderByDesc('waste_cost')
            ->get();

        $units = DB::table('units_of_measure')->pluck('name_ar', 'id');
        $total = (float) $rows->sum('waste_cost');

        return $rows->map(fn ($row) => [
            'label' => $row->label,
            'records' => (int) $row->records,
            'quantity' => $row->quantity !== null ? (float) $row->quantity : null,
            'unit' => $row->unit_id ? ($units[$row->unit_id] ?? null) : null,
            'waste_cost' => (float) $row->waste_cost,
            'share_pct' => ReportFormat::ratio($row->waste_cost, $total),
        ]);
    }

    /**
     * Rework actions. Extra material cost is the rework-issued material of the production order (Stage 10 subset,
     * already inside actual cost); labour rework cost is not calculated because labour costing does not exist.
     *
     * @param  array<string, mixed>  $filters
     */
    public function rework(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $reworkCost = DB::table('material_issue_lines as mil')
            ->join('material_issues as mi', 'mi.id', '=', 'mil.material_issue_id')
            ->where('mi.status', 'POSTED')
            ->whereIn('mil.request_reason', ['REWORK', 'REMANUFACTURE'])
            ->groupBy('mi.production_order_id')
            ->selectRaw('mi.production_order_id, SUM(mil.total_cost) AS rework_material_cost');

        $query = DB::table('production_rework_actions as ra')
            ->join('production_orders as po', 'po.id', '=', 'ra.production_order_id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->leftJoin('quality_incidents as qi', 'qi.id', '=', 'ra.quality_incident_id')
            ->leftJoin('departments as dd', 'dd.id', '=', 'qi.detected_department_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'qi.responsible_department_id')
            ->leftJoin('departments as ad', 'ad.id', '=', 'ra.assigned_department_id')
            ->leftJoinSub($reworkCost, 'rc', 'rc.production_order_id', '=', 'ra.production_order_id')
            ->whereBetween('ra.created_at', $period->timestampRange())
            ->select('ra.id', 'ra.rework_number', 'ra.quantity', 'ra.status', 'ra.action_type', 'ra.production_order_id')
            ->selectRaw("po.production_order_number, COALESCE(pm.name_ar, 'تصاميم خاصة') AS model, qi.incident_number, qi.description AS reason")
            ->selectRaw('dd.name_ar AS detected_department, rd.name_ar AS responsible_department, ad.name_ar AS assigned_department, rc.rework_material_cost')
            ->orderByDesc('ra.created_at');

        if (! empty($filters['status'])) {
            $filters['status'] === 'open'
                ? $query->whereNotIn('ra.status', ['COMPLETED', 'CANCELLED'])
                : $query->where('ra.status', 'COMPLETED');
        }

        $aggregate = DB::query()->fromSub(clone $query, 't')
            ->selectRaw('COUNT(*) AS rework_count, COALESCE(SUM(t.quantity), 0) AS affected_qty')
            ->first();
        $materialCost = (float) DB::query()->fromSub(
            DB::query()->fromSub(clone $query, 'x')->select('x.production_order_id', 'x.rework_material_cost')->distinct(), 'd'
        )->sum('d.rework_material_cost');

        return new ReportDataset($query, fn (object $row) => [
            'rework_number' => $row->rework_number,
            'production_order' => ['text' => $row->production_order_number, 'url' => route('reports.production.variance.show', $row->production_order_id)],
            'model' => $row->model,
            'quantity' => (int) $row->quantity,
            'reason' => $row->reason,
            'incident' => $row->incident_number,
            'detected_department' => $row->detected_department,
            'responsible_department' => $row->responsible_department,
            'assigned_department' => $row->assigned_department,
            'rework_material_cost' => $row->rework_material_cost !== null ? (float) $row->rework_material_cost : null,
            'status' => in_array($row->status, ['COMPLETED', 'CANCELLED'], true)
                ? ['label' => $row->status === 'COMPLETED' ? 'مكتملة' : 'ملغاة', 'tone' => 'success']
                : ['label' => 'مفتوحة', 'tone' => 'warning'],
        ], null, [
            'rework_count' => (int) $aggregate->rework_count,
            'affected_qty' => (int) $aggregate->affected_qty,
            'rework_material_cost' => round($materialCost, 2),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function qualitySummary(ReportPeriod $period): array
    {
        [$from, $to] = $period->timestampRange();
        $base = fn () => DB::table('quality_incidents as qi')->whereBetween('qi.created_at', [$from, $to]);

        $severityLabels = ['LOW' => 'منخفضة', 'MEDIUM' => 'متوسطة', 'HIGH' => 'عالية', 'CRITICAL' => 'حرجة'];
        $bySeverity = $base()->groupBy('qi.severity')->selectRaw('qi.severity, COUNT(*) AS n, SUM(qi.affected_quantity) AS qty')->get()
            ->map(fn ($row) => ['label' => $severityLabels[$row->severity] ?? $row->severity, 'count' => (int) $row->n, 'quantity' => (int) $row->qty]);
        $byType = $base()->groupBy('qi.incident_type')->selectRaw('qi.incident_type, COUNT(*) AS n, SUM(qi.affected_quantity) AS qty')->orderByDesc('n')->get()
            ->map(fn ($row) => ['label' => $row->incident_type, 'count' => (int) $row->n, 'quantity' => (int) $row->qty]);

        $byDepartment = collect(['detected_department_id' => 'detected', 'responsible_department_id' => 'responsible'])
            ->map(fn ($key, $column) => $base()->groupBy("qi.{$column}")->selectRaw("qi.{$column} AS department_id, COUNT(*) AS n")->pluck('n', 'department_id'));
        $departments = DB::table('departments')->pluck('name_ar', 'id');
        $departmentRows = $byDepartment['detected_department_id']->keys()->merge($byDepartment['responsible_department_id']->keys())->filter()->unique()
            ->map(fn ($id) => [
                'department' => $departments[$id] ?? '—',
                'detected' => (int) ($byDepartment['detected_department_id'][$id] ?? 0),
                'responsible' => (int) ($byDepartment['responsible_department_id'][$id] ?? 0),
            ])->sortByDesc('responsible')->values();

        $counts = $base()
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN qi.status IN ('RESOLVED', 'CANCELLED') THEN 1 ELSE 0 END) AS closed")
            ->selectRaw("SUM(CASE WHEN qi.disposition IN ('REWORK', 'REMANUFACTURE', 'REPAIR') THEN 1 ELSE 0 END) AS rework_required")
            ->selectRaw('COALESCE(SUM(qi.affected_quantity), 0) AS affected')
            ->first();

        $resolutionDays = $base()->whereNotNull('qi.resolved_at')->get(['qi.created_at', 'qi.resolved_at'])
            ->map(fn ($row) => CarbonImmutable::parse($row->created_at)->diffInHours(CarbonImmutable::parse($row->resolved_at)) / 24);

        return [
            'total' => (int) $counts->total,
            'open' => (int) $counts->total - (int) $counts->closed,
            'closed' => (int) $counts->closed,
            'rework_required' => (int) $counts->rework_required,
            'affected' => (int) $counts->affected,
            'avg_resolution_days' => $resolutionDays->isNotEmpty() ? round($resolutionDays->avg(), 1) : null,
            'resolved_sample' => $resolutionDays->count(),
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'by_department' => $departmentRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function wasteBase(ReportPeriod $period, array $filters): Builder
    {
        $query = DB::table('production_waste_records as w')
            ->join('materials as m', 'm.id', '=', 'w.material_id')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.material_category_id')
            ->leftJoin('production_orders as po', 'po.id', '=', 'w.production_order_id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->leftJoin('production_waste_reasons as wr', 'wr.id', '=', 'w.waste_reason_id')
            ->whereBetween('w.occurred_at', $period->timestampRange());

        foreach (['material_id' => 'w.material_id', 'waste_reason_id' => 'w.waste_reason_id', 'department_id' => 'w.responsible_department_id', 'material_category_id' => 'm.material_category_id', 'product_model_id' => 'po.product_model_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        return $query;
    }
}
