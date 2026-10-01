<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ReceivablesAnalyticsService;
use App\Domain\Reports\ReportColumn as C;
use App\Domain\Reports\ReportPeriod;
use App\Domain\Reports\ReportTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersReports;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesManagementReportController extends Controller
{
    use RendersReports;

    public function __construct(protected ReceivablesAnalyticsService $receivables) {}

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('reports.receivables'), 403);

        $tab = $request->query('tab') === 'collections' ? 'collections' : 'balances';
        $period = ReportPeriod::fromRequest($request);

        if ($tab === 'collections') {
            $dataset = $this->receivables->collections($period, $request->only(['status', 'payment_method']));
            $columns = [
                C::make('payment', 'الدفعة', C::LINK),
                C::make('payment_date', 'التاريخ', C::DATE),
                C::make('customer', 'العميل'),
                C::make('amount', 'المبلغ', C::MONEY),
                C::make('method', 'طريقة الدفع'),
                C::make('status', 'الحالة', C::BADGE),
                C::make('unallocated', 'غير مخصص', C::MONEY),
            ];
            if ($this->wantsExport($request)) {
                return $this->exportCsv($request, $dataset, $columns, 'نشاط التحصيل', 'collections', $period);
            }

            return view('management_reports.receivables', [
                'tab' => $tab, 'period' => $period,
                'table' => $this->paginatedTable($request, $dataset, $columns),
                'totals' => $dataset->totals,
            ]);
        }

        $paginator = $this->receivables->customerBalances($request->only(['search', 'is_credit_customer']))->withQueryString();
        $table = ReportTable::for($request->user(), [
            C::make('customer', 'العميل', C::LINK),
            C::make('code', 'الكود', C::CODE),
            C::make('outstanding', 'المبالغ المستحقة', C::MONEY),
            C::make('overdue', 'المتأخرات', C::MONEY)->negativeAlert(),
            C::make('aging_bucket', 'أقدم فئة تأخر'),
            C::make('credit_limit', 'حد الائتمان', C::MONEY),
            C::make('exposure', 'التعرض الائتماني', C::MONEY),
            C::make('available_credit', 'الائتمان المتاح', C::MONEY),
            C::make('unallocated', 'دفعات غير مخصصة', C::MONEY),
            C::make('credit_status', 'حالة الائتمان', C::BADGE),
        ], $paginator->items(), null, $paginator);

        return view('management_reports.receivables', [
            'tab' => $tab, 'period' => $period, 'table' => $table,
            'aging' => $this->receivables->agingBuckets(),
        ]);
    }
}
