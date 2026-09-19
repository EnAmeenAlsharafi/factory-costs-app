<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ReceivablesReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivablesReportController extends Controller
{
    public function __construct(
        protected ReceivablesReportingService $reportingService
    ) {}

    public function dashboard(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض لوحة متابعة التحصيل والدفعات.');

        $summary = $this->reportingService->getDashboardSummary();

        return view('receivables.dashboard', compact('summary'));
    }

    public function balances(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض أرصدة العملاء.');

        $filters = $request->only(['search', 'is_credit_customer']);
        $balances = $this->reportingService->getCustomerBalances($filters);

        return view('receivables.customers.index', compact('balances', 'filters'));
    }

    public function statement(Request $request, Customer $customer): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض كشف الحساب التشغيلي للعميل.');

        $statement = $this->reportingService->getCustomerStatement($customer);
        $customer->load(['creditProfile']);

        return view('receivables.customers.show', compact('customer', 'statement'));
    }

    public function aging(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض تقرير أعمار الديون.');

        $agingBuckets = $this->reportingService->getAgingReport();

        return view('receivables.reports.aging', compact('agingBuckets'));
    }
}
