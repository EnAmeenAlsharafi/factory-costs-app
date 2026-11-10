<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionOrderRequest;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\ProductionRouting;
use App\Models\ProductionWasteRecord;
use App\Models\QualityIncident;
use App\Models\User;
use App\Services\OrderTimelineService;
use App\Services\ProductionCostService;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        protected ProductionOrderService $productionOrderService,
        protected OrderTimelineService $timelineService,
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionOrder::visibleTo($request->user())->with([
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
                    ->orWhereHas('customerOrder.customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
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
        $user = $request->user();
        abort_if(! $user->can('production.view'), 403);
        abort_unless(ProductionOrder::visibleTo($user)->whereKey($order->id)->exists(), 404);

        $order->load([
            'customerOrder.customer',
            'customerOrderLine',
            'productModel',
            'productConfiguration',
            'recipeVersion.recipe',
            'recipeVersion.items.material.baseUnit',
            'customerProductAlias',
            'fabricMaterial',
            'fabricColor.supplierCatalogColors.catalog',
            'releasedByUser',
            'routing',
            'operations.workCenter.department',
            'operations.progressLogs.user',
            'operations.assignedUser',
            'operations.activeBlock',
            'operations.targetReworkActions' => fn ($r) => $r->whereNotIn('status', ['COMPLETED', 'CANCELLED']),
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

        if ($user->isDepartmentRestricted()) {
            $this->limitToDepartment($order, $user);
        }

        $costService = app(ProductionCostService::class);
        $costSummary = $costService->calculateOrderMaterialCost($order);
        $canViewCost = $request->user()->can('costing.view');
        $timeline = $this->timelineService->forProductionOrder($order, viewer: $user);

        $recipeChoices = $user->can('production.release') && ! in_array($order->status, ['COMPLETED', 'CANCELLED'], true) && $order->materialIssues->isEmpty()
            ? ManufacturingRecipeVersion::where('status', 'APPROVED')
                ->with('recipe')
                ->get()
                ->sortBy(fn (ManufacturingRecipeVersion $version) => [
                    $version->recipe?->product_configuration_id && $version->recipe->product_configuration_id === $order->product_configuration_id ? 0 : 1,
                    $version->recipe?->name,
                ])
                ->values()
            : null;

        $assignableEmployees = $user->can('production.assignment.manage')
            ? $this->employeesForOperations($order, $user)
            : null;

        return view('production.orders.show', compact('order', 'costSummary', 'canViewCost', 'timeline', 'assignableEmployees', 'recipeChoices'));
    }

    /**
     * Active workers of the departments that own this order's operations, with their open task count.
     *
     * @return Collection<int, Collection<int, User>>
     */
    private function employeesForOperations(ProductionOrder $order, User $user): Collection
    {
        $departmentIds = $order->operations->pluck('workCenter.department_id')->filter()->unique()
            ->when($user->isDepartmentRestricted(), fn (Collection $ids) => $ids->intersect([$user->productionDepartmentId()]));

        return User::query()
            ->where('is_active', true)
            ->whereIn('department_id', $departmentIds)
            ->withCount(['assignedOperations as open_tasks' => fn ($q) => $q->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])])
            ->orderBy('name')
            ->get()
            ->groupBy('department_id');
    }

    /**
     * Keep only the operations, quality incidents, rework and waste of the user's own department on the order page.
     */
    private function limitToDepartment(ProductionOrder $order, User $user): void
    {
        $visible = [
            'operations' => ProductionOrderOperation::class,
            'qualityIncidents' => QualityIncident::class,
            'reworkActions' => ProductionReworkAction::class,
            'wasteRecords' => ProductionWasteRecord::class,
        ];

        foreach ($visible as $relation => $model) {
            $visibleIds = $model::visibleTo($user)->where('production_order_id', $order->id)->pluck('id');
            $order->setRelation($relation, $order->{$relation}->whereIn('id', $visibleIds)->values());
        }
    }

    public function linkRecipe(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release'), 403);

        $data = $request->validate([
            'manufacturing_recipe_version_id' => ['required', 'exists:manufacturing_recipe_versions,id'],
        ], ['manufacturing_recipe_version_id.required' => 'اختر الوصفة.']);

        $version = ManufacturingRecipeVersion::with('recipe')->findOrFail($data['manufacturing_recipe_version_id']);
        $order = $this->productionOrderService->linkRecipe($order, $version);

        return redirect()->route('production.orders.show', $order)
            ->with('success', "تم ربط الوصفة «{$version->recipe?->name}» بأمر الإنتاج، وحُسبت خاماته ({$order->materialRequirements->count()} خامة).");
    }

    /**
     * Points category for the bed this order makes (custom and showroom designs, or a model without one).
     * Saved on the customer order line, so every production order of that line earns the same points.
     */
    public function setPointCategory(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release'), 403);
        $data = $request->validate(['point_category_id' => ['nullable', 'exists:production_point_categories,id']]);
        abort_unless($order->customerOrderLine, 422, 'أمر الإنتاج غير مرتبط ببند طلب عميل.');

        $order->customerOrderLine->update(['point_category_id' => $data['point_category_id'] ?? null]);

        return redirect()->route('production.orders.show', $order)
            ->with('success', 'تم تحديث تصنيف النقاط. يُحتسب على الإنجاز المسجل من الآن؛ ولما سُجّل سابقاً استخدم «احتساب أعمال بلا تصنيف» في إعدادات النقاط.');
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
