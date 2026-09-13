<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerReturnFormRequest;
use App\Models\CustomerReturn;
use App\Models\ProductionOrder;
use App\Models\QualityIncident;
use App\Services\CustomerReturnService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReturnController extends Controller
{
    public function __construct(
        protected CustomerReturnService $returnService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $query = CustomerReturn::with([
            'customerOrder.customer',
            'productionOrder',
            'deliveryOrder',
            'reportedByUser',
            'receivedByUser',
            'qualityIncident',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', fn ($co) => $co->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('productionOrder', fn ($po) => $po->where('production_order_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('reason_code')) {
            $query->where('reason_code', $request->reason_code);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $customerReturns = $query->latest('reported_at')->paginate(15)->withQueryString();

        return view('customer_returns.index', compact('customerReturns'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('delivery.manage_returns'), 403);

        $orderId = $request->query('production_order_id');
        $productionOrder = ProductionOrder::with('customerOrder.customer', 'customerOrderLine.productModel')->findOrFail($orderId);

        $netDelivered = $this->returnService->getNetDeliveredQuantity($productionOrder);

        return view('customer_returns.create', compact('productionOrder', 'netDelivered'));
    }

    public function store(CustomerReturnFormRequest $request): RedirectResponse
    {
        $productionOrder = ProductionOrder::findOrFail($request->production_order_id);

        try {
            $return = $this->returnService->reportReturn($productionOrder, $request->user(), $request->validated());

            return redirect()->route('customer-returns.show', $return)
                ->with('success', "تم تسجيل بلاغ إرجاع العميل رقم {$return->return_number} بنجاح.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(CustomerReturn $customerReturn, Request $request): View
    {
        abort_if(! $request->user()->can('delivery.view'), 403);

        $customerReturn->load([
            'customerOrder.customer',
            'deliveryOrder',
            'productionOrder.customerOrderLine.productModel',
            'reportedByUser',
            'receivedByUser',
            'qualityIncident.responsibleDepartment',
        ]);

        $return = $customerReturn;

        return view('customer_returns.show', compact('return'));
    }

    public function receive(CustomerReturn $customerReturn, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.manage_returns'), 403);

        $request->validate([
            'condition_code' => ['nullable', 'string', 'in:GOOD,NEEDS_INSPECTION,DAMAGED,SCRAP_CANDIDATE'],
        ]);

        try {
            $this->returnService->receiveReturnedGoods($customerReturn, $request->user(), $request->input('condition_code'));

            return back()->with('success', "تم إيداع واستلام المرتجع رقم {$customerReturn->return_number} بالمخزون بنجاح.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function linkQuality(CustomerReturn $customerReturn, Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('delivery.manage_returns'), 403);

        $request->validate([
            'quality_incident_id' => ['required', 'exists:quality_incidents,id'],
        ]);

        try {
            $incident = QualityIncident::findOrFail($request->quality_incident_id);
            $this->returnService->linkQualityIncident($customerReturn, $incident);

            return back()->with('success', "تم ربط بلاغ المرتجع بحادثة الجودة رقم {$incident->incident_number} بنجاح.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
