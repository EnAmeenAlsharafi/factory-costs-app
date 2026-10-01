<?php

namespace App\Domain\Reports;

use App\Models\CustomerOrder;
use App\Services\ProductionCostService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operational contribution = historical customer-order commercial value − actual production material cost
 * (Stage 10 cost: posted issues − posted usable returns). NOT net/accounting profit: labour, overhead, delivery,
 * VAT, fees and financing are not included. Contribution is only presented as final once production is complete.
 */
class ProfitabilityReportingService
{
    public const STATUSES = [
        'NOT_STARTED' => ['label' => 'لم يبدأ الإنتاج', 'tone' => 'secondary'],
        'IN_PROGRESS' => ['label' => 'قيد الإنتاج', 'tone' => 'info'],
        'PROVISIONAL' => ['label' => 'تكلفة غير مكتملة', 'tone' => 'warning'],
        'FINAL_OPERATIONAL' => ['label' => 'نهائية تشغيلياً', 'tone' => 'success'],
        // Data completeness: production is complete but no posted material cost exists — contribution cannot be trusted.
        'MISSING_COST' => ['label' => 'تكلفة مفقودة', 'tone' => 'danger'],
    ];

    public const CUSTOM_DESIGN_KEY = 0;

    public function __construct(protected ProductionCostService $costService) {}

    /**
     * Production rollup keyed by customer order or order line: cost components, production progress and
     * whether any material document is still unposted (which keeps actual cost provisional).
     */
    public function productionRollup(string $key): Builder
    {
        $pendingDocs = DB::table('production_orders as pd_po')
            ->selectRaw('pd_po.id AS production_order_id')
            ->selectRaw("(SELECT COUNT(*) FROM material_issues WHERE material_issues.production_order_id = pd_po.id AND material_issues.status = 'DRAFT') + (SELECT COUNT(*) FROM material_returns WHERE material_returns.production_order_id = pd_po.id AND material_returns.status = 'DRAFT') AS pending_docs");

        return DB::table('production_orders as rp')
            ->joinSub($this->costService->costSummaryQuery(), 'rc', 'rc.production_order_id', '=', 'rp.id')
            ->joinSub($pendingDocs, 'rd', 'rd.production_order_id', '=', 'rp.id')
            ->whereNotNull("rp.{$key}")
            ->groupBy("rp.{$key}")
            ->selectRaw("rp.{$key} AS rollup_key")
            ->selectRaw('SUM(rc.issued_cost) AS issued_cost')
            ->selectRaw('SUM(rc.returned_cost) AS returned_cost')
            ->selectRaw('SUM(rc.actual_cost) AS actual_cost')
            ->selectRaw('SUM(rc.rework_cost) AS rework_cost')
            ->selectRaw('SUM(rc.waste_cost) AS waste_cost')
            ->selectRaw('SUM(rc.planned_cost) AS planned_cost')
            ->selectRaw("SUM(CASE WHEN rp.status <> 'CANCELLED' THEN 1 ELSE 0 END) AS active_po_count")
            ->selectRaw("SUM(CASE WHEN rp.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_po_count")
            ->selectRaw("SUM(CASE WHEN rp.status NOT IN ('DRAFT', 'CANCELLED') THEN 1 ELSE 0 END) AS started_po_count")
            ->selectRaw("SUM(CASE WHEN rp.status <> 'CANCELLED' THEN rp.released_quantity ELSE 0 END) AS released_qty")
            ->selectRaw("SUM(CASE WHEN rp.status <> 'CANCELLED' THEN rp.completed_quantity ELSE 0 END) AS completed_qty")
            ->selectRaw("SUM(CASE WHEN rp.status <> 'CANCELLED' THEN rd.pending_docs ELSE 0 END) AS pending_docs");
    }

