<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\DeliveryAnalyticsService;
use App\Domain\Reports\ReportColumn as C;
use App\Domain\Reports\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FulfillmentReportController extends Controller
{
    use RendersReports;

    public const TABS = [
        'pipeline' => 'مسار تنفيذ الطلبات',
        'finished_goods' => 'المنتجات الجاهزة غير المسلمة',
        'delivery' => 'تحليل التوصيل',
        'returns' => 'مرتجعات العملاء',
    ];

    public function __construct(protected DeliveryAnalyticsService $delivery) {}

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('reports.delivery'), 403);

        $period = ReportPeriod::fromRequest($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'pipeline';
        $data = ['period' => $period, 'tab' => $tab, 'tabs' => self::TABS];

        if ($tab === 'delivery') {
            return view('management_reports.fulfillment', $data + ['summary' => $this->delivery->deliverySummary($period)]);
        }

        [$dataset, $columns, $title] = match ($tab) {
            'finished_goods' => [$this->delivery->finishedGoodsAging(), [
                C::make('production_order', 'أمر الإنتاج', C::CODE),
                C::make('customer_order', 'طلب العميل', C::LINK),
                C::make('customer', 'العميل'),
                C::make('product', 'المنتج'),
                C::make('available_qty', 'الكمية الجاهزة', C::QTY),
                C::make('ready_since', 'جاهز منذ', C::DATE),
                C::make('days_waiting', 'أيام الانتظار', C::INT),
                C::make('delivery_status', 'التوصيل', C::BADGE),
            ], 'المنتجات الجاهزة غير المسلمة'],
            'returns' => [$this->delivery->returns($period), [
                C::make('return_number', 'رقم المرتجع', C::LINK),
                C::make('reported_at', 'تاريخ البلاغ', C::DATE),
                C::make('customer', 'العميل'),
                C::make('order', 'الطلب', C::CODE),
                C::make('delivery', 'أمر التوصيل', C::CODE),
                C::make('model', 'الموديل'),
                C::make('quantity', 'الكمية', C::QTY),
                C::make('reason', 'السبب'),
                C::make('condition', 'الحالة الفيزيائية'),
                C::make('incident', 'حادثة الجودة', C::CODE),
                C::make('status', 'حالة المرتجع'),
            ], 'مرتجعات العملاء'],
            default => [$this->delivery->fulfillment($period, $request->only('stage')), [
                C::make('order', 'الطلب', C::LINK),
                C::make('customer', 'العميل'),
                C::make('order_date', 'تاريخ الطلب', C::DATE),
                C::make('ordered_qty', 'المطلوب', C::QTY),
                C::make('released_qty', 'المُطلق للإنتاج', C::QTY),
                C::make('completed_qty', 'المنجز', C::QTY),
                C::make('fg_available', 'جاهز بالمخزن', C::QTY),
                C::make('dispatched_qty', 'خرج للتوصيل', C::QTY),
                C::make('delivered_qty', 'المُسلّم', C::QTY),
                C::make('payment_status', 'السداد', C::BADGE)->requires(['reports.receivables']),
            ], 'تنفيذ الطلبات'],
        };

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, $title, 'fulfillment-'.$tab, $tab === 'finished_goods' ? null : $period);
        }

        return view('management_reports.fulfillment', $data + [
            'title' => $title,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'totals' => $dataset->totals,
        ]);
    }
}
