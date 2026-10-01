<?php

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Procurement analytics. "Committed / ordered" (purchase orders, by order date) is always kept separate from
 * "actually received" (posted material receipts, by receipt date). Price variance compares the actual receipt cost
 * with the PO agreed price normalised to the material base unit; it has no accounting meaning.
 */
class ProcurementAnalyticsService
{
    public const OPEN_STATUSES = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'];

    public const COMMITTED_STATUSES = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'];

    /**
     * Receipt lines linked to PO lines with base-unit price variance per line.
     */
    public function varianceLines(ReportPeriod $period): Builder
    {
        return DB::table('material_receipt_lines as rl')
            ->join('material_receipts as r', 'r.id', '=', 'rl.material_receipt_id')
            ->join('purchase_order_lines as pol', 'pol.id', '=', 'rl.purchase_order_line_id')
            ->join('purchase_orders as pur', 'pur.id', '=', 'pol.purchase_order_id')
            ->where('r.status', 'POSTED')
            ->whereBetween('r.receipt_date', $period->dateRange())
            ->selectRaw('rl.id, r.receipt_number, r.receipt_date, pur.purchase_order_number, pur.supplier_id, rl.material_id, rl.base_quantity, rl.quantity_received')
            ->selectRaw('rl.unit_cost_purchase, rl.unit_cost_base, pol.unit_price AS po_unit_price, pol.conversion_factor AS po_conversion')
            ->selectRaw('rl.unit_cost_base - (pol.unit_price / CASE WHEN pol.conversion_factor > 0 THEN pol.conversion_factor ELSE 1 END) AS variance_per_base_unit')
            ->selectRaw('(rl.unit_cost_base - (pol.unit_price / CASE WHEN pol.conversion_factor > 0 THEN pol.conversion_factor ELSE 1 END)) * rl.base_quantity AS variance_total');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(ReportPeriod $period): array
    {
        $received = DB::table('material_receipt_lines as rl')
            ->join('material_receipts as r', 'r.id', '=', 'rl.material_receipt_id')
            ->where('r.status', 'POSTED')
            ->whereBetween('r.receipt_date', $period->dateRange())
            ->selectRaw('COALESCE(SUM(rl.total_cost), 0) AS value, COUNT(DISTINCT r.supplier_id) AS suppliers, COUNT(DISTINCT r.id) AS receipts')
            ->first();

        $ordered = DB::table('purchase_orders')
            ->whereIn('status', self::COMMITTED_STATUSES)
            ->whereBetween('order_date', $period->dateRange())
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS value, COUNT(*) AS orders')
            ->first();

        $variance = DB::query()->fromSub($this->varianceLines($period), 'v')->selectRaw('COALESCE(SUM(v.variance_total), 0) AS total')->value('total');

        return [
            'received_value' => (float) $received->value,
            'receipt_count' => (int) $received->receipts,
            'supplier_count' => (int) $received->suppliers,
            'ordered_value' => (float) $ordered->value,
            'ordered_count' => (int) $ordered->orders,
            'open_po_value' => $this->openPoValue(),
            'late_po_count' => $this->latePoQuery()->count(),
            'price_variance' => round((float) $variance, 2),
        ];
    }

    /**
     * Value still to be received on open purchase orders (remaining purchase quantity × agreed price).
     */
    public function openPoValue(): float
    {
        return round((float) DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as pur', 'pur.id', '=', 'pol.purchase_order_id')
            ->whereIn('pur.status', self::OPEN_STATUSES)
            ->sum(DB::raw('CASE WHEN pol.ordered_base_quantity > pol.received_base_quantity THEN (pol.ordered_base_quantity - pol.received_base_quantity) / CASE WHEN pol.conversion_factor > 0 THEN pol.conversion_factor ELSE 1 END * pol.unit_price ELSE 0 END')), 2);
    }

    public function latePoQuery(): Builder
    {
        return DB::table('purchase_orders')
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', CarbonImmutable::now(config('app.timezone'))->toDateString());
    }

    public function priceVariance(ReportPeriod $period): ReportDataset
    {
        $query = DB::query()->fromSub($this->varianceLines($period), 'v')
            ->join('materials as m', 'm.id', '=', 'v.material_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'v.supplier_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'm.base_unit_id')
            ->select('v.*')
            ->selectRaw('m.name_ar AS material, s.name AS supplier, u.name_ar AS unit')
            ->orderByRaw('ABS(v.variance_total) DESC');

        return new ReportDataset($query, fn (object $row) => [
            'receipt' => $row->receipt_number,
            'receipt_date' => $row->receipt_date,
            'purchase_order' => $row->purchase_order_number,
            'supplier' => $row->supplier,
            'material' => ['text' => $row->material, 'url' => route('reports.procurement.price-history', ['material_id' => $row->material_id, 'supplier_id' => $row->supplier_id])],
            'base_quantity' => (float) $row->base_quantity,
            'unit' => $row->unit,
            'po_price_per_base' => round((float) $row->po_unit_price / max((float) $row->po_conversion, 0.000001), 4),
            'actual_price_per_base' => round((float) $row->unit_cost_base, 4),
            'variance_per_base_unit' => round((float) $row->variance_per_base_unit, 4),
            'variance_total' => round((float) $row->variance_total, 2),
            'direction' => (float) $row->variance_total > 0.005
                ? ['label' => 'غير مواتٍ (أعلى من المتفق)', 'tone' => 'danger']
                : ((float) $row->variance_total < -0.005 ? ['label' => 'مواتٍ (أقل من المتفق)', 'tone' => 'success'] : ['label' => 'مطابق', 'tone' => 'secondary']),
            'is_negative' => (float) $row->variance_total > 0.005,
        ]);
    }

    /**
     * Supplier facts (no subjective score). PO metrics use PO order date; receipt value uses receipt date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function suppliers(ReportPeriod $period): Collection
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        $firstLastReceipt = DB::table('material_receipts')
            ->where('status', 'POSTED')->whereNotNull('purchase_order_id')
            ->groupBy('purchase_order_id')
            ->selectRaw('purchase_order_id, MIN(receipt_date) AS first_receipt, MAX(receipt_date) AS last_receipt');

        $pos = DB::table('purchase_orders as pur')
            ->leftJoinSub($firstLastReceipt, 'rr', 'rr.purchase_order_id', '=', 'pur.id')
            ->whereIn('pur.status', self::COMMITTED_STATUSES)
            ->whereBetween('pur.order_date', $period->dateRange())
            ->get(['pur.id', 'pur.supplier_id', 'pur.status', 'pur.order_date', 'pur.expected_delivery_date', 'pur.total_amount', 'rr.first_receipt', 'rr.last_receipt']);

        $receipts = DB::table('material_receipt_lines as rl')
            ->join('material_receipts as r', 'r.id', '=', 'rl.material_receipt_id')
            ->where('r.status', 'POSTED')
            ->whereBetween('r.receipt_date', $period->dateRange())
            ->groupBy('r.supplier_id')
            ->selectRaw('r.supplier_id, SUM(rl.total_cost) AS received_value, COUNT(DISTINCT rl.material_id) AS materials')
            ->get()->keyBy('supplier_id');

        $variance = DB::query()->fromSub($this->varianceLines($period), 'v')->groupBy('v.supplier_id')
            ->selectRaw('v.supplier_id, SUM(v.variance_total) AS variance')->pluck('variance', 'supplier_id');

        $names = DB::table('suppliers')->pluck('name', 'id');
        $supplierIds = $pos->pluck('supplier_id')->merge($receipts->keys())->unique()->filter();

        return $supplierIds->map(function ($supplierId) use ($pos, $receipts, $variance, $names, $today) {
            $supplierPos = $pos->where('supplier_id', $supplierId);
            $completed = $supplierPos->filter(fn ($po) => in_array($po->status, ['RECEIVED', 'CLOSED'], true) && $po->expected_delivery_date && $po->last_receipt);
            $onTime = $completed->filter(fn ($po) => $po->last_receipt <= $po->expected_delivery_date)->count();
            $late = $supplierPos->filter(fn ($po) => $po->expected_delivery_date && (
                ($po->last_receipt && in_array($po->status, ['RECEIVED', 'CLOSED'], true) && $po->last_receipt > $po->expected_delivery_date)
                || (in_array($po->status, self::OPEN_STATUSES, true) && $po->expected_delivery_date < $today)
            ))->count();
            $leadTimes = $supplierPos->filter(fn ($po) => $po->first_receipt)
                ->map(fn ($po) => CarbonImmutable::parse($po->order_date)->diffInDays(CarbonImmutable::parse($po->first_receipt)));

            return [
                'supplier' => $names[$supplierId] ?? '—',
                'po_count' => $supplierPos->count(),
                'ordered_value' => (float) $supplierPos->sum('total_amount'),
                'received_value' => (float) ($receipts[$supplierId]->received_value ?? 0),
                'materials' => (int) ($receipts[$supplierId]->materials ?? 0),
                'avg_lead_days' => $leadTimes->isNotEmpty() ? round($leadTimes->avg(), 1) : null,
                'on_time_pct' => $completed->isNotEmpty() ? round($onTime / $completed->count() * 100, 1) : null,
                'completed_pos' => $completed->count(),
                'late_pos' => $late,
                'partial_pos' => $supplierPos->where('status', 'PARTIALLY_RECEIVED')->count(),
                'price_variance' => isset($variance[$supplierId]) ? round((float) $variance[$supplierId], 2) : null,
            ];
        })->sortByDesc('received_value')->values();
    }

    /**
     * Quote → PO → actual receipt price timeline for one material (optionally one supplier), normalised to base unit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function priceHistory(int $materialId, ?int $supplierId = null): Collection
    {
        $units = DB::table('units_of_measure')->pluck('name_ar', 'id');
        $suppliers = DB::table('suppliers')->pluck('name', 'id');

        $quotes = DB::table('supplier_quotation_lines as ql')
            ->join('supplier_quotations as q', 'q.id', '=', 'ql.supplier_quotation_id')
            ->where('ql.material_id', $materialId)
            ->when($supplierId, fn ($q) => $q->where('q.supplier_id', $supplierId))
            ->get(['q.quotation_date AS date', 'q.supplier_quotation_number AS reference', 'q.supplier_id', 'ql.unit_price', 'ql.purchase_unit_id', 'ql.conversion_factor'])
            ->map(fn ($row) => ['type' => 'عرض سعر مورد', 'tone' => 'secondary'] + (array) $row);

        $orders = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as pur', 'pur.id', '=', 'pol.purchase_order_id')
            ->where('pol.material_id', $materialId)
            ->whereNotIn('pur.status', ['DRAFT', 'CANCELLED'])
            ->when($supplierId, fn ($q) => $q->where('pur.supplier_id', $supplierId))
            ->get(['pur.order_date AS date', 'pur.purchase_order_number AS reference', 'pur.supplier_id', 'pol.unit_price', 'pol.purchase_unit_id', 'pol.conversion_factor'])
            ->map(fn ($row) => ['type' => 'أمر شراء (سعر متفق)', 'tone' => 'primary'] + (array) $row);

        $receipts = DB::table('material_receipt_lines as rl')
            ->join('material_receipts as r', 'r.id', '=', 'rl.material_receipt_id')
            ->where('rl.material_id', $materialId)
            ->where('r.status', 'POSTED')
            ->when($supplierId, fn ($q) => $q->where('r.supplier_id', $supplierId))
            ->get(['r.receipt_date AS date', 'r.receipt_number AS reference', 'r.supplier_id', 'rl.unit_cost_purchase AS unit_price', 'rl.purchase_unit_id', 'rl.conversion_factor'])
            ->map(fn ($row) => ['type' => 'استلام فعلي', 'tone' => 'success'] + (array) $row);

        return $quotes->concat($orders)->concat($receipts)
            ->map(fn (array $row) => [
                'date' => $row['date'],
                'type' => ['label' => $row['type'], 'tone' => $row['tone']],
                'reference' => $row['reference'],
                'supplier' => $suppliers[$row['supplier_id']] ?? '—',
                'unit_price' => (float) $row['unit_price'],
                'purchase_unit' => $units[$row['purchase_unit_id']] ?? null,
                'conversion_factor' => (float) $row['conversion_factor'],
                'base_unit_price' => round((float) $row['unit_price'] / max((float) $row['conversion_factor'], 0.000001), 4),
            ])
            ->sortByDesc('date')->values();
    }
}
