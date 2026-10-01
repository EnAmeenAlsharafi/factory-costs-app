<?php

namespace App\Domain\Reports;

use App\Models\CustomerOrder;
use App\Models\User;
use App\Services\OrderPaymentEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Management dashboard, daily summary and operational exceptions. Each section is computed only when the user holds
 * the matching report permission, and every figure is taken from the domain analytics services.
 */
class ManagementReportingService
{
    public function __construct(
        protected ProfitabilityReportingService $profitability,
        protected ProductionAnalyticsService $production,
        protected InventoryAnalyticsService $inventory,
        protected ProcurementAnalyticsService $procurement,
        protected DeliveryAnalyticsService $delivery,
        protected ReceivablesAnalyticsService $receivables,
    ) {}

    public static function canSeeCommercial(User $user): bool
    {
        return $user->can('reports.profitability') || $user->can('reports.sales');
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user, ReportPeriod $period): array
    {
        $sections = [];

        if (self::canSeeCommercial($user)) {
            $current = $this->profitability->totalsFor($this->profitability->orderBase($period));
            $previous = $this->profitability->totalsFor($this->profitability->orderBase($period->previous()));
            $sections['commercial'] = ['current' => $current, 'previous' => $previous, 'show_contribution' => $user->can('reports.profitability')];
        }

        if ($user->can('reports.production')) {
            [$from, $to] = $period->timestampRange();
            $variance = $this->production->costVariance($period, ['scope' => 'completed'])->totals;
            $sections['production'] = [
                'active_orders' => DB::table('production_orders')->whereIn('status', ProductionAnalyticsService::ACTIVE_STATUSES)->count(),
                'completed_quantity' => (int) DB::table('production_orders')->where('status', 'COMPLETED')->whereBetween('completed_at', [$from, $to])->sum('completed_quantity'),
                'completed_orders' => $variance['po_count'],
                'planned_cost' => $variance['planned_cost'],
                'actual_cost' => $variance['actual_cost'],
                'cost_variance' => $variance['cost_variance'],
                'variance_pct' => $variance['variance_pct'],
                'open_incidents' => DB::table('quality_incidents')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])->count(),
                'open_rework_qty' => (int) DB::table('production_rework_actions')->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->sum('quantity'),
                'show_cost' => $user->can('costing.view'),
            ];
        }

        if ($user->can('reports.inventory')) {
            $shortageDataset = $this->inventory->shortages();
            $shortages = $shortageDataset->mapRows($shortageDataset->query->get());
            $sections['inventory'] = [
                'stock_value' => $user->can('costing.view') ? $this->inventory->totalStockValue() : null,
                'below_reorder' => DB::query()->fromSub($this->inventory->stock(['state' => 'below_reorder'])->query, 's')->count(),
                'slow_moving' => DB::query()->fromSub($this->inventory->slowMoving(90)->query, 's')->count(),
                'fabric_shortages' => $shortages->where('is_fabric', true)->filter(fn ($row) => $row['production_shortfall'] !== null)->count(),
                'production_shortages' => $shortages->filter(fn ($row) => $row['production_shortfall'] !== null)->count(),
            ];
        }

        if ($user->can('reports.procurement')) {
            $summary = $this->procurement->summary($period);
            $suppliers = $this->procurement->suppliers($period);
            $completed = $suppliers->sum('completed_pos');
            $sections['procurement'] = $summary + [
                'on_time_pct' => $completed > 0 ? round($suppliers->sum(fn ($s) => ($s['on_time_pct'] ?? 0) * $s['completed_pos'] / 100) / $completed * 100, 1) : null,
                'show_cost' => $user->can('costing.view'),
            ];
        }

        if ($user->can('reports.delivery')) {
            $summary = $this->delivery->deliverySummary($period);
            $sections['fulfillment'] = [
                'fg_ready_qty' => (float) DB::query()->fromSub($this->delivery->finishedGoodsAging()->query, 'f')->sum('f.available_qty'),
                'delivered' => $summary['delivered_in_period'],
                'failed_rescheduled' => $summary['failed_in_period'] + $summary['rescheduled_in_period'],
                'awaiting_installation' => $summary['awaiting_installation'],
            ];
        }

        if ($user->can('reports.receivables')) {
            // Stage 13 summary evaluates every open order; cache briefly so the dashboard stays responsive.
            $summary = Cache::remember('reports.receivables.summary', now()->addMinutes(5), fn () => $this->receivables->summary());
            $sections['receivables'] = [
                'outstanding' => $summary['total_outstanding'],
                'overdue' => $summary['overdue_outstanding'],
                'unallocated' => $summary['unallocated_credit_amount'],
                'credit_holds' => $summary['exceeded_credit_customers_count'],
                'delivery_blocked' => $summary['delivery_blocked_orders_count'],
            ];
        }

        return $sections;
    }

    /**
     * Factual "today" summary (business timezone).
     *
     * @return list<array{label: string, value: string, icon: string}>
     */
    public function today(User $user): array
    {
        $period = ReportPeriod::preset('today');
        [$from, $to] = $period->timestampRange();
        $today = $period->startDate();
        $items = [];

        $orders = DB::table('customer_orders')->whereDate('created_at', $today)->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS value')->first();
        $items[] = ['label' => 'طلبات عملاء أُنشئت اليوم', 'value' => number_format((int) $orders->n).(self::canSeeCommercial($user) ? ' — '.ReportFormat::money($orders->value) : ''), 'icon' => 'fa-cart-plus'];
        $items[] = ['label' => 'أوامر إنتاج اكتملت اليوم', 'value' => number_format(DB::table('production_orders')->where('status', 'COMPLETED')->whereBetween('completed_at', [$from, $to])->count()), 'icon' => 'fa-industry'];
        $items[] = ['label' => 'منتجات جاهزة استُلمت اليوم', 'value' => ReportFormat::quantity(DB::table('finished_goods_movements')->where('movement_type', 'PRODUCTION_RECEIPT')->whereBetween('occurred_at', [$from, $to])->sum('quantity')), 'icon' => 'fa-boxes-stacked'];
        $items[] = ['label' => 'توصيلات اكتملت اليوم', 'value' => number_format(DB::table('delivery_events')->where('event_type', 'DELIVERED')->whereBetween('occurred_at', [$from, $to])->count()), 'icon' => 'fa-truck-fast'];
        $items[] = ['label' => 'سندات استلام مواد رُحّلت اليوم', 'value' => number_format(DB::table('material_receipts')->where('status', 'POSTED')->whereBetween('posted_at', [$from, $to])->count()), 'icon' => 'fa-truck-ramp-box'];

        if ($user->can('reports.receivables')) {
            $payments = DB::table('customer_payments')->where('status', 'CONFIRMED')->whereBetween('confirmed_at', [$from, $to])
                ->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount), 0) AS value')->first();
            $items[] = ['label' => 'دفعات عملاء أُكدت اليوم', 'value' => number_format((int) $payments->n).' — '.ReportFormat::money($payments->value), 'icon' => 'fa-money-check-dollar'];
        }

        return $items;
    }

    /**
     * Trend rows: current vs previous equivalent period. Change is omitted when the previous value is zero.
     *
     * @param  array<string, mixed>  $commercial
     * @return list<array<string, mixed>>
     */
    public function trends(array $commercial, User $user, ReportPeriod $period): array
    {
        $current = $commercial['current'];
        $previous = $commercial['previous'];
        $deliveries = fn (ReportPeriod $p) => DB::table('delivery_events')->where('event_type', 'DELIVERED')->whereBetween('occurred_at', $p->timestampRange())->count();

        $rows = [
            ['label' => 'القيمة التجارية للطلبات', 'type' => ReportColumn::MONEY, 'current' => $current['commercial_value'], 'previous' => $previous['commercial_value']],
            ['label' => 'عدد الطلبات', 'type' => ReportColumn::INT, 'current' => $current['order_count'], 'previous' => $previous['order_count']],
        ];

        if ($commercial['show_contribution']) {
            $rows[] = ['label' => 'المساهمة التشغيلية (طلبات مكتملة)', 'type' => ReportColumn::MONEY, 'current' => $current['contribution'], 'previous' => $previous['contribution']];
            $rows[] = ['label' => 'تكلفة المواد الفعلية', 'type' => ReportColumn::MONEY, 'current' => $current['actual_cost'], 'previous' => $previous['actual_cost']];
            $rows[] = ['label' => 'تكلفة الهدر (تحليلية)', 'type' => ReportColumn::MONEY, 'current' => $current['waste_cost'], 'previous' => $previous['waste_cost']];
        }
        if ($user->can('reports.delivery')) {
            $rows[] = ['label' => 'التوصيلات المكتملة', 'type' => ReportColumn::INT, 'current' => $deliveries($period), 'previous' => $deliveries($period->previous())];
        }

        return array_map(function (array $row) {
            $comparable = $row['current'] !== null && $row['previous'] !== null && abs((float) $row['previous']) > 0.00001;
            $row['change_pct'] = $comparable ? round(((float) $row['current'] - (float) $row['previous']) / abs((float) $row['previous']) * 100, 1) : null;

            return $row;
        }, $rows);
    }

    /**
     * Operational blockers grouped by type. Severity is shown only where the source record defines one.
     *
     * @return Collection<string, array{title: string, area: string, count: int, items: Collection<int, array<string, mixed>>}>
     */
    public function exceptions(User $user): Collection
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $age = fn ($date) => $date ? (int) CarbonImmutable::parse($date, config('app.timezone'))->diffInDays($now) : null;
        $groups = collect();
        $limit = 25;

        if ($user->can('reports.production') || $user->can('orders.review_production')) {
            $query = DB::table('customer_orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')->where('o.status', 'PENDING_PRODUCTION_REVIEW');
            $groups['review'] = [
                'title' => 'طلبات بانتظار مراجعة الإنتاج', 'area' => 'الإنتاج', 'count' => (clone $query)->count(),
                'items' => $query->orderBy('o.created_at')->limit($limit)->get(['o.id', 'o.order_number', 'o.created_at', 'c.name'])
                    ->map(fn ($row) => ['reference' => $row->order_number, 'context' => $row->name, 'url' => route('sales.orders.review', $row->id), 'age' => $age($row->created_at), 'severity' => null]),
            ];
        }

        if ($user->can('reports.receivables')) {
            $orders = CustomerOrder::with('customer')->whereIn('status', ['PENDING_PRODUCTION_REVIEW', 'APPROVED_FOR_PRODUCTION'])->latest('id')->limit(200)->get();
            $eligibility = app(OrderPaymentEligibilityService::class);
            $blocked = $orders->map(fn (CustomerOrder $order) => ['order' => $order, 'check' => $eligibility->checkProductionEligibility($order)])
                ->reject(fn ($item) => $item['check']['eligible']);
            foreach (['payment' => ['PAYMENT_REQUIRED', 'DEPOSIT_REQUIRED', 'BLOCKED'], 'credit' => ['CREDIT_HOLD']] as $key => $statuses) {
                $items = $blocked->filter(fn ($item) => $key === 'credit' ? in_array($item['check']['status'] ?? null, $statuses, true) : ! in_array($item['check']['status'] ?? null, ['CREDIT_HOLD'], true));
                $groups[$key.'_blocked'] = [
                    'title' => $key === 'credit' ? 'طلبات موقوفة ائتمانياً' : 'طلبات موقوفة بانتظار السداد',
                    'area' => 'التحصيل', 'count' => $items->count(),
                    'items' => $items->take($limit)->map(fn ($item) => ['reference' => $item['order']->order_number, 'context' => $item['order']->customer?->name, 'url' => route('sales.orders.show', $item['order']), 'age' => $age($item['order']->created_at), 'severity' => null])->values(),
                ];
            }

            $overdue = $this->receivables->agingBuckets()->except('current');
            $overdueCustomers = $overdue->flatMap(fn ($bucket, $key) => collect($bucket['customer_ids'])->mapWithKeys(fn ($id) => [$id => $bucket['label']]));
            $names = DB::table('customers')->whereIn('id', $overdueCustomers->keys())->pluck('name', 'id');
            $groups['overdue'] = [
                'title' => 'ذمم متأخرة السداد', 'area' => 'التحصيل', 'count' => $overdueCustomers->count(),
                'items' => $overdueCustomers->take($limit)->map(fn ($bucket, $id) => ['reference' => $names[$id] ?? '—', 'context' => 'فئة التأخر: '.$bucket, 'url' => route('receivables.customers.show', $id), 'age' => null, 'severity' => null])->values(),
            ];
        }

        if ($user->can('reports.inventory') || $user->can('reports.production')) {
            $shortages = $this->inventory->shortages();
            $rows = $shortages->mapRows($shortages->query->get())->filter(fn ($row) => $row['production_shortfall'] !== null);
            $groups['shortages'] = [
                'title' => 'نقص مواد لأوامر إنتاج نشطة', 'area' => 'المخزون', 'count' => $rows->count(),
                'items' => $rows->take($limit)->map(fn ($row) => ['reference' => $row['material']['text'], 'context' => 'العجز: '.ReportFormat::quantity($row['production_shortfall']).' '.$row['unit'].' — أوامر متأثرة: '.$row['affected_pos'], 'url' => $row['material']['url'], 'age' => null, 'severity' => null])->values(),
            ];
        }

        if ($user->can('reports.procurement')) {
            $late = $this->procurement->latePoQuery()->leftJoin('suppliers as s', 's.id', '=', 'purchase_orders.supplier_id');
            $groups['late_po'] = [
                'title' => 'أوامر شراء متأخرة التوريد', 'area' => 'المشتريات', 'count' => (clone $late)->count(),
                'items' => $late->orderBy('expected_delivery_date')->limit($limit)->get(['purchase_orders.id', 'purchase_order_number', 'expected_delivery_date', 's.name'])
                    ->map(fn ($row) => ['reference' => $row->purchase_order_number, 'context' => $row->name.' — متوقع '.$row->expected_delivery_date, 'url' => route('purchasing.orders.show', $row->id), 'age' => $age($row->expected_delivery_date), 'severity' => null]),
            ];
        }

        if ($user->can('reports.production') || $user->can('reports.quality')) {
            $severityLabels = ['HIGH' => ['label' => 'عالية', 'tone' => 'warning'], 'CRITICAL' => ['label' => 'حرجة', 'tone' => 'danger']];
            $incidents = DB::table('quality_incidents')->whereIn('severity', ['HIGH', 'CRITICAL'])->whereNotIn('status', ['RESOLVED', 'CANCELLED']);
            $groups['quality'] = [
                'title' => 'حوادث جودة عالية/حرجة مفتوحة', 'area' => 'الجودة', 'count' => (clone $incidents)->count(),
                'items' => $incidents->orderByRaw("CASE WHEN severity = 'CRITICAL' THEN 0 ELSE 1 END")->orderBy('created_at')->limit($limit)->get(['id', 'incident_number', 'incident_type', 'severity', 'created_at'])
                    ->map(fn ($row) => ['reference' => $row->incident_number, 'context' => $row->incident_type, 'url' => route('production.quality-incidents.show', $row->id), 'age' => $age($row->created_at), 'severity' => $severityLabels[$row->severity] ?? null]),
            ];

            $rework = DB::table('production_rework_actions as ra')->join('production_orders as po', 'po.id', '=', 'ra.production_order_id')->whereNotIn('ra.status', ['COMPLETED', 'CANCELLED']);
            $groups['rework'] = [
                'title' => 'إعادة عمل مفتوحة', 'area' => 'الإنتاج', 'count' => (clone $rework)->count(),
                'items' => $rework->orderBy('ra.created_at')->limit($limit)->get(['ra.rework_number', 'ra.quantity', 'ra.created_at', 'po.production_order_number'])
                    ->map(fn ($row) => ['reference' => $row->rework_number, 'context' => $row->production_order_number.' — الكمية '.$row->quantity, 'url' => route('production.rework.index'), 'age' => $age($row->created_at), 'severity' => null]),
            ];
        }

        if ($user->can('reports.delivery')) {
            $failed = DB::table('delivery_orders')->whereIn('status', ['FAILED', 'RESCHEDULED']);
            $groups['failed_delivery'] = [
                'title' => 'توصيلات متعذرة / مؤجلة', 'area' => 'التوصيل', 'count' => (clone $failed)->count(),
                'items' => $failed->orderBy('updated_at')->limit($limit)->get(['id', 'delivery_number', 'customer_name_snapshot', 'status', 'updated_at'])
                    ->map(fn ($row) => ['reference' => $row->delivery_number, 'context' => $row->customer_name_snapshot, 'url' => route('delivery.orders.show', $row->id), 'age' => $age($row->updated_at), 'severity' => null]),
            ];
        }

        return $groups->filter(fn ($group) => $group['count'] > 0);
    }
}
