<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ProductionAnalyticsService;
use App\Domain\Reports\ProfitabilityReportingService;
use App\Domain\Reports\ReportColumn as C;
use App\Domain\Reports\ReportDataset;
use App\Domain\Reports\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerType;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\SalesChannel;
use App\Services\CustomerCreditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitabilityReportController extends Controller
{
    use RendersReports;

    public function __construct(
        protected ProfitabilityReportingService $profitability,
        protected ProductionAnalyticsService $production,
    ) {}

    public function orders(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('reports.profitability'), 403);

        $period = ReportPeriod::fromRequest($request);
        $filters = $request->only(['customer_id', 'sales_channel_id', 'customer_type_id', 'status', 'include_cancelled']);
        $dataset = $this->profitability->orders($period, $filters, (string) $request->query('sort', 'date'));
        $columns = $this->orderColumns();

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'ربحية الطلبات التشغيلية', 'order-contribution', $period, $this->describeFilters($filters));
        }

        return view('management_reports.profitability.orders', [
            'period' => $period,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'totals' => $dataset->totals,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'channels' => SalesChannel::orderBy('sort_order')->get(['id', 'name_ar']),
            'customerTypes' => CustomerType::orderBy('name_ar')->get(['id', 'name_ar']),
            'statuses' => ProfitabilityReportingService::STATUSES,
        ]);
    }

    public function showOrder(Request $request, CustomerOrder $order): View
    {
        abort_unless($request->user()->can('reports.profitability'), 403);

        $order->load(['customer', 'salesChannel', 'lines.productModel', 'lines.productConfiguration']);
        $row = collect($this->profitability->orderBase(ReportPeriod::between(
            CarbonImmutable::parse($order->order_date ?? $order->created_at),
            CarbonImmutable::parse($order->order_date ?? $order->created_at)
        ), ['include_cancelled' => true])->where('o.id', $order->id)->get())->first();
        $summary = $row ? $this->profitability->mapOrderRow($row) : null;

        $productionOrders = ProductionOrder::with(['productModel', 'recipeVersion.recipe', 'productConfiguration'])
            ->where('customer_order_id', $order->id)->orderBy('id')->get()
            ->map(fn (ProductionOrder $po) => ['po' => $po] + $this->production->materialVariance($po));

        return view('management_reports.profitability.order_show', compact('order', 'summary', 'row', 'productionOrders'));
    }

    public function products(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('reports.profitability') || $user->can('reports.sales'), 403);

        $period = ReportPeriod::fromRequest($request);
        $group = $request->query('group') === 'configuration' ? 'configuration' : 'model';
        $sort = (string) $request->query('sort', 'value');
        $filters = $request->only(['sales_channel_id', 'product_model_id', 'has_storage']);
        $dataset = $this->profitability->products($period, $filters, $group, $sort);
        $columns = $this->productColumns();

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, $group === 'model' ? 'ربحية الموديلات' : 'ربحية التكوينات والمقاسات', 'product-contribution', $period, $this->describeFilters($filters));
        }

        $table = $this->paginatedTable($request, $dataset, $columns);

        return view('management_reports.profitability.products', [
            'period' => $period, 'group' => $group, 'sort' => $sort, 'table' => $table,
            'chart' => collect($table->rows)->take(10),
            'models' => ProductModel::orderBy('name_ar')->get(['id', 'name_ar']),
            'channels' => SalesChannel::orderBy('sort_order')->get(['id', 'name_ar']),
            'canSeeContribution' => $user->can('reports.profitability'),
        ]);
    }

    public function channels(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('reports.profitability') || $user->can('reports.sales'), 403);

        $period = ReportPeriod::fromRequest($request);
        $dataset = $this->profitability->channels($period);
        $columns = [
            C::make('channel', 'قناة البيع', C::LINK),
            C::make('channel_code', 'الرمز', C::CODE),
            C::make('order_count', 'الطلبات', C::INT),
            C::make('quantity', 'الكمية', C::QTY),
            C::make('commercial_value', 'القيمة التجارية', C::MONEY),
            C::make('average_order_value', 'متوسط قيمة الطلب', C::MONEY),
            C::make('actual_cost_to_date', 'تكلفة المواد الفعلية حتى تاريخه', C::MONEY)->requires(['reports.profitability']),
            C::make('final_count', 'طلبات مكتملة التكلفة', C::INT)->requires(['reports.profitability']),
            C::make('contribution', 'المساهمة التشغيلية (المكتملة)', C::MONEY)->requires(['reports.profitability'])->negativeAlert(),
            C::make('contribution_pct', 'هامش المساهمة', C::PERCENT)->requires(['reports.profitability'])->negativeAlert(),
        ];

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'تحليل قنوات البيع', 'sales-channels', $period);
        }

        return view('management_reports.profitability.channels', ['period' => $period, 'table' => $this->paginatedTable($request, $dataset, $columns)]);
    }

    public function customers(Request $request, CustomerCreditService $credit): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('reports.profitability') || $user->can('reports.sales'), 403);

        $period = ReportPeriod::fromRequest($request, 'this_year');
        $filters = $request->only(['customer_type_id', 'sales_channel_id']);
        $base = $this->profitability->customers($period, $filters);
        $showCredit = $user->can('reports.receivables');
        $dataset = new ReportDataset($base->query, $base->mapper, function (Collection $rows) use ($credit, $showCredit) {
            if (! $showCredit) {
                return $rows;
            }
            $customers = Customer::with('creditProfile')->whereIn('id', $rows->pluck('customer_id'))->get()->keyBy('id');

            return $rows->map(function (array $row) use ($customers, $credit) {
                $customer = $customers->get($row['customer_id']);
                if ($customer && ($customer->creditProfile || $customer->is_credit_customer)) {
                    $row['credit_exposure'] = (float) $credit->calculateExposure($customer)['current_exposure'];
                }

                return $row;
            });
        });
        $columns = [
            C::make('customer', 'العميل', C::LINK),
            C::make('customer_type', 'نوع العميل'),
            C::make('order_count', 'الطلبات', C::INT),
            C::make('commercial_value', 'القيمة التجارية', C::MONEY),
            C::make('actual_cost_to_date', 'تكلفة المواد الفعلية حتى تاريخه', C::MONEY)->requires(['reports.profitability']),
            C::make('contribution', 'المساهمة التشغيلية (المكتملة)', C::MONEY)->requires(['reports.profitability'])->negativeAlert(),
            C::make('contribution_pct', 'هامش المساهمة', C::PERCENT)->requires(['reports.profitability'])->negativeAlert(),
            C::make('outstanding', 'المبالغ المستحقة', C::MONEY)->requires(['reports.receivables']),
            C::make('overdue', 'المتأخرات', C::MONEY)->requires(['reports.receivables'])->negativeAlert(),
            C::make('credit_exposure', 'التعرض الائتماني', C::MONEY)->requires(['reports.receivables']),
            C::make('last_order_date', 'آخر طلب', C::DATE),
        ];

        if ($this->wantsExport($request)) {
            return $this->exportCsv($request, $dataset, $columns, 'تحليل العملاء', 'customers-analysis', $period, $this->describeFilters($filters));
        }

        return view('management_reports.profitability.customers', [
            'period' => $period,
            'table' => $this->paginatedTable($request, $dataset, $columns),
            'customerTypes' => CustomerType::orderBy('name_ar')->get(['id', 'name_ar']),
            'channels' => SalesChannel::orderBy('sort_order')->get(['id', 'name_ar']),
        ]);
    }

    /**
     * @return list<C>
     */
    private function orderColumns(): array
    {
        return [
            C::make('order', 'الطلب', C::LINK),
            C::make('customer', 'العميل'),
            C::make('channel', 'قناة البيع'),
            C::make('order_date', 'تاريخ الطلب', C::DATE),
            C::make('commercial_value', 'القيمة التجارية للطلب', C::MONEY),
            C::make('actual_cost', 'تكلفة المواد الفعلية', C::MONEY)->withHint('المصروف المرحّل ناقص المرتجع القابل للاستخدام'),
            C::make('contribution', 'المساهمة التشغيلية', C::MONEY)->negativeAlert()->withHint('تُعرض فقط عند اكتمال التكلفة'),
            C::make('contribution_pct', 'هامش المساهمة', C::PERCENT)->negativeAlert(),
            C::make('profitability_status', 'حالة التكلفة', C::BADGE),
            C::make('production_status', 'الإنتاج', C::BADGE),
            C::make('delivery_status', 'التوصيل', C::BADGE),
            C::make('payment_status', 'السداد', C::BADGE),
        ];
    }

    /**
     * @return list<C>
     */
    private function productColumns(): array
    {
        return [
            C::make('product', 'الموديل / التكوين', C::LINK),
            C::make('order_count', 'الطلبات', C::INT),
            C::make('quantity', 'الكمية المباعة', C::QTY),
            C::make('commercial_value', 'القيمة التجارية', C::MONEY),
            C::make('avg_value_per_unit', 'متوسط القيمة للوحدة', C::MONEY),
            C::make('actual_cost_to_date', 'تكلفة المواد الفعلية حتى تاريخه', C::MONEY)->requires(['reports.profitability']),
            C::make('final_quantity', 'كمية مكتملة التكلفة', C::QTY)->requires(['reports.profitability']),
            C::make('avg_cost_per_unit', 'متوسط تكلفة المواد للوحدة (المكتملة)', C::MONEY)->requires(['reports.profitability']),
            C::make('contribution', 'المساهمة التشغيلية (المكتملة)', C::MONEY)->requires(['reports.profitability'])->negativeAlert(),
            C::make('contribution_pct', 'هامش المساهمة', C::PERCENT)->requires(['reports.profitability'])->negativeAlert(),
            C::make('rework_cost', 'مواد إعادة العمل (ضمن التكلفة)', C::MONEY)->requires(['reports.profitability']),
            C::make('waste_cost', 'الهدر (تحليلي، ضمن التكلفة)', C::MONEY)->requires(['reports.profitability']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function describeFilters(array $filters): array
    {
        $described = [];
        if (! empty($filters['customer_id'])) {
            $described['العميل'] = (string) Customer::whereKey($filters['customer_id'])->value('name');
        }
        if (! empty($filters['sales_channel_id'])) {
            $described['قناة البيع'] = (string) SalesChannel::whereKey($filters['sales_channel_id'])->value('name_ar');
        }
        if (! empty($filters['product_model_id'])) {
            $described['الموديل'] = (string) ProductModel::whereKey($filters['product_model_id'])->value('name_ar');
        }
        if (! empty($filters['status'])) {
            $described['حالة التكلفة'] = ProfitabilityReportingService::STATUSES[$filters['status']]['label'] ?? $filters['status'];
        }
        if (! empty($filters['include_cancelled'])) {
            $described['الطلبات الملغاة'] = 'مشمولة';
        }

        return $described;
    }
}
