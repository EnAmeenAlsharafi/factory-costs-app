<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrderOperation;
use App\Models\User;
use App\Services\ProductionAssignmentService;
use App\Services\ProductionExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shop-floor execution actions (spec §14–22, §33–36, §63–64) — thin controller
 * over ProductionExecutionService / ProductionAssignmentService. No execution
 * or dependency logic lives here; everything is delegated to the services.
 */
class ProductionOperationController extends Controller
{
    public function __construct(
        protected ProductionExecutionService $executionService,
        protected ProductionAssignmentService $assignmentService,
    ) {}

    /**
     * An authorized department employee claims a READY unassigned operation (spec §35).
     */
    public function claim(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.operation.claim'), 403);

        $this->assignmentService->claim($operation, $request->user());

        return redirect()->back()->with('success', "تم استلام مهمة «{$operation->operation_name_snapshot}» بنجاح.");
    }

    /**
     * Start execution of an assigned/ready operation (spec §14).
     */
    public function start(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.operation.start'), 403);

        $this->executionService->start($operation, $request->user());

        return redirect()->back()->with('success', "تم بدء تنفيذ عملية «{$operation->operation_name_snapshot}».");
    }

    /**
     * Complete the remaining quantity of an operation in one action (spec §17).
     */
    public function complete(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.update_progress'), 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->executionService->complete($operation, $request->user(), $data['notes'] ?? null);

        return redirect()->back()->with('success', "تم تسجيل اكتمال عملية «{$operation->operation_name_snapshot}» بنجاح.");
    }

    /**
     * Record a structured block/obstacle on an operation (spec §20–21).
     */
    public function block(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.operation.block'), 403);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'in:MATERIAL_SHORTAGE,SPEC_ISSUE,QUALITY_ISSUE,AWAITING_MANAGER,EQUIPMENT_FAILURE,OTHER'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->executionService->block($operation, $data['reason_code'], $data['note'] ?? null, $request->user());

        return redirect()->back()->with('success', "تم تسجيل عائق على عملية «{$operation->operation_name_snapshot}» — سيتم إيقافها حتى يُحل العائق.");
    }

    /**
     * Resolve the active block on an operation and resume it (spec §22).
     */
    public function resolveBlock(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.operation.unblock'), 403);

        $data = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->executionService->resolveBlock($operation, $data['resolution_note'] ?? null, $request->user());

        return redirect()->back()->with('success', "تم حل العائق واستئناف عملية «{$operation->operation_name_snapshot}».");
    }

    /**
     * Production Manager assigns (or reassigns) an operation to an employee (spec §33, §36).
     */
    public function assign(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        abort_if(! $request->user()->can('production.assignment.manage'), 403);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = User::findOrFail($data['employee_id']);

        $this->assignmentService->assign($operation, $employee, $request->user(), $data['note'] ?? null);

        return redirect()->back()->with('success', "تم إسناد عملية «{$operation->operation_name_snapshot}» إلى {$employee->name}.");
    }
}
