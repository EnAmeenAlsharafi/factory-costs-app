<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionMaterialRequestFormRequest;
use App\Models\Department;
use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\ProductionMaterialRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionMaterialRequestController extends Controller
{
    public function __construct(
        protected ProductionMaterialRequestService $requestService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionMaterialRequest::with([
            'productionOrder',
            'requestedByUser',
            'requestedFromDepartment',
            'warehouse',
            'lines.material.baseUnit',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('productionOrder', fn ($po) => $po->where('production_order_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        return view('production.material_requests.index', compact('requests'));
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('production.material_requests'), 403);

        $orderId = $request->query('production_order_id');
        $productionOrder = ProductionOrder::with('materialRequirements.material.baseUnit', 'recipeVersion.items.material.baseUnit')->findOrFail($orderId);

        // Ensure requirements are generated if missing
        if ($productionOrder->materialRequirements()->count() === 0 && $productionOrder->recipeVersion) {
            $this->requestService->createMaterialRequirementsFromRecipe($productionOrder);
            $productionOrder->load('materialRequirements.material.baseUnit');
        }

        $warehouses = Warehouse::active()->where('code', 'RAW_MATERIALS')->get();
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::active()->orderBy('name_ar')->get();
        }

        $departments = Department::all();
        $materials = Material::with('baseUnit')->where('is_active', true)->get();
        $fabricColors = FabricColor::where('is_active', true)->get();
        $units = UnitOfMeasure::all();

        return view('production.material_requests.create', compact(
            'productionOrder',
            'warehouses',
            'departments',
            'materials',
            'fabricColors',
            'units'
        ));
    }

    public function store(ProductionMaterialRequestFormRequest $request): RedirectResponse
    {
        $productionOrder = ProductionOrder::findOrFail($request->production_order_id);

        $pmr = $this->requestService->createRequest($productionOrder, $request->user(), $request->validated());

        if ($request->input('action') === 'submit') {
            $this->requestService->submitRequest($pmr);

            return redirect()->route('production.material-requests.show', $pmr)
                ->with('success', "تم إنشاء وتقديم طلب المواد {$pmr->request_number} بنجاح.");
        }

        return redirect()->route('production.material-requests.show', $pmr)
            ->with('success', "تم حفظ مسودة طلب المواد {$pmr->request_number} بنجاح.");
    }

    public function show(Request $request, ProductionMaterialRequest $materialRequest): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $materialRequest->load([
            'productionOrder.productModel',
            'productionOrder.productConfiguration',
            'requestedByUser',
            'reviewedByUser',
            'requestedFromDepartment',
            'warehouse',
            'lines.material.baseUnit',
            'lines.fabricColor',
            'lines.requirement',
            'materialIssues.lines.lot',
            'materialIssues.issuedByUser',
        ]);

        return view('production.material_requests.show', compact('materialRequest'));
    }

    public function submit(Request $request, ProductionMaterialRequest $materialRequest): RedirectResponse
    {
        abort_if(! $request->user()->can('production.material_requests'), 403);

        $this->requestService->submitRequest($materialRequest);

        return redirect()->back()->with('success', "تم تقديم طلب المواد {$materialRequest->request_number} للمستودع.");
    }

    public function fulfillForm(Request $request, ProductionMaterialRequest $materialRequest): View
    {
        abort_if(! $request->user()->can('inventory.issue'), 403);

        $materialRequest->load([
            'productionOrder',
            'warehouse',
            'lines.material.baseUnit',
            'lines.fabricColor',
        ]);

        // Load active lots with remaining quantity > 0 for materials in this request
        $materialIds = $materialRequest->lines->pluck('material_id')->unique();
        $lots = InventoryLot::whereIn('material_id', $materialIds)
            ->where('warehouse_id', $materialRequest->warehouse_id)
            ->where('remaining_quantity', '>', 0)
            ->where('status', 'ACTIVE')
            ->with(['material.baseUnit', 'fabricColor', 'supplier'])
            ->orderBy('received_date', 'asc') // FIFO order
            ->get();

        return view('production.material_requests.fulfill', compact('materialRequest', 'lots'));
    }

    public function fulfill(Request $request, ProductionMaterialRequest $materialRequest): RedirectResponse
    {
        abort_if(! $request->user()->can('inventory.issue'), 403);

        $request->validate([
            'fulfillments' => 'required|array|min:1',
            'fulfillments.*.request_line_id' => 'required|exists:production_material_request_lines,id',
            'fulfillments.*.inventory_lot_id' => 'required|exists:inventory_lots,id',
            'fulfillments.*.quantity' => 'required|numeric|gt:0',
            'fulfillments.*.notes' => 'nullable|string|max:500',
        ]);

        $issue = $this->requestService->fulfillRequest($materialRequest, $request->user(), $request->input('fulfillments'));

        return redirect()->route('production.material-requests.show', $materialRequest)
            ->with('success', "تم صرف المواد بنجاح وبناء سند الصرف رقم {$issue->issue_number}.");
    }
}