    /**
     * SQL for the profitability status given a rollup alias and the ordered-quantity expression.
     */
    public static function statusSql(string $rollup, string $orderedQty): string
    {
        $complete = "COALESCE({$rollup}.active_po_count, 0) > 0
                AND {$rollup}.completed_po_count = {$rollup}.active_po_count
                AND {$rollup}.released_qty >= {$orderedQty}
                AND COALESCE({$rollup}.pending_docs, 0) = 0";

        return "CASE
            WHEN {$complete} AND COALESCE({$rollup}.issued_cost, 0) <= 0 THEN 'MISSING_COST'
            WHEN {$complete} THEN 'FINAL_OPERATIONAL'
            WHEN COALESCE({$rollup}.issued_cost, 0) > 0 THEN 'PROVISIONAL'
            WHEN COALESCE({$rollup}.started_po_count, 0) > 0 THEN 'IN_PROGRESS'
            ELSE 'NOT_STARTED' END";
    }

    /**
     * Confirmed payment allocations per order (the Stage 13 basis of confirmed_paid_amount).
     */
    public function confirmedPaidByOrder(): Builder
    {
        return DB::table('customer_payment_allocations as cpa')
            ->join('customer_payments as cpp', 'cpp.id', '=', 'cpa.customer_payment_id')
            ->where('cpp.status', 'CONFIRMED')
            ->groupBy('cpa.customer_order_id')
            ->selectRaw('cpa.customer_order_id, SUM(cpa.allocated_amount) AS confirmed_paid');
    }

