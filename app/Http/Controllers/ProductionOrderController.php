<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionOrderRequest;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ProductionOrder;
use App\Models\ProductionRouting;
use App\Services\ProductionCostService;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(protected ProductionOrderService $productionOrderService) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionOrder::with([
            'customerOrder.customer',
            'productModel',
            'productConfiguration',
            'recipeVersion',
            'routing',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('production_order_number', 'like', "%{$search}%")
                    ->orWhere('custom_design_name', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', fn ($co) => $co->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('customerOrder.customer', fn ($c) => $c->where('name_ar', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('production.orders.index', compact('orders'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('production.release'), 403);

        $lineId = $request->query('line_id');
        $orderLine = null;

        if ($lineId) {
            $orderLine = CustomerOrderLine::with(['customerOrder.customer', 'productModel', 'productConfiguration', 'fabricMaterial', 'fabricColor'])->find($lineId);
        }

        $approvedOrders = CustomerOrder::where('status', 'APPROVED_FOR_PRODUCTION')
            ->with(['customer', 'lines' => fn ($query) => $query
                ->with('productModel')
                ->withSum([
                    'productionOrders as released_quantity_sum' => fn ($productionOrders) => $productionOrders
                        ->where('status', '!=', 'CANCELLED'),
                ], 'released_quantity')])
            ->get();

        $routings = ProductionRouting::where('is_active', true)->get();

        return view('production.orders.create', compact('orderLine', 'approvedOrders', 'routings'));
    }

    public function store(ProductionOrderRequest $request): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release'), 403);

        $line = CustomerOrderLine::findOrFail($request->customer_order_line_id);

        $po = $this->productionOrderService->createFromOrderLine($line, $request->validated());

        if ($request->filled('production_routing_id')) {
            $routing = ProductionRouting::findOrFail($request->production_routing_id);
            $this->productionOrderService->releaseProductionOrder($po, $routing, $request->user());

            return redirect()->route('production.orders.show', $po)
                ->with('success', "تم إنشاء وإطلاق أمر الإنتاج {$po->production_order_number} بنجاح.");
        }

        return redirect()->route('production.orders.show', $po)
            ->with('success', "تم إنشاء مسودة أمر الإنتاج {$po->production_order_number} بنجاح.");
    }

    public function show(Request $request, ProductionOrder $order): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $order->load([
            'customerOrder.customer',
            'customerOrderLine',
            'productModel',
            'productConfiguration',
            'recipeVersion.items.material.baseUnit',
            'customerProductAlias',
            'fabricMaterial',
            'fabricColor',
            'releasedByUser',
            'routing',
            'operations.workCenter.department',
            'operations.progressLogs.user',
            'materialRequirements.material.baseUnit',
            'materialRequests.lines.material.baseUnit',
            'materialRequests.requestedFromDepartment',
            'materialRequests.warehouse',
            'materialIssues.lines.material.baseUnit',
            'materialIssues.lines.lot',
            'materialReturns.lines.material.baseUnit',
            'qualityIncidents.detectedDepartment',
            'qualityIncidents.responsibleDepartment',
            'reworkActions.assignedDepartment',
            'wasteRecords.material.baseUnit',
            'wasteRecords.wasteReason',
        ]);

        $costService = app(ProductionCostService::class);
        $costSummary = $costService->calculateOrderMaterialCost($order);
        $canViewCost = $request->user()->can('costing.view');

        return view('production.orders.show', compact('order', 'costSummary', 'canViewCost'));
    }

    public function releaseForm(Request $request, ProductionOrder $order): View
    {
        abort_if(! $request->user()->can('production.release'), 403);

        $routings = ProductionRouting::where('is_active', true)->with('operations.workCenter')->get();

        return view('production.orders.release', compact('order', 'routings'));
    }

    public function release(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release'), 403);

        $request->validate([
            'production_routing_id' => ['required', 'exists:production_routings,id'],
        ]);

        $routing = ProductionRouting::findOrFail($request->production_routing_id);

        $this->productionOrderService->releaseProductionOrder($order, $routing, $request->user());

        return redirect()->route('production.orders.show', $order)
            ->with('success', "تم إطلاق أمر الإنتاج {$order->production_order_number} بنجاح للورشة.");
    }

    public function hold(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.hold'), 403);

        $request->validate([
            'hold_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->productionOrderService->holdOrder($order, $request->hold_reason);

        return redirect()->back()->with('success', 'تم تعليق أمر الإنتاج بنجاح.');
    }

    public function resume(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.hold'), 403);

        $this->productionOrderService->resumeOrder($order);

        return redirect()->back()->with('success', 'تم استئناف أمر الإنتاج بنجاح.');
    }

    public function cancel(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release') && ! $request->user()->isAdministrator(), 403);

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->productionOrderService->cancelOrder($order, $request->cancellation_reason, $request->user());

        return redirect()->route('production.orders.index')->with('success', 'تم إلغاء أمر الإنتاج.');
    }
}
