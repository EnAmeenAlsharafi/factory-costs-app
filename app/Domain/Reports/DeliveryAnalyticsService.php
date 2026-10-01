<?php

namespace App\Domain\Reports;

use App\Services\StatusPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fulfilment analytics: order pipeline, finished goods waiting delivery (finished-goods movement ledger:
 * available = Σ IN − Σ OUT, the FinishedGoodsService definition), delivery status/throughput and physical returns.
 */
class DeliveryAnalyticsService
{
    public function __construct(protected ProfitabilityReportingService $profitability) {}

    /**
     * Customer order → production → finished goods → delivery → payment, per order (order date basis).
     *
     * @param  array<string, mixed>  $filters
     */
    public function fulfillment(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $fg = DB::table('finished_goods_movements')
            ->whereNotNull('customer_order_id')
            ->groupBy('customer_order_id')
            ->selectRaw("customer_order_id, SUM(CASE WHEN movement_type = 'PRODUCTION_RECEIPT' THEN quantity ELSE 0 END) AS fg_received")
            ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS fg_available");

        $query = DB::query()->fromSub($this->profitability->orderBase($period, $filters), 'r')
            ->leftJoinSub($fg, 'fg', 'fg.customer_order_id', '=', 'r.id')
            ->select('r.id', 'r.order_number', 'r.customer_name', 'r.order_date', 'r.ordered_qty', 'r.released_qty', 'r.completed_qty', 'r.dispatched_qty', 'r.delivered_qty')
            ->selectRaw('COALESCE(fg.fg_received, 0) AS fg_received, COALESCE(fg.fg_available, 0) AS fg_available')
            ->orderByDesc('r.order_date')->orderByDesc('r.id');

        if (($filters['stage'] ?? '') === 'open') {
            $query->whereRaw('r.delivered_qty < r.ordered_qty');
        }

        return new ReportDataset($query, fn (object $row) => [
            'id' => $row->id,
            'order' => ['text' => $row->order_number, 'url' => route('sales.orders.show', $row->id)],
            'customer' => $row->customer_name,
            'order_date' => $row->order_date,
            'ordered_qty' => (float) $row->ordered_qty,
            'released_qty' => (float) $row->released_qty,
            'completed_qty' => (float) $row->completed_qty,
            'fg_available' => max(0.0, (float) $row->fg_available),
            'dispatched_qty' => (float) $row->dispatched_qty,
            'delivered_qty' => (float) $row->delivered_qty,
            'payment_status' => null,
        ], fn (Collection $rows) => $this->profitability->withPaymentStatus($rows));
    }

    /**
     * Finished goods currently available (not yet dispatched), with how long they have been waiting.
     */
    public function finishedGoodsAging(): ReportDataset
    {
        $today = CarbonImmutable::now(config('app.timezone'));

        $openDeliveries = DB::table('delivery_order_lines as dol')
            ->join('delivery_orders as d', 'd.id', '=', 'dol.delivery_order_id')
            ->whereIn('d.status', ['DRAFT', 'READY_FOR_DELIVERY', 'ASSIGNED'])
            ->groupBy('dol.production_order_id')
            ->selectRaw('dol.production_order_id, MIN(d.status) AS delivery_status, COUNT(*) AS open_deliveries');

        $query = DB::table('finished_goods_movements as fgm')
            ->join('production_orders as po', 'po.id', '=', 'fgm.production_order_id')
            ->leftJoin('customer_orders as co', 'co.id', '=', 'po.customer_order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'co.customer_id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->leftJoinSub($openDeliveries, 'od', 'od.production_order_id', '=', 'po.id')
            ->groupBy('po.id', 'po.production_order_number', 'co.id', 'co.order_number', 'c.name', 'pm.name_ar', 'po.custom_design_name', 'od.delivery_status', 'od.open_deliveries')
            ->havingRaw("SUM(CASE WHEN fgm.direction = 'IN' THEN fgm.quantity ELSE -fgm.quantity END) > 0")
            ->selectRaw('po.id, po.production_order_number, co.id AS customer_order_id, co.order_number, c.name AS customer, pm.name_ar AS model, po.custom_design_name')
            ->selectRaw("SUM(CASE WHEN fgm.direction = 'IN' THEN fgm.quantity ELSE -fgm.quantity END) AS available_qty")
            ->selectRaw("MIN(CASE WHEN fgm.movement_type = 'PRODUCTION_RECEIPT' THEN fgm.occurred_at END) AS ready_since")
            ->selectRaw('od.delivery_status, od.open_deliveries')
            ->orderByRaw("MIN(CASE WHEN fgm.movement_type = 'PRODUCTION_RECEIPT' THEN fgm.occurred_at END) ASC");

        return new ReportDataset($query, function (object $row) use ($today) {
            $readySince = $row->ready_since ? CarbonImmutable::parse($row->ready_since, config('app.timezone')) : null;

            return [
                'production_order' => $row->production_order_number,
                'customer_order' => $row->order_number ? ['text' => $row->order_number, 'url' => route('sales.orders.show', $row->customer_order_id)] : null,
                'customer' => $row->customer,
                'product' => $row->model ?? ($row->custom_design_name ?: 'تصميم خاص'),
                'available_qty' => (float) $row->available_qty,
                'ready_since' => $readySince?->format('Y-m-d'),
                'days_waiting' => $readySince ? (int) $readySince->diffInDays($today) : null,
                'delivery_status' => $row->delivery_status
                    ? ['label' => StatusPresenter::label('delivery', $row->delivery_status), 'tone' => 'info']
                    : ['label' => 'لا يوجد أمر توصيل', 'tone' => 'warning'],
            ];
        });
    }

    /**
     * Current delivery status distribution plus period throughput (delivery event dates).
     *
     * @return array<string, mixed>
     */
    public function deliverySummary(ReportPeriod $period): array
    {
        [$from, $to] = $period->timestampRange();

        $distribution = DB::table('delivery_orders')
            ->whereNotIn('status', ['CANCELLED'])
            ->groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status');

        $order = ['DRAFT', 'READY_FOR_DELIVERY', 'ASSIGNED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED', 'FAILED', 'RESCHEDULED'];
        $statuses = collect($order)->map(fn ($status) => StatusPresenter::present('delivery', $status) + ['status' => $status, 'count' => (int) ($distribution[$status] ?? 0)]);

        $awaitingInstallation = DB::table('delivery_orders')->where('status', 'DELIVERED')->where('installation_required', true)->count();

        $events = DB::table('delivery_events')->whereBetween('occurred_at', [$from, $to])
            ->groupBy('event_type')->selectRaw('event_type, COUNT(*) AS n')->pluck('n', 'event_type');

        $durations = DB::table('delivery_orders')
            ->whereNotNull('dispatched_at')->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$from, $to])
            ->get(['dispatched_at', 'delivered_at'])
            ->map(fn ($row) => CarbonImmutable::parse($row->dispatched_at)->diffInMinutes(CarbonImmutable::parse($row->delivered_at)) / 60);

