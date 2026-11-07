<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Production Manager assignment board (spec §33): READY/ASSIGNED operations
 * awaiting or holding an assignment, filterable by department/product/priority,
 * alongside each department employee's current workload (active + ready counts,
 * no scoring — spec §77).
 */
class ProductionAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->can('production.assignment.view'), 403);

        // Department-restricted users (e.g. a department supervisor) only see their own department.
        $departmentId = $user->isDepartmentRestricted() ? $user->productionDepartmentId() : $request->input('department_id');

        $query = ProductionOrderOperation::query()
            ->whereHas('productionOrder', fn ($po) => $po->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED']))
            ->whereIn('status', ['READY', 'ASSIGNED'])
            ->with(['productionOrder.customerOrder.customer', 'productionOrder.productModel', 'workCenter.department', 'assignedUser']);

        if (filled($departmentId)) {
            $query->whereHas('workCenter', fn ($wc) => $wc->where('department_id', $departmentId));
        }

        if ($request->filled('priority')) {
            $query->whereHas('productionOrder', fn ($po) => $po->where('priority', $request->priority));
        }

        if ($request->filled('product_model_id')) {
            $query->whereHas('productionOrder', fn ($po) => $po->where('product_model_id', $request->product_model_id));
        }

        if ($request->boolean('unassigned_only')) {
            $query->whereNull('assigned_user_id');
        }

        $operations = $query
            ->orderByRaw("CASE (SELECT priority FROM production_orders WHERE production_orders.id = production_order_operations.production_order_id) WHEN 'VIP' THEN 0 WHEN 'URGENT' THEN 1 ELSE 2 END")
            ->orderBy('sequence_number')
            ->paginate(25)
            ->withQueryString();

        $departments = Department::production()->active()
            ->when($user->isDepartmentRestricted(), fn ($q) => $q->whereKey($user->productionDepartmentId()))
            ->orderBy('sort_order')
            ->get();

        $employeesQuery = User::query()
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->whereHas('department', fn ($q) => $q->where('is_production_department', true))
            ->withCount([
                'assignedOperations as active_tasks_count' => fn ($q) => $q->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED']),
                'assignedOperations as ready_tasks_count' => fn ($q) => $q->where('status', 'READY'),
            ]);

        if (filled($departmentId)) {
            $employeesQuery->where('department_id', $departmentId);
        }

        $employees = $employeesQuery->orderBy('name')->get();

        return view('production.assignment.index', compact('operations', 'departments', 'employees'));
    }
}
