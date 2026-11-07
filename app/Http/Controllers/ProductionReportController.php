<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\QualityIncident;
use App\Models\User;
use App\Services\ProductionCostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReportController extends Controller
{
    public function __construct(
        protected ProductionCostService $costService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        return view('production.reports.index');
    }

    public function actualVsPlannedCost(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);
        $canViewCost = $request->user()->can('costing.view');
        abort_if(! $canViewCost, 403, 'غير مصرح لك بعرض تقارير التكلفة.');

        $query = ProductionOrder::visibleTo($request->user())->with(['customerOrder.customer', 'productModel', 'materialRequirements.material']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        $costReports = [];
        foreach ($orders as $order) {
            $costReports[$order->id] = $this->costService->calculateOrderMaterialCost($order);
        }

        return view('production.reports.actual_vs_planned', compact('orders', 'costReports', 'canViewCost'));
    }

    public function wasteAnalysis(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);
        $canViewCost = $request->user()->can('costing.view');

        $query = ProductionWasteRecord::visibleTo($request->user())->with(['productionOrder', 'material', 'wasteReason', 'responsibleDepartment', 'detectedDepartment']);

        if ($request->filled('reason_id')) {
            $query->where('waste_reason_id', $request->reason_id);
        }

        if ($request->filled('department_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('detected_department_id', $request->department_id)
                    ->orWhere('responsible_department_id', $request->department_id);
            });
        }

        $records = $query->latest('occurred_at')->get();

        $byReason = $records->groupBy('wasteReason.name_ar')->map(fn ($group) => [
            'count' => $group->count(),
            'total_cost' => $group->sum('total_cost'),
            'total_qty' => $group->sum('quantity'),
        ]);

        $byDepartment = $records->groupBy(fn ($item) => $item->responsibleDepartment?->name_ar ?? 'غير محدد')->map(fn ($group) => [
            'count' => $group->count(),
            'total_cost' => $group->sum('total_cost'),
        ]);

        $reasons = ProductionWasteReason::all();
        $departments = $this->departmentsFor($request->user(), Department::query());

        return view('production.reports.waste_analysis', compact('records', 'byReason', 'byDepartment', 'reasons', 'departments', 'canViewCost'));
    }

    public function qualitySummary(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $incidents = QualityIncident::visibleTo($request->user())->with(['productionOrder', 'detectedDepartment', 'responsibleDepartment'])->get();

        $bySeverity = $incidents->groupBy('severity')->map->count();
        $byStatus = $incidents->groupBy('status')->map->count();
        $byDisposition = $incidents->groupBy(fn ($i) => $i->disposition ?? 'NOT_SET')->map->count();
        $byDepartment = $incidents->groupBy(fn ($i) => $i->responsibleDepartment?->name_ar ?? 'غير محدد')->map->count();

        return view('production.reports.quality_summary', compact('incidents', 'bySeverity', 'byStatus', 'byDisposition', 'byDepartment'));
    }

    public function employeeProductivity(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $query = ProductionOrderOperation::visibleTo($request->user())->with(['assignedUser', 'workCenter.department'])
            ->whereNotNull('assigned_user_id');

        if ($request->filled('department_id')) {
            $query->whereHas('workCenter', fn ($wc) => $wc->where('department_id', $request->department_id));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('assigned_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('assigned_at', '<=', $request->to_date);
        }

        $operations = $query->get();

        $byEmployee = $operations->groupBy('assigned_user_id')->map(function ($group) {
            return [
                'employee' => $group->first()->assignedUser,
                'assigned_count' => $group->count(),
                'completed_quantity' => $group->sum('completed_quantity'),
                'in_progress_count' => $group->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count(),
                'blocked_count' => $group->where('status', 'BLOCKED')->count(),
            ];
        })->sortByDesc('completed_quantity');

        $departments = $this->departmentsFor($request->user(), Department::where('is_production_department', true));

        return view('production.reports.employee_productivity', compact('byEmployee', 'departments'));
    }

    /**
     * Department filter options: a department-restricted user only gets their own department.
     *
     * @param  Builder<Department>  $query
     * @return Collection<int, Department>
     */
    private function departmentsFor(User $user, Builder $query): Collection
    {
        return $query
            ->when($user->isDepartmentRestricted(), fn ($q) => $q->whereKey($user->productionDepartmentId()))
            ->get();
    }
}
