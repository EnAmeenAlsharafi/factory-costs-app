<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationProgressRequest;
use App\Models\Department;
use App\Models\ProductionOrderOperation;
use App\Services\OperationalDashboardService;
use App\Services\ProductionOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentQueueController extends Controller
{
    /**
     * Queue tabs shown to workers: key => Arabic label.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'active' => 'كل المهام المفتوحة',
        'waiting' => 'بانتظار البدء',
        'in_progress' => 'قيد التنفيذ',
        'rework' => 'إعادة عمل',
        'completed_today' => 'أُنجزت اليوم',
    ];

    public function __construct(
        protected ProductionOrderService $productionOrderService,
        protected OperationalDashboardService $dashboardService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->can('production.view'), 403);

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'active';

        $tabCounts = [];
        foreach (array_keys(self::TABS) as $tabKey) {
            $tabCounts[$tabKey] = $this->filteredQuery($request, $tabKey)->count();
        }

        $operations = $this->filteredQuery($request, $tab)
            ->with([
                'productionOrder.customerOrder.customer',
                'productionOrder.productModel',
                'productionOrder.productConfiguration',
                'productionOrder.fabricMaterial',
                'productionOrder.fabricSupplier',
                'productionOrder.fabricColor',
                'workCenter.department',
                'targetReworkActions' => fn ($r) => $r->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->with('qualityIncident'),
            ])
            ->orderByRaw("CASE (SELECT priority FROM production_orders WHERE production_orders.id = production_order_operations.production_order_id) WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END")
            ->orderBy('sequence_number')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $departments = Department::production()->active()->orderBy('sort_order')->get();
        $tabs = self::TABS;

        return view('production.queue.index', compact('operations', 'departments', 'tab', 'tabs', 'tabCounts'));
    }

    /**
     * Operations for one queue tab, honouring department isolation and the priority/department filters.
     */
    private function filteredQuery(Request $request, string $tab): Builder
    {
        $user = $request->user();

        if ($tab === 'completed_today') {
            $query = ProductionOrderOperation::query()
                ->where('status', 'COMPLETED')
                ->whereDate('completed_at', today());

            if ($this->dashboardService->isDepartmentScoped($user)) {
                $query->whereHas('workCenter', fn ($wc) => $wc->where('department_id', $user->department_id));
            }
        } else {
            $query = $this->dashboardService->operationsQueryFor($user);

            match ($tab) {
                'waiting' => $query->where('status', 'READY'),
                'in_progress' => $query->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED']),
                'rework' => $query->whereIn('status', OperationalDashboardService::ACTIVE_OPERATION_STATUSES)
                    ->whereHas('targetReworkActions', fn ($r) => $r->whereNotIn('status', ['COMPLETED', 'CANCELLED'])),
                default => $query->whereIn('status', OperationalDashboardService::ACTIVE_OPERATION_STATUSES),
            };
        }

        if (! $this->dashboardService->isDepartmentScoped($user) && $request->filled('department_id')) {
            $query->whereHas('workCenter', fn ($wc) => $wc->where('department_id', $request->department_id));
        }

        if ($request->filled('priority')) {
            $query->whereHas('productionOrder', fn ($po) => $po->where('priority', $request->priority));
        }

        return $query;
    }

    public function updateProgress(OperationProgressRequest $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.update_progress'), 403);

        $this->productionOrderService->recordProgress(
            $operation,
            (int) $request->added_quantity,
            'PROGRESS',
            $request->notes,
            $request->user()
        );

        $operation->refresh();
        $remaining = max(0, $operation->required_quantity - $operation->completed_quantity);
        $message = $remaining === 0
            ? "تم تسجيل التقدم بنجاح واكتملت عملية «{$operation->operation_name_snapshot}»."
            : "تم تسجيل التقدم بنجاح. المنجز {$operation->completed_quantity} من {$operation->required_quantity} — المتبقي {$remaining}.";

        return redirect()->back()->with('success', $message);
    }

    public function correctProgress(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.correct_progress'), 403);

        $request->validate([
            'new_completed_quantity' => ['required', 'integer', 'min:0'],
            'correction_notes' => ['required', 'string', 'max:500'],
        ]);

        $this->productionOrderService->correctProgress(
            $operation,
            (int) $request->new_completed_quantity,
            $request->correction_notes,
            $request->user()
        );

        return redirect()->back()->with('success', 'تم تصحيح كمية الإنجاز بنجاح.');
    }
}
