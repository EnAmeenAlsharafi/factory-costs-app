<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderPaymentEligibilityService;
use App\Services\ProductionPipelineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * One screen that moves orders through the production stages in batches:
 * approve → release → request materials → assign to departments.
 */
class ProductionPipelineController extends Controller
{
    public const STAGES = ['approve', 'release', 'materials', 'assign'];

    /**
     * @var array<string, string>
     */
    private const STAGE_PERMISSIONS = [
        'approve' => 'orders.approve_production',
        'release' => 'production.release',
        'materials' => 'production.material_requests',
        'assign' => 'production.assignment.manage',
    ];

    public function __construct(protected ProductionPipelineService $pipeline) {}

    public function index(Request $request, ?string $stage = null): View|RedirectResponse
    {
        $user = $request->user();
        $allowedStages = collect(self::STAGES)->filter(fn (string $key) => $user->can(self::STAGE_PERMISSIONS[$key]))->values();
        abort_if($allowedStages->isEmpty(), 403);

        if ($stage === null || ! $allowedStages->contains($stage)) {
            // Open on the first stage that has work waiting, so the user lands where action is needed.
            $firstBusy = $allowedStages->first(fn (string $key) => $this->stageQuery($key, $user)->count() > 0);

            return redirect()->route('production.pipeline.index', $firstBusy ?? $allowedStages->first());
        }

        $counts = $allowedStages->mapWithKeys(fn (string $key) => [$key => $this->stageQuery($key, $user)->count()]);
        $search = trim((string) $request->query('search'));

        $data = match ($stage) {
            'approve' => $this->approveData($user, $search),
            'release' => $this->releaseData($user, $search),
            'materials' => $this->materialsData($user, $search),
            'assign' => $this->assignData($request, $user, $search),
        };

        return view('production.pipeline.index', array_merge($data, [
            'stage' => $stage,
            'allowedStages' => $allowedStages,
            'counts' => $counts,
            'search' => $search,
        ]));
    }

