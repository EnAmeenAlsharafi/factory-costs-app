<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\QualityIncident;
use App\Services\ProductionCostService;
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

        $query = ProductionOrder::with(['customerOrder.customer', 'productModel', 'materialRequirements.material']);

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

        $query = ProductionWasteRecord::with(['productionOrder', 'material', 'wasteReason', 'responsibleDepartment', 'detectedDepartment']);

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
        $departments = Department::all();

        return view('production.reports.waste_analysis', compact('records', 'byReason', 'byDepartment', 'reasons', 'departments', 'canViewCost'));
    }

    public function qualitySummary(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $incidents = QualityIncident::with(['productionOrder', 'detectedDepartment', 'responsibleDepartment'])->get();

        $bySeverity = $incidents->groupBy('severity')->map->count();
        $byStatus = $incidents->groupBy('status')->map->count();
        $byDisposition = $incidents->groupBy(fn ($i) => $i->disposition ?? 'NOT_SET')->map->count();
        $byDepartment = $incidents->groupBy(fn ($i) => $i->responsibleDepartment?->name_ar ?? 'غير محدد')->map->count();

        return view('production.reports.quality_summary', compact('incidents', 'bySeverity', 'byStatus', 'byDisposition', 'byDepartment'));
    }
}
