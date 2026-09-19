<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerCreditService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCreditProfileController extends Controller
{
    public function __construct(
        protected CustomerCreditService $creditService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.credit.view'), 403, 'غير مصرح لك بعرض ملفات الائتمان للعملاء.');

        $customers = Customer::with(['creditProfile', 'orders' => fn ($q) => $q->whereNotIn('status', ['CANCELLED', 'REJECTED'])])
            ->active()
            ->paginate(20);

        return view('receivables.credit.index', compact('customers'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.credit.manage'), 403, 'غير مصرح لك بإدارة الحدود الائتمانية للعملاء.');

        $validated = $request->validate([
            'credit_enabled' => 'required|boolean',
            'credit_limit' => 'required|numeric|min:0',
            'credit_days' => 'required|integer|min:0',
            'warning_threshold_percent' => 'required|numeric|min:1|max:100',
            'hold_when_exceeded' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->creditService->updateCreditProfile($customer, $validated, $request->user());

            return back()->with('success', 'تم تحديث ملف وحد الائتمان للعميل '.$customer->name.' بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
