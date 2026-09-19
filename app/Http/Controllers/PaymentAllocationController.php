<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Services\PaymentAllocationService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentAllocationController extends Controller
{
    public function __construct(
        protected PaymentAllocationService $allocationService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.allocate'), 403, 'غير مصرح لك بتخصيص الدفعات على الطلبات.');

        $validated = $request->validate([
            'customer_payment_id' => 'required|exists:customer_payments,id',
            'customer_order_id' => 'required|exists:customer_orders,id',
            'allocated_amount' => 'required|numeric|gt:0',
            'allocation_type' => 'nullable|in:DEPOSIT,PARTIAL_PAYMENT,FINAL_PAYMENT,GENERAL_ALLOCATION',
            'notes' => 'nullable|string',
        ]);

        try {
            $payment = CustomerPayment::findOrFail($validated['customer_payment_id']);
            $order = CustomerOrder::findOrFail($validated['customer_order_id']);

            $this->allocationService->allocatePayment(
                $payment,
                $order,
                (float) $validated['allocated_amount'],
                $validated['allocation_type'] ?? 'PARTIAL_PAYMENT',
                $request->user(),
                $validated['notes'] ?? null
            );

            return back()->with('success', 'تم تخصيص المبلغ على الطلب بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, CustomerPaymentAllocation $allocation): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.allocate'), 403, 'غير مصرح لك بفك تخصيص الدفعات.');

        try {
            $this->allocationService->removeAllocation($allocation, $request->user());

            return back()->with('success', 'تم إلغاء تخصيص المبلغ عن الطلب بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