        return [
            'statuses' => $statuses,
            'active_total' => $statuses->whereNotIn('status', ['DELIVERED', 'INSTALLATION_COMPLETED'])->sum('count'),
            'awaiting_installation' => $awaitingInstallation,
            'delivered_in_period' => (int) ($events['DELIVERED'] ?? 0),
            'installed_in_period' => (int) ($events['INSTALLED'] ?? 0),
            'failed_in_period' => (int) ($events['FAILED'] ?? 0),
            'rescheduled_in_period' => (int) ($events['RESCHEDULED'] ?? 0),
            'avg_dispatch_to_delivery_hours' => $durations->isNotEmpty() ? round($durations->avg(), 1) : null,
            'duration_sample' => $durations->count(),
        ];
    }

    /**
     * Physical customer returns (reported date basis). A physical return never reduces commercial value.
     */
    public function returns(ReportPeriod $period): ReportDataset
    {
        $query = DB::table('customer_returns as cr')
            ->leftJoin('customer_orders as co', 'co.id', '=', 'cr.customer_order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'co.customer_id')
            ->leftJoin('production_orders as po', 'po.id', '=', 'cr.production_order_id')
            ->leftJoin('product_models as pm', 'pm.id', '=', 'po.product_model_id')
            ->leftJoin('delivery_orders as d', 'd.id', '=', 'cr.delivery_order_id')
            ->leftJoin('quality_incidents as qi', 'qi.id', '=', 'cr.quality_incident_id')
            ->whereBetween('cr.reported_at', $period->timestampRange())
            ->select('cr.id', 'cr.return_number', 'cr.quantity', 'cr.reason_code', 'cr.condition_code', 'cr.status', 'cr.reported_at')
            ->selectRaw("co.order_number, c.name AS customer, COALESCE(pm.name_ar, 'تصاميم خاصة') AS model, d.delivery_number, qi.incident_number")
            ->orderByDesc('cr.reported_at');

        $totals = DB::query()->fromSub(clone $query, 't')->selectRaw('COUNT(*) AS return_count, COALESCE(SUM(t.quantity), 0) AS quantity')->first();
        $byReason = DB::query()->fromSub(clone $query, 't')->groupBy('t.reason_code')->selectRaw('t.reason_code, COUNT(*) AS n, SUM(t.quantity) AS qty')->orderByDesc('n')->get();

        return new ReportDataset($query, fn (object $row) => [
            'return_number' => ['text' => $row->return_number, 'url' => route('customer-returns.show', $row->id)],
            'reported_at' => CarbonImmutable::parse($row->reported_at)->format('Y-m-d'),
            'customer' => $row->customer,
            'order' => $row->order_number,
            'delivery' => $row->delivery_number,
            'model' => $row->model,
            'quantity' => (float) $row->quantity,
            'reason' => $row->reason_code,
            'condition' => $row->condition_code,
            'incident' => $row->incident_number,
            'status' => $row->status,
        ], null, ['return_count' => (int) $totals->return_count, 'quantity' => (float) $totals->quantity, 'by_reason' => $byReason]);
    }
}
