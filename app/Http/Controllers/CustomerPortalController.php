<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPortalOrderRequest;
use App\Models\CustomerOrder;
use App\Models\CustomerPortalRequest;
use App\Models\DeliveryOrder;
use App\Services\CustomerPortalAccountService;
use App\Services\CustomerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerPortalController extends Controller
{
    public function home(Request $request, CustomerPortalAccountService $accounts): View
    {
        $customer = $request->user()->customer;
        $summary = $accounts->account($customer)['summary'];
        $submissions = CustomerPortalRequest::where('customer_id', $customer->id)->latest('id')->limit(5)->get();
        $openRequests = CustomerPortalRequest::where('customer_id', $customer->id)->whereIn('status', ['SUBMITTED', 'OFFERED', 'CHANGES_REQUESTED'])->count();
        $deliveredOrders = $customer->orders()->whereHas('deliveryOrders', fn ($query) => $query->whereIn('status', ['DELIVERED', 'INSTALLATION_COMPLETED']))->count();

        return view('portal.home', compact('customer', 'summary', 'submissions', 'openRequests', 'deliveredOrders'));
    }

    public function requests(Request $request): View
    {
        $query = CustomerPortalRequest::where('customer_id', $request->user()->customer_id);
        if (array_key_exists((string) $request->query('status'), CustomerPortalRequest::STATUSES)) {
            $query->where('status', $request->query('status'));
        }
        $submissions = $query->latest('id')->paginate(20)->withQueryString();

        return view('portal.requests', compact('submissions'));
    }

    public function create(Request $request, CustomerPortalService $portal): View
    {
        $submission = new CustomerPortalRequest(['lines' => [], 'delivery_city' => $request->user()->customer->city, 'delivery_address' => $request->user()->customer->address]);

        return view('portal.form', array_merge(compact('submission'), $portal->catalog($request->user()->customer)));
    }

    public function store(CustomerPortalOrderRequest $request, CustomerPortalService $portal): RedirectResponse
    {
        $submission = $portal->saveDraft($request->user(), $request->validated());

        return redirect()->route('portal.requests.show', $submission)->with('success', 'تم حفظ المسودة. راجعها ثم أرسلها للمصنع.');
    }

    public function show(Request $request, CustomerPortalRequest $submission): View
    {
        $this->own($request, $submission);
        $submission->load('events', 'order');

        return view('portal.show', compact('submission'));
    }

    public function designImage(Request $request, CustomerPortalRequest $submission, string $lineKey): BinaryFileResponse
    {
        if ($request->user()->isCustomerAccount()) {
            $this->own($request, $submission);
        } else {
            abort_unless($request->user()->can('orders.update') && $submission->status !== 'DRAFT', 403);
        }
        $line = collect($submission->lines)->firstWhere('line_key', $lineKey);
        $path = $line['custom_design_image_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    public function edit(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): View
    {
        $this->own($request, $submission);
        abort_unless(in_array($submission->status, ['DRAFT', 'CHANGES_REQUESTED'], true), 403);

        return view('portal.form', array_merge(compact('submission'), $portal->catalog($request->user()->customer)));
    }

    public function update(CustomerPortalOrderRequest $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        $portal->saveDraft($request->user(), $request->validated(), $submission);

        return redirect()->route('portal.requests.show', $submission)->with('success', 'تم حفظ التعديلات كمسودة. أرسل الطلب للمراجعة.');
    }

    public function submit(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        $this->own($request, $submission);
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $portal->submit($submission, $request->user(), $request->integer('revision'));

        return back()->with('success', 'تم إرسال طلبك لخدمة العملاء لمراجعة المواصفات والتسعير.');
    }

    public function confirm(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        $this->own($request, $submission);
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $order = $portal->confirm($submission, $request->user(), $request->integer('revision'));

        return redirect()->route('portal.orders.show', $order)->with('success', 'تم تأكيد العرض وإحالة الطلب لاعتماد الإنتاج.');
    }

    public function changes(Request $request, CustomerPortalRequest $submission, CustomerPortalService $portal): RedirectResponse
    {
        $this->own($request, $submission);
        $request->validate(['revision' => ['required', 'integer', 'min:1'], 'message' => ['required', 'string', 'max:5000']]);
        $portal->requestChanges($submission, $request->user(), $request->integer('revision'), $request->input('message'));

        return back()->with('success', 'تم تسجيل طلب التعديل لخدمة العملاء. لا تتغير المواصفات المعتمدة دون مراجعة المصنع.');
    }

    public function orders(Request $request, CustomerPortalAccountService $accounts): View
    {
        $query = $request->user()->customer->orders()->with(['lines', 'deliveryOrders.lines', 'productionOrders', 'customerReturns']);
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($query) => $query->where('order_number', 'like', "%{$search}%")->orWhere('customer_reference', 'like', "%{$search}%"));
        }
        $orders = $query->latest('id')->paginate(15)->withQueryString();
        $progress = $orders->getCollection()->mapWithKeys(fn (CustomerOrder $order): array => [$order->id => $accounts->progress($order)]);

        return view('portal.orders', compact('orders', 'progress'));
    }

    public function order(Request $request, CustomerOrder $order, CustomerPortalAccountService $accounts): View
    {
        abort_unless($order->customer_id === $request->user()->customer_id, 404);
        $order->load(['lines.productModel', 'lines.material', 'lines.fabricMaterial', 'lines.fabricColor', 'portalRequest']);
        $progress = $accounts->progress($order);

        return view('portal.order', compact('order', 'progress'));
    }

    public function receipts(Request $request): View
    {
        $deliveries = $request->user()->customer->orders()->pluck('id');
        $receipts = DeliveryOrder::whereIn('customer_order_id', $deliveries)->whereIn('status', ['DELIVERED', 'INSTALLATION_COMPLETED'])
            ->with('customerOrder', 'lines')->latest('delivered_at')->paginate(20);

        return view('portal.receipts', compact('receipts'));
    }

    public function account(Request $request, CustomerPortalAccountService $accounts): View
    {
        $account = $accounts->account($request->user()->customer);
        $summary = $account['summary'];
        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = new LengthAwarePaginator($account['entries']->forPage($page, 30)->values(), $account['entries']->count(), 30, $page, ['path' => $request->url()]);

        return view('portal.account', compact('summary', 'entries'));
    }

    public function profile(Request $request): View
    {
        $customer = $request->user()->customer;

        return view('portal.profile', compact('customer'));
    }

    protected function own(Request $request, CustomerPortalRequest $submission): void
    {
        abort_unless($submission->customer_id === $request->user()->customer_id, 404);
    }
}
