<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Production Control Board (spec §39–40, §71, §129): a matrix of active
 * production orders against department/stage columns, with attention counts
 * (ready-unassigned, in-progress, blocked, delayed, rework) and SLA/delay
 * highlighting derived from routing_operations.expected_minutes.
 */
class ProductionBoardController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->can('production.view'), 403);

        $departments = Department::where('is_production_department', true)
            ->where('is_active', true)
            ->when($user->isDepartmentRestricted(), fn ($q) => $q->whereKey($user->productionDepartmentId()))
            ->orderBy('sort_order')
            ->get();

        $activeOrdersCount = ProductionOrder::whereIn('status', self::ACTIVE_ORDER_STATUSES)->count();
        $completedOrdersCount = ProductionOrder::where('status', 'COMPLETED')->count();
        $heldOrdersCount = ProductionOrder::where('status', 'ON_HOLD')->count();

        $activeOperations = ProductionOrderOperation::whereHas(
            'productionOrder',
            fn ($po) => $po->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED'])
        )->when(
            $user->isDepartmentRestricted(),
            fn ($q) => $q->whereHas('workCenter', fn ($wc) => $wc->where('department_id', $user->productionDepartmentId()))
        )->with('workCenter.department', 'routingOperation')->get();

        $departmentStats = [];

        foreach ($departments as $dept) {
            $ops = $activeOperations->filter(fn (ProductionOrderOperation $op) => $op->workCenter?->department_id === $dept->id);

            $departmentStats[$dept->id] = [
                'department' => $dept,
                'ready_count' => $ops->where('status', 'READY')->count(),
                'ready_unassigned_count' => $ops->where('status', 'READY')->whereNull('assigned_user_id')->count(),
                'in_progress_count' => $ops->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count(),
                'blocked_count' => $ops->where('status', 'BLOCKED')->count(),
                'completed_count' => $ops->where('status', 'COMPLETED')->count(),
                'delayed_count' => $ops->filter(fn (ProductionOrderOperation $op) => $this->isDelayed($op))->count(),
                'total_required' => $ops->sum('required_quantity'),
                'total_completed' => $ops->sum('completed_quantity'),
            ];
        }

        // Attention counts (spec §129) — the headline numbers the board exists to surface.
        $attention = [
            'ready_unassigned' => $activeOperations->where('status', 'READY')->whereNull('assigned_user_id')->count(),
            'in_progress' => $activeOperations->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count(),
            'blocked' => $activeOperations->where('status', 'BLOCKED')->count(),
            'delayed' => $activeOperations->filter(fn (ProductionOrderOperation $op) => $this->isDelayed($op))->count(),
            'open_rework' => ProductionReworkAction::whereNotIn('status', ['COMPLETED', 'CANCELLED'])->count(),
        ];

        // Matrix: active production orders (bounded + paginated, spec §71) × department columns.
        $matrixOrders = ProductionOrder::with(['customerOrder.customer', 'productModel', 'operations.workCenter.department', 'operations.activeBlock'])
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->orderByRaw("CASE priority WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(15)
            ->withQueryString();

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
            'attention',
            'matrixOrders',
            'latestOrders'
        ));
    }

    /**
     * An operation is "delayed" once it has run longer than its routing's
     * expected_minutes SLA without completing (spec §38–40).
     */
    private function isDelayed(ProductionOrderOperation $op): bool
    {
        $expectedMinutes = $op->routingOperation?->expected_minutes;

        if (! $expectedMinutes || ! $op->started_at || in_array($op->status, ['COMPLETED', 'SKIPPED'], true)) {
            return false;
        }

        return $op->started_at->diffInMinutes(now()) > $expectedMinutes;
    }
}
