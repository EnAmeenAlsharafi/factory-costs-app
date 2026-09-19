<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAllocationService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function __construct(
        protected CustomerPaymentService $paymentService,
        protected PaymentAllocationService $allocationService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض دفعات العملاء.');

        $filters = $request->only(['search', 'customer_id', 'status', 'payment_method']);

        $payments = CustomerPayment::with(['customer', 'createdBy', 'confirmedBy', 'allocations.order'])
            ->filter($filters)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::orderBy('name')->get();

        return view('receivables.payments.index', compact('payments', 'customers', 'filters'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('receivables.payment.create'), 403, 'غير مصرح لك بتسجيل دفعات للعملاء.');

        $customers = Customer::active()->orderBy('name')->get();
        $selectedOrder = null;
        $selectedCustomer = null;

        if ($request->filled('order_id')) {
            $selectedOrder = CustomerOrder::with('customer')->find($request->input('order_id'));
            if ($selectedOrder) {
                $selectedCustomer = $selectedOrder->customer;
            }
        } elseif ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->input('customer_id'));
        }

        return view('receivables.payments.create', compact('customers', 'selectedOrder', 'selectedCustomer'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.payment.create'), 403, 'غير مصرح لك بتسجيل دفعات للعملاء.');

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:CASH,BANK_TRANSFER,CARD,SADAD_OR_EXTERNAL,CHEQUE,OTHER',
            'reference_number' => 'nullable|string|max:100',
            'bank_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'auto_allocate_order_id' => 'nullable|exists:customer_orders,id',
            'auto_confirm' => 'nullable|boolean',
        ]);

        try {
            $payment = $this->paymentService->createPayment($validated, $request->user());

            // If requested and user has confirm permission, confirm automatically
            if (! empty($validated['auto_confirm']) && $request->user()->can('receivables.payment.confirm')) {
                $this->paymentService->confirmPayment($payment, $request->user());
            }

            // If initiated from a specific order and payment is confirmed, allocate immediately
            if (! empty($validated['auto_allocate_order_id']) && $payment->status === 'CONFIRMED') {
                $order = CustomerOrder::findOrFail($validated['auto_allocate_order_id']);
                $allocatedAmount = min((float) $payment->amount, $order->outstanding_balance);

                if ($allocatedAmount > 0) {
                    $this->allocationService->allocatePayment($payment, $order, $allocatedAmount, 'PARTIAL_PAYMENT', $request->user(), 'تخصيص تلقائي عند تسجيل الدفعة من صفحة الطلب');
                }
            }

            return redirect()->route('receivables.payments.show', $payment)
                ->with('success', 'تم تسجيل الدفعة رقم '.$payment->payment_number.' بنجاح.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, CustomerPayment $payment): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بعرض تفاصيل الدفعة.');

        $payment->load(['customer', 'createdBy', 'receivedBy', 'confirmedBy', 'reversedBy', 'allocations.order', 'events.user']);

        // Fetch open orders for customer to allow allocation
        $openOrders = CustomerOrder::where('customer_id', $payment->customer_id)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->get()
            ->filter(fn ($o) => $o->outstanding_balance > 0);

        return view('receivables.payments.show', compact('payment', 'openOrders'));
    }

    public function confirm(Request $request, CustomerPayment $payment): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.payment.confirm'), 403, 'غير مصرح لك بتأكيد الدفعات.');

        try {
            $this->paymentService->confirmPayment($payment, $request->user());

            return redirect()->route('receivables.payments.show', $payment)
                ->with('success', 'تم تأكيد واعتماد الدفعة بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, CustomerPayment $payment): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.payment.create'), 403, 'غير مصرح لك بإلغاء مسودة الدفعة.');

        try {
            $this->paymentService->cancelPayment($payment, $request->user(), $request->input('reason'));

            return redirect()->route('receivables.payments.show', $payment)
                ->with('success', 'تم إلغاء طلب الدفعة بنجاح.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, CustomerPayment $payment): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.payment.reverse'), 403, 'غير مصرح لك بعكس الدفعات المؤكدة.');

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5',
        ]);

        try {
            $this->paymentService->reversePayment($payment, $request->user(), $validated['reversal_reason']);

            return redirect()->route('receivables.payments.show', $payment)
                ->with('success', 'تم عكس الدفعة المؤكدة وتحديث الأرصدة المرتبطة بها.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receipt(Request $request, CustomerPayment $payment): View
    {
        abort_if(! $request->user()->can('receivables.view'), 403, 'غير مصرح لك بطباعة إيصال الدفعة.');

        $payment->load(['customer', 'createdBy', 'receivedBy', 'confirmedBy', 'allocations.order']);

        return view('receivables.payments.receipt', compact('payment'));
    }
}
