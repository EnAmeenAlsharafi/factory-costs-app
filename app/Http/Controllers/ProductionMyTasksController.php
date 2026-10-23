<?php

namespace App\Http\Controllers;

use App\Services\OperationalDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employee «مهامي» view for production workers (spec §45–46): the operations
 * assigned to the signed-in worker plus the READY, unassigned tasks they may
 * claim in their own department. Reuses OperationalDashboardService's
 * department-isolation rule so the data stays consistent with the department queue.
 */
class ProductionMyTasksController extends Controller
{
    public function __construct(protected OperationalDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_if(
            ! $user->can('production.operation.start') && ! $user->can('production.operation.claim') && ! $user->can('production.assignment.view'),
            403
        );

        $priorityOrder = "CASE (SELECT priority FROM production_orders WHERE production_orders.id = production_order_operations.production_order_id) WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END";

        $myTasks = $this->dashboardService->operationsQueryFor($user)
            ->where('assigned_user_id', $user->id)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED', 'BLOCKED'])
            ->with([
                'productionOrder.customerOrder.customer',
                'productionOrder.productModel',
                'workCenter.department',
                'activeBlock',
                'targetReworkActions' => fn ($r) => $r->whereNotIn('status', ['COMPLETED', 'CANCELLED']),
            ])
            ->orderByRaw($priorityOrder)
            ->orderBy('sequence_number')
            ->get();

        $claimable = $user->can('production.operation.claim')
            ? $this->dashboardService->operationsQueryFor($user)
                ->where('status', 'READY')
                ->whereNull('assigned_user_id')
                ->with(['productionOrder.customerOrder.customer', 'productionOrder.productModel', 'workCenter.department'])
                ->orderByRaw($priorityOrder)
                ->orderBy('sequence_number')
                ->get()
            : collect();

        return view('production.my-tasks', compact('myTasks', 'claimable'));
    }
}