    public function approve(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('orders.approve_production'), 403);
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ], ['order_ids.required' => 'اختر طلباً واحداً على الأقل.']);

        $result = $this->pipeline->approveOrders($data['order_ids'], $request->user());

        return $this->finish('approve', 'اعتماد للإنتاج', $result);
    }

    public function release(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('production.release'), 403);
        $data = $request->validate([
            'line_ids' => ['array'],
            'line_ids.*' => ['integer'],
            'production_order_ids' => ['array'],
            'production_order_ids.*' => ['integer'],
            'production_routing_id' => ['required', 'exists:production_routings,id'],
            'priority' => ['required', 'in:NORMAL,URGENT,VIP'],
        ], ['production_routing_id.required' => 'اختر مسار التصنيع.']);

        if (empty($data['line_ids']) && empty($data['production_order_ids'])) {
            return back()->withErrors(['line_ids' => 'اختر بنداً واحداً على الأقل.']);
        }

        $request->session()->put('pipeline.routing_id', (int) $data['production_routing_id']);
        $result = $this->pipeline->release(
            $data['line_ids'] ?? [],
            $data['production_order_ids'] ?? [],
            ProductionRouting::findOrFail($data['production_routing_id']),
            $data['priority'],
            $request->user(),
        );

        return $this->finish('release', 'إطلاق للورشة', $result);
    }

    public function requestMaterials(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('production.material_requests'), 403);
        $data = $request->validate([
            'production_order_ids' => ['required', 'array', 'min:1'],
            'production_order_ids.*' => ['integer'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ], [
            'production_order_ids.required' => 'اختر أمر إنتاج واحداً على الأقل.',
            'warehouse_id.required' => 'اختر المستودع.',
        ]);

        $request->session()->put('pipeline.warehouse_id', (int) $data['warehouse_id']);
        $result = $this->pipeline->requestMaterials($data['production_order_ids'], Warehouse::findOrFail($data['warehouse_id']), $request->user());

        return $this->finish('materials', 'طلب المواد من المستودع', $result);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('production.assignment.manage'), 403);
        $data = $request->validate([
            'operation_ids' => ['required', 'array', 'min:1'],
            'operation_ids.*' => ['integer'],
            'mode' => ['required', 'in:employee,auto'],
            'employee_id' => ['required_if:mode,employee', 'nullable', 'exists:users,id'],
        ], [
            'operation_ids.required' => 'اختر عملية واحدة على الأقل.',
            'employee_id.required_if' => 'اختر الموظف أو استخدم التوزيع التلقائي.',
        ]);

        $employee = $data['mode'] === 'employee' ? User::findOrFail($data['employee_id']) : null;
        $result = $this->pipeline->assignOperations($data['operation_ids'], $employee, $request->user());

        return $this->finish('assign', 'إسناد للأقسام', $result);
    }

    /**
     * @param  array{done: array<int, string>, skipped: array<string, string>}  $result
     */
    private function finish(string $stage, string $actionLabel, array $result): RedirectResponse
    {
        return redirect()->route('production.pipeline.index', $stage)
            ->with('pipeline_result', ['action' => $actionLabel] + $result);
    }

    private function stageQuery(string $stage, User $user): Builder
    {
        return match ($stage) {
            'approve' => CustomerOrder::query()->where('status', 'PENDING_PRODUCTION_REVIEW'),
            'release' => $this->releasableLinesQuery(),
            'materials' => ProductionOrder::visibleTo($user)
                ->whereIn('status', ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])
                ->whereDoesntHave('materialRequests', fn (Builder $q) => $q->where('status', '!=', 'REJECTED')),
            'assign' => $this->assignableOperationsQuery($user),
        };
    }

    private function releasableLinesQuery(): Builder
    {
        return CustomerOrderLine::query()
            ->where('line_type', 'MANUFACTURED')
            ->whereHas('customerOrder', fn (Builder $q) => $q->where('status', 'APPROVED_FOR_PRODUCTION'))
            ->whereRaw('quantity > (SELECT COALESCE(SUM(released_quantity), 0) FROM production_orders WHERE production_orders.customer_order_line_id = customer_order_lines.id AND production_orders.status != ?)', ['CANCELLED']);
    }

    private function assignableOperationsQuery(User $user): Builder
    {
        return ProductionOrderOperation::visibleTo($user)
            ->where('production_order_operations.status', 'READY')
            ->whereNull('production_order_operations.assigned_user_id')
            ->whereHas('productionOrder', fn (Builder $q) => $q->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED']));
    }

    /**
     * @return array<string, mixed>
     */
    private function approveData(User $user, string $search): array
    {
        $orders = $this->stageQuery('approve', $user)
            ->with('customer')
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->withPaymentTotals()
            ->with('paymentOverrides', 'customer.creditProfile')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"))))
            ->orderBy('requested_delivery_date')
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        $eligibility = app(OrderPaymentEligibilityService::class);
        $paymentHolds = $orders->mapWithKeys(fn (CustomerOrder $order) => [$order->id => ! $eligibility->checkProductionEligibility($order)['eligible']]);

        return compact('orders', 'paymentHolds');
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseData(User $user, string $search): array
    {
        $lines = $this->releasableLinesQuery()
            ->with(['customerOrder.customer', 'productModel'])
            ->when($search !== '', fn (Builder $q) => $q->whereHas('customerOrder', fn (Builder $o) => $o
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"))))
            ->limit(100)
            ->get()
            ->each(fn (CustomerOrderLine $line) => $line->setAttribute('remaining_quantity', $this->pipeline->remainingToRelease($line)))
            ->sortBy(fn (CustomerOrderLine $line) => $line->customerOrder->requested_delivery_date ?? now()->addYears(5))
            ->values();

        $draftOrders = ProductionOrder::with(['customerOrder.customer', 'productModel'])
            ->whereIn('status', ['DRAFT', 'READY_FOR_RELEASE'])
            ->whereHas('customerOrder', fn (Builder $q) => $q->where('status', 'APPROVED_FOR_PRODUCTION'))
            ->latest()
            ->limit(100)
            ->get();

        $routings = ProductionRouting::where('is_active', true)->withCount('operations')->orderBy('name_ar')->get();
        $selectedRoutingId = session('pipeline.routing_id') ?? ($routings->count() === 1 ? $routings->first()->id : null);

        return compact('lines', 'draftOrders', 'routings', 'selectedRoutingId');
    }

    /**
     * @return array<string, mixed>
     */
    private function materialsData(User $user, string $search): array
    {
        $productionOrders = $this->stageQuery('materials', $user)
            ->with(['customerOrder.customer', 'productModel'])
            ->withCount('materialRequirements')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('production_order_number', 'like', "%{$search}%")
                ->orWhereHas('customerOrder', fn (Builder $o) => $o->where('order_number', 'like', "%{$search}%"))))
            ->orderByRaw("CASE priority WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END")
            ->oldest('released_at')
            ->limit(100)
            ->get();

        $warehouses = Warehouse::active()->orderBy('name_ar')->get();
        $selectedWarehouseId = session('pipeline.warehouse_id')
            ?? $warehouses->firstWhere('code', 'RAW_MATERIALS')?->id
            ?? $warehouses->first()?->id;

        return compact('productionOrders', 'warehouses', 'selectedWarehouseId');
    }

    /**
     * @return array<string, mixed>
     */
    private function assignData(Request $request, User $user, string $search): array
    {
        $departmentId = $user->isDepartmentRestricted() ? $user->productionDepartmentId() : ($request->integer('department_id') ?: null);

        $operations = $this->assignableOperationsQuery($user)
            ->with(['productionOrder.customerOrder.customer', 'productionOrder.productModel', 'workCenter.department'])
            ->when($departmentId, fn (Builder $q) => $q->whereHas('workCenter', fn (Builder $w) => $w->where('department_id', $departmentId)))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('productionOrder', fn (Builder $p) => $p
                ->where('production_order_number', 'like', "%{$search}%")
                ->orWhereHas('customerOrder', fn (Builder $o) => $o->where('order_number', 'like', "%{$search}%"))))
            ->orderByRaw("CASE (SELECT priority FROM production_orders WHERE production_orders.id = production_order_operations.production_order_id) WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END")
            ->orderBy('ready_at')
            ->limit(150)
            ->get();

        $departments = Department::production()->active()
            ->when($user->isDepartmentRestricted(), fn (Builder $q) => $q->whereKey($user->productionDepartmentId()))
            ->orderBy('sort_order')
            ->get();

        /** @var Collection<int, User> $employees */
        $employees = User::query()
            ->where('is_active', true)
            ->whereIn('department_id', $departments->pluck('id'))
            ->withCount(['assignedOperations as open_tasks' => fn ($q) => $q->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])])
            ->orderBy('name')
            ->get();

        $readyByDepartment = $this->assignableOperationsQuery($user)
            ->join('work_centers', 'work_centers.id', '=', 'production_order_operations.work_center_id')
            ->selectRaw('work_centers.department_id, COUNT(*) as total')
            ->groupBy('work_centers.department_id')
            ->pluck('total', 'department_id');

        return compact('operations', 'departments', 'employees', 'departmentId', 'readyByDepartment');
    }
}