    /**
     * One row per customer order (commercial value = historical order total_amount).
     *
     * @param  array{customer_id?: mixed, sales_channel_id?: mixed, customer_type_id?: mixed, status?: mixed, include_cancelled?: mixed}  $filters
     */
    public function orderBase(ReportPeriod $period, array $filters = []): Builder
    {
        $orderedQty = DB::table('customer_order_lines')
            ->groupBy('customer_order_id')
            ->selectRaw('customer_order_id, SUM(quantity) AS ordered_qty');

        $deliveries = DB::table('delivery_order_lines as dol')
            ->join('delivery_orders as dlo', 'dlo.id', '=', 'dol.delivery_order_id')
            ->groupBy('dlo.customer_order_id')
            ->selectRaw('dlo.customer_order_id')
            ->selectRaw("SUM(CASE WHEN dlo.status IN ('DELIVERED', 'INSTALLATION_COMPLETED') THEN dol.quantity ELSE 0 END) AS delivered_qty")
            ->selectRaw("SUM(CASE WHEN dlo.status IN ('OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED') THEN dol.quantity ELSE 0 END) AS dispatched_qty");

        $query = DB::table('customer_orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'o.sales_channel_id')
            ->leftJoinSub($orderedQty, 'oq', 'oq.customer_order_id', '=', 'o.id')
            ->leftJoinSub($this->productionRollup('customer_order_id'), 'pr', 'pr.rollup_key', '=', 'o.id')
            ->leftJoinSub($deliveries, 'dv', 'dv.customer_order_id', '=', 'o.id')
            ->whereBetween('o.order_date', $period->dateRange())
            ->select('o.id', 'o.order_number', 'o.order_date', 'o.status as order_status', 'o.customer_id', 'o.sales_channel_id', 'o.total_amount')
            ->selectRaw('c.name AS customer_name, c.customer_type_id, sc.name_ar AS channel_name, sc.code AS channel_code')
            ->selectRaw('COALESCE(oq.ordered_qty, 0) AS ordered_qty')
            ->selectRaw('COALESCE(pr.actual_cost, 0) AS actual_cost, COALESCE(pr.issued_cost, 0) AS issued_cost, COALESCE(pr.rework_cost, 0) AS rework_cost')
            ->selectRaw('COALESCE(pr.waste_cost, 0) AS waste_cost, COALESCE(pr.planned_cost, 0) AS planned_cost')
            ->selectRaw('COALESCE(pr.released_qty, 0) AS released_qty, COALESCE(pr.completed_qty, 0) AS completed_qty, COALESCE(pr.active_po_count, 0) AS active_po_count')
            ->selectRaw('COALESCE(dv.delivered_qty, 0) AS delivered_qty, COALESCE(dv.dispatched_qty, 0) AS dispatched_qty')
            ->selectRaw(self::statusSql('pr', 'COALESCE(oq.ordered_qty, 0)').' AS profitability_status');

        if (empty($filters['include_cancelled'])) {
            $query->whereNotIn('o.status', ['CANCELLED', 'REJECTED']);
        }
        foreach (['customer_id' => 'o.customer_id', 'sales_channel_id' => 'o.sales_channel_id', 'customer_type_id' => 'c.customer_type_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function orders(ReportPeriod $period, array $filters = [], string $sort = 'date'): ReportDataset
    {
        $query = DB::query()->fromSub($this->orderBase($period, $filters), 'r');

        if (! empty($filters['status']) && array_key_exists($filters['status'], self::STATUSES)) {
            $query->where('r.profitability_status', $filters['status']);
        }

        $totals = $this->totalsFor(clone $query);

        match ($sort) {
            'value' => $query->orderByDesc('r.total_amount'),
            'cost' => $query->orderByDesc('r.actual_cost'),
            'contribution' => $query->orderByRaw("CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN 0 ELSE 1 END")->orderByRaw('r.total_amount - r.actual_cost DESC'),
            'contribution_asc' => $query->orderByRaw("CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN 0 ELSE 1 END")->orderByRaw('r.total_amount - r.actual_cost ASC'),
            default => $query->orderByDesc('r.order_date')->orderByDesc('r.id'),
        };

        return new ReportDataset(
            $query,
            fn (object $row) => $this->mapOrderRow($row),
            fn (Collection $rows) => $this->withPaymentStatus($rows),
            $totals,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function mapOrderRow(object $row): array
    {
        $status = $row->profitability_status;
        $hasCost = in_array($status, ['PROVISIONAL', 'FINAL_OPERATIONAL'], true);
        $isFinal = $status === 'FINAL_OPERATIONAL';
        $contribution = $isFinal ? round((float) $row->total_amount - (float) $row->actual_cost, 2) : null;

        return [
            'id' => $row->id,
            'order' => ['text' => $row->order_number, 'url' => route('reports.profitability.orders.show', $row->id)],
            'customer' => $row->customer_name,
            'channel' => $row->channel_name,
            'order_date' => $row->order_date,
            'commercial_value' => (float) $row->total_amount,
            'actual_cost' => $hasCost ? (float) $row->actual_cost : null,
            'contribution' => $contribution,
            'contribution_pct' => $isFinal ? ReportFormat::ratio($contribution, $row->total_amount) : null,
            'profitability_status' => self::STATUSES[$status] ?? ['label' => $status, 'tone' => 'secondary'],
            'production_status' => $this->productionLabel($row),
            'delivery_status' => $this->deliveryLabel($row),
            'payment_status' => null,
            'is_negative' => $contribution !== null && $contribution < 0,
            'order_status' => $row->order_status,
        ];
    }

    /**
     * Payment status reuses the authoritative Stage 13 accessor, once per page/chunk (never for the full history).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function withPaymentStatus(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $orders = CustomerOrder::whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
        $labels = [
            'PAID' => ['label' => 'مسدد', 'tone' => 'success'],
            'OVERDUE' => ['label' => 'متأخر السداد', 'tone' => 'danger'],
            'DEPOSIT_PENDING' => ['label' => 'بانتظار العربون', 'tone' => 'warning'],
            'PARTIALLY_PAID' => ['label' => 'مسدد جزئياً', 'tone' => 'info'],
            'UNPAID' => ['label' => 'غير مسدد', 'tone' => 'secondary'],
            'CANCELLED' => ['label' => 'ملغى', 'tone' => 'secondary'],
        ];

        return $rows->map(function (array $row) use ($orders, $labels) {
            $paymentStatus = $orders->get($row['id'])?->payment_status;
            $row['payment_status'] = $paymentStatus ? ($labels[$paymentStatus] ?? ['label' => $paymentStatus, 'tone' => 'secondary']) : null;

            return $row;
        });
    }

    /**
     * Totals for the whole filtered set. Contribution is aggregated over FINAL_OPERATIONAL orders only.
     *
     * @return array<string, mixed>
     */
    public function totalsFor(Builder $query): array
    {
        $aggregate = DB::query()->fromSub($query, 't')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('COALESCE(SUM(t.total_amount), 0) AS commercial_value')
            ->selectRaw("COALESCE(SUM(CASE WHEN t.profitability_status IN ('PROVISIONAL', 'FINAL_OPERATIONAL') THEN t.actual_cost ELSE 0 END), 0) AS actual_cost")
            ->selectRaw("COALESCE(SUM(CASE WHEN t.profitability_status = 'FINAL_OPERATIONAL' THEN t.total_amount ELSE 0 END), 0) AS final_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN t.profitability_status = 'FINAL_OPERATIONAL' THEN t.actual_cost ELSE 0 END), 0) AS final_cost")
            ->selectRaw("SUM(CASE WHEN t.profitability_status = 'FINAL_OPERATIONAL' THEN 1 ELSE 0 END) AS final_count")
            ->selectRaw("SUM(CASE WHEN t.profitability_status = 'PROVISIONAL' THEN 1 ELSE 0 END) AS provisional_count")
            ->selectRaw("SUM(CASE WHEN t.profitability_status IN ('NOT_STARTED', 'IN_PROGRESS') THEN 1 ELSE 0 END) AS not_costed_count")
            ->selectRaw("SUM(CASE WHEN t.profitability_status = 'MISSING_COST' THEN 1 ELSE 0 END) AS missing_cost_count")
            ->selectRaw('COALESCE(SUM(t.waste_cost), 0) AS waste_cost')
            ->first();

        $finalContribution = round((float) $aggregate->final_value - (float) $aggregate->final_cost, 2);

        return [
            'order_count' => (int) $aggregate->order_count,
            'commercial_value' => (float) $aggregate->commercial_value,
            'average_order_value' => $aggregate->order_count > 0 ? round((float) $aggregate->commercial_value / $aggregate->order_count, 2) : null,
            'actual_cost' => (float) $aggregate->actual_cost,
            'final_value' => (float) $aggregate->final_value,
            'final_cost' => (float) $aggregate->final_cost,
            'final_count' => (int) $aggregate->final_count,
            'provisional_count' => (int) $aggregate->provisional_count,
            'not_costed_count' => (int) $aggregate->not_costed_count,
            'missing_cost_count' => (int) $aggregate->missing_cost_count,
            'contribution' => $aggregate->final_count > 0 ? $finalContribution : null,
            'contribution_pct' => $aggregate->final_count > 0 ? ReportFormat::ratio($finalContribution, $aggregate->final_value) : null,
            'waste_cost' => (float) $aggregate->waste_cost,
        ];
    }

    /**
     * Line-level base for model / configuration analysis. Groups by the internal product model/configuration
     * (customer aliases never fragment the analysis); custom designs form their own group.
     *
     * @param  array<string, mixed>  $filters
     */
    public function lineBase(ReportPeriod $period, array $filters = []): Builder
    {
        $query = DB::table('customer_order_lines as l')
            ->join('customer_orders as o', 'o.id', '=', 'l.customer_order_id')
            ->leftJoinSub($this->productionRollup('customer_order_line_id'), 'lr', 'lr.rollup_key', '=', 'l.id')
            ->whereBetween('o.order_date', $period->dateRange())
            ->whereNotIn('o.status', ['CANCELLED', 'REJECTED'])
            ->selectRaw('l.id AS line_id, l.customer_order_id, o.sales_channel_id, l.quantity, l.line_total')
            ->selectRaw('CASE WHEN l.custom_design = 1 OR l.product_model_id IS NULL THEN '.self::CUSTOM_DESIGN_KEY.' ELSE l.product_model_id END AS model_key')
            ->selectRaw('CASE WHEN l.custom_design = 1 OR l.product_model_id IS NULL THEN NULL ELSE l.product_configuration_id END AS configuration_key')
            ->selectRaw('COALESCE(l.has_storage, 0) AS has_storage')
            ->selectRaw('COALESCE(lr.actual_cost, 0) AS actual_cost, COALESCE(lr.rework_cost, 0) AS rework_cost, COALESCE(lr.waste_cost, 0) AS waste_cost')
            ->selectRaw(self::statusSql('lr', 'l.quantity').' AS profitability_status');

        foreach (['sales_channel_id' => 'o.sales_channel_id', 'customer_id' => 'o.customer_id', 'product_model_id' => 'l.product_model_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['has_storage']) && $filters['has_storage'] !== '') {
            $query->where('l.has_storage', (int) $filters['has_storage']);
        }

        return $query;
    }

    /**
     * Model or configuration profitability with rankings.
     *
     * @param  array<string, mixed>  $filters
     */
    public function products(ReportPeriod $period, array $filters = [], string $groupBy = 'model', string $sort = 'value'): ReportDataset
    {
        $keyColumns = $groupBy === 'configuration' ? ['b.model_key', 'b.configuration_key', 'b.has_storage'] : ['b.model_key'];

        $query = DB::query()->fromSub($this->lineBase($period, $filters), 'b')
            ->groupBy($keyColumns)
            ->select($keyColumns)
            ->selectRaw('COUNT(DISTINCT b.customer_order_id) AS order_count')
            ->selectRaw('SUM(b.quantity) AS quantity')
            ->selectRaw('SUM(b.line_total) AS commercial_value')
            ->selectRaw("SUM(CASE WHEN b.profitability_status IN ('PROVISIONAL', 'FINAL_OPERATIONAL') THEN b.actual_cost ELSE 0 END) AS actual_cost_to_date")
            ->selectRaw("SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.line_total ELSE 0 END) AS final_value")
            ->selectRaw("SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.actual_cost ELSE 0 END) AS final_cost")
            ->selectRaw("SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.quantity ELSE 0 END) AS final_quantity")
            ->selectRaw('SUM(b.rework_cost) AS rework_cost')
            ->selectRaw('SUM(b.waste_cost) AS waste_cost');

        $contributionSql = "(SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.line_total ELSE 0 END) - SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.actual_cost ELSE 0 END))";
        $finalValueSql = "SUM(CASE WHEN b.profitability_status = 'FINAL_OPERATIONAL' THEN b.line_total ELSE 0 END)";

        match ($sort) {
            'quantity' => $query->orderByDesc('quantity'),
            'contribution' => $query->orderByRaw("{$contributionSql} DESC"),
            'contribution_pct' => $query->orderByRaw("CASE WHEN {$finalValueSql} > 0 THEN 0 ELSE 1 END")->orderByRaw("CASE WHEN {$finalValueSql} > 0 THEN {$contributionSql} / {$finalValueSql} ELSE 0 END DESC"),
            'cost' => $query->orderByDesc('actual_cost_to_date'),
            'rework' => $query->orderByDesc('rework_cost'),
            'waste' => $query->orderByDesc('waste_cost'),
            default => $query->orderByDesc('commercial_value'),
        };

        $names = $this->productNames();

        return new ReportDataset($query, function (object $row) use ($groupBy, $names, $period) {
            $finalContribution = $row->final_quantity > 0 ? round((float) $row->final_value - (float) $row->final_cost, 2) : null;
            $isCustom = (int) $row->model_key === self::CUSTOM_DESIGN_KEY;
            $label = $isCustom ? 'تصاميم خاصة (بدون موديل)' : ($names['models'][$row->model_key] ?? 'موديل #'.$row->model_key);

            if ($groupBy === 'configuration' && ! $isCustom) {
                $label .= ' — '.($names['configurations'][$row->configuration_key] ?? 'مقاس غير قياسي').((int) $row->has_storage ? ' — بسحارة' : ' — بدون سحارة');
            }

            $drillParams = array_merge($period->toQuery(), ['group' => 'configuration'], $isCustom ? [] : ['product_model_id' => $row->model_key]);

            return [
                'product' => $groupBy === 'model' && ! $isCustom
                    ? ['text' => $label, 'url' => route('reports.profitability.products', $drillParams)]
                    : $label,
                'order_count' => (int) $row->order_count,
                'quantity' => (float) $row->quantity,
                'commercial_value' => (float) $row->commercial_value,
                'avg_value_per_unit' => $row->quantity > 0 ? round((float) $row->commercial_value / (float) $row->quantity, 2) : null,
                'actual_cost_to_date' => (float) $row->actual_cost_to_date > 0 ? (float) $row->actual_cost_to_date : null,
                'final_quantity' => (float) $row->final_quantity,
                'contribution' => $finalContribution,
                'contribution_pct' => $finalContribution !== null ? ReportFormat::ratio($finalContribution, $row->final_value) : null,
                'avg_cost_per_unit' => $row->final_quantity > 0 ? round((float) $row->final_cost / (float) $row->final_quantity, 2) : null,
                'rework_cost' => (float) $row->rework_cost,
                'waste_cost' => (float) $row->waste_cost,
                'is_negative' => $finalContribution !== null && $finalContribution < 0,
            ];
        });
    }

    /**
     * Sales channel analysis (order level).
     */
    public function channels(ReportPeriod $period): ReportDataset
    {
        $query = DB::query()->fromSub($this->orderBase($period), 'r')
            ->groupBy('r.sales_channel_id', 'r.channel_name', 'r.channel_code')
            ->select('r.sales_channel_id', 'r.channel_name', 'r.channel_code')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('SUM(r.ordered_qty) AS quantity')
            ->selectRaw('SUM(r.total_amount) AS commercial_value')
            ->selectRaw("SUM(CASE WHEN r.profitability_status IN ('PROVISIONAL', 'FINAL_OPERATIONAL') THEN r.actual_cost ELSE 0 END) AS actual_cost_to_date")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN r.total_amount ELSE 0 END) AS final_value")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN r.actual_cost ELSE 0 END) AS final_cost")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN 1 ELSE 0 END) AS final_count")
            ->orderByDesc('commercial_value');

        return new ReportDataset($query, function (object $row) use ($period) {
            $contribution = $row->final_count > 0 ? round((float) $row->final_value - (float) $row->final_cost, 2) : null;

            return [
                'channel' => ['text' => $row->channel_name ?? 'بدون قناة', 'url' => route('reports.profitability.orders', array_merge($period->toQuery(), ['sales_channel_id' => $row->sales_channel_id]))],
                'channel_code' => $row->channel_code,
                'order_count' => (int) $row->order_count,
                'quantity' => (float) $row->quantity,
                'commercial_value' => (float) $row->commercial_value,
                'average_order_value' => $row->order_count > 0 ? round((float) $row->commercial_value / $row->order_count, 2) : null,
                'actual_cost_to_date' => (float) $row->actual_cost_to_date > 0 ? (float) $row->actual_cost_to_date : null,
                'final_count' => (int) $row->final_count,
                'contribution' => $contribution,
                'contribution_pct' => $contribution !== null ? ReportFormat::ratio($contribution, $row->final_value) : null,
                'is_negative' => $contribution !== null && $contribution < 0,
            ];
        });
    }

    /**
     * Customer analysis: commercial, operational contribution and (for receivables users) balances.
     * Outstanding per order uses the Stage 13 definition max(0, total − confirmed allocations).
     *
     * @param  array<string, mixed>  $filters
     */
    public function customers(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $balances = DB::table('customer_orders as bo')
            ->leftJoinSub($this->confirmedPaidByOrder(), 'bp', 'bp.customer_order_id', '=', 'bo.id')
            ->whereNotIn('bo.status', ['CANCELLED', 'REJECTED'])
            ->groupBy('bo.customer_id')
            ->selectRaw('bo.customer_id')
            ->selectRaw('SUM(CASE WHEN bo.total_amount - COALESCE(bp.confirmed_paid, 0) > 0 THEN bo.total_amount - COALESCE(bp.confirmed_paid, 0) ELSE 0 END) AS outstanding')
            ->selectRaw('SUM(CASE WHEN bo.payment_due_date < ? AND bo.total_amount - COALESCE(bp.confirmed_paid, 0) > 0 THEN bo.total_amount - COALESCE(bp.confirmed_paid, 0) ELSE 0 END) AS overdue', [now()->toDateString()])
            ->selectRaw('MAX(bo.order_date) AS last_order_date');

        $query = DB::query()->fromSub($this->orderBase($period, $filters), 'r')
            ->leftJoin('customer_types as ct', 'ct.id', '=', 'r.customer_type_id')
            ->leftJoinSub($balances, 'bal', 'bal.customer_id', '=', 'r.customer_id')
            ->groupBy('r.customer_id', 'r.customer_name', 'ct.name_ar', 'bal.outstanding', 'bal.overdue', 'bal.last_order_date')
            ->select('r.customer_id', 'r.customer_name')
            ->selectRaw('ct.name_ar AS customer_type, bal.outstanding, bal.overdue, bal.last_order_date')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('SUM(r.total_amount) AS commercial_value')
            ->selectRaw("SUM(CASE WHEN r.profitability_status IN ('PROVISIONAL', 'FINAL_OPERATIONAL') THEN r.actual_cost ELSE 0 END) AS actual_cost_to_date")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN r.total_amount ELSE 0 END) AS final_value")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN r.actual_cost ELSE 0 END) AS final_cost")
            ->selectRaw("SUM(CASE WHEN r.profitability_status = 'FINAL_OPERATIONAL' THEN 1 ELSE 0 END) AS final_count")
            ->orderByDesc('commercial_value');

        return new ReportDataset($query, function (object $row) {
            $contribution = $row->final_count > 0 ? round((float) $row->final_value - (float) $row->final_cost, 2) : null;

            return [
                'customer_id' => $row->customer_id,
                'customer' => ['text' => $row->customer_name, 'url' => route('reports.profitability.orders', ['customer_id' => $row->customer_id, 'period' => 'this_year'])],
                'customer_type' => $row->customer_type,
                'order_count' => (int) $row->order_count,
                'commercial_value' => (float) $row->commercial_value,
                'actual_cost_to_date' => (float) $row->actual_cost_to_date > 0 ? (float) $row->actual_cost_to_date : null,
                'contribution' => $contribution,
                'contribution_pct' => $contribution !== null ? ReportFormat::ratio($contribution, $row->final_value) : null,
                'outstanding' => (float) $row->outstanding,
                'overdue' => (float) $row->overdue,
                'credit_exposure' => null,
                'last_order_date' => $row->last_order_date,
                'is_negative' => $contribution !== null && $contribution < 0,
            ];
        });
    }

    /**
     * @return array{models: array<int, string>, configurations: array<int, string>}
     */
    public function productNames(): array
    {
        return [
            'models' => DB::table('product_models')->pluck('name_ar', 'id')->all(),
            'configurations' => DB::table('product_configurations')
                ->selectRaw('id, width_cm, length_cm')
                ->get()
                ->mapWithKeys(fn ($config) => [$config->id => rtrim(rtrim((string) $config->width_cm, '0'), '.').'×'.rtrim(rtrim((string) $config->length_cm, '0'), '.')])
                ->all(),
        ];
    }

    private function productionLabel(object $row): array
    {
        if ((int) $row->active_po_count === 0) {
            return ['label' => 'لم يُطلق', 'tone' => 'secondary'];
        }

        if ((float) $row->completed_qty >= (float) $row->ordered_qty && (float) $row->ordered_qty > 0) {
            return ['label' => 'مكتمل', 'tone' => 'success'];
        }

        return ['label' => 'منجز '.ReportFormat::quantity($row->completed_qty).' من '.ReportFormat::quantity($row->ordered_qty), 'tone' => 'info'];
    }

    private function deliveryLabel(object $row): array
    {
        if ((float) $row->ordered_qty > 0 && (float) $row->delivered_qty >= (float) $row->ordered_qty) {
            return ['label' => 'مُسلّم', 'tone' => 'success'];
        }
        if ((float) $row->delivered_qty > 0) {
            return ['label' => 'مُسلّم جزئياً', 'tone' => 'info'];
        }
        if ((float) $row->dispatched_qty > 0) {
            return ['label' => 'خارج للتوصيل', 'tone' => 'warning'];
        }

        return ['label' => 'لم يُسلّم', 'tone' => 'secondary'];
    }
}
