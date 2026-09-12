<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionBoardController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('production.view'), 403);

        $departments = Department::where('is_production_department', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $activeOrdersCount = ProductionOrder::whereIn('status', ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count();
        $completedOrdersCount = ProductionOrder::where('status', 'COMPLETED')->count();
        $heldOrdersCount = ProductionOrder::where('status', 'ON_HOLD')->count();

        $departmentStats = [];

        foreach ($departments as $dept) {
            $ops = ProductionOrderOperation::whereHas('workCenter', fn ($wc) => $wc->where('department_id', $dept->id))
                ->whereHas('productionOrder', fn ($po) => $po->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED']))
                ->get();

            $departmentStats[$dept->id] = [
                'department' => $dept,
                'ready_count' => $ops->where('status', 'READY')->count(),
                'in_progress_count' => $ops->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count(),
                'completed_count' => $ops->where('status', 'COMPLETED')->count(),
                'total_required' => $ops->sum('required_quantity'),
                'total_completed' => $ops->sum('completed_quantity'),
            ];
        }

        $latestOrders = ProductionOrder::with(['customerOrder.customer', 'productModel', 'operations.workCenter.department'])
            ->whereNotIn('status', ['CANCELLED'])
            ->latest()
            ->limit(10)
            ->get();

        return view('production.board.index', compact(
            'departments',
            'activeOrdersCount',
            'completedOrdersCount',
            'heldOrdersCount',
            'departmentStats',
            'latestOrders'
        ));
    }
}
