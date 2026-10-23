<?php

namespace App\Services;

use App\Models\ProductionOrderOperation;
use App\Models\User;
use App\Services\Concerns\AuthorizesOperationExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee assignment, reassignment and claim for shop-floor operations (spec §6, §7, §33, §35, §36).
 *
 * Assignment history is preserved through the append-only event log rather than
 * a separate table; the operation row carries only the current assignment.
 */
class ProductionAssignmentService
{
    use AuthorizesOperationExecution;

    public function __construct(protected ProductionEventService $events) {}

    private const ASSIGNABLE_STATUSES = ['READY', 'ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'];

    /**
     * Mode A — Production Manager assigns (or reassigns) an operation to an employee.
     */
    public function assign(ProductionOrderOperation $operation, User $employee, User $actor, ?string $note = null): ProductionOrderOperation
    {
        if (! $actor->hasPermission('production.assignment.manage')) {
            throw ValidationException::withMessages([
                'authorization' => 'لا تملك صلاحية إسناد مهام الإنتاج.',
            ]);
        }

        if (! in_array($operation->status, self::ASSIGNABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'assignment' => 'لا يمكن إسناد هذه العملية في حالتها الحالية.',
            ]);
        }

        // Employee must be active, permitted, and belong to the operation's department.
        $this->assertEmployeeMayExecute($employee, $operation, 'production.operation.start');

        return DB::transaction(function () use ($operation, $employee, $actor, $note) {
            $locked = ProductionOrderOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();

            $previousUser = $locked->assigned_user_id;
            $isReassignment = $previousUser !== null && $previousUser !== $employee->id;

            $fromStatus = $locked->status;
            $locked->assigned_user_id = $employee->id;
            $locked->assigned_at = now();
            $locked->assigned_by_user_id = $actor->id;

            if ($locked->status === 'READY') {
                $locked->status = 'ASSIGNED';
            }

            $locked->save();

            $this->events->record($locked, $isReassignment ? 'REASSIGNED' : 'ASSIGNED', [
                'from' => $fromStatus,
                'to' => $locked->status,
                'note' => $note,
                'user' => $actor,
                'meta' => array_filter([
                    'assigned_to_user_id' => $employee->id,
                    'assigned_to_name' => $employee->name,
                    'previous_user_id' => $isReassignment ? $previousUser : null,
                ]),
            ]);

            return $locked->fresh(['assignedUser', 'assignedByUser']);
        });
    }

    /**
     * Reassign an operation to a different employee (records previous assignment — spec §36).
     */
    public function reassign(ProductionOrderOperation $operation, User $employee, User $actor, string $reason): ProductionOrderOperation
    {
        return $this->assign($operation, $employee, $actor, $reason);
    }

    /**
     * Mode B — an authorized department employee claims a READY unassigned operation.
     *
     * Atomic compare-and-set prevents two employees claiming the same task (spec §35, §64).
     */
    public function claim(ProductionOrderOperation $operation, User $employee): ProductionOrderOperation
    {
        $this->assertEmployeeMayExecute($employee, $operation, 'production.operation.claim');

        $affected = ProductionOrderOperation::where('id', $operation->id)
            ->where('status', 'READY')
            ->whereNull('assigned_user_id')
            ->update([
                'assigned_user_id' => $employee->id,
                'assigned_at' => now(),
                'assigned_by_user_id' => $employee->id,
                'status' => 'ASSIGNED',
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw ValidationException::withMessages([
                'claim' => 'تم استلام هذه المهمة بواسطة موظف آخر.',
            ]);
        }

        $fresh = $operation->fresh(['assignedUser', 'workCenter.department']);

        $this->events->record($fresh, 'CLAIMED', [
            'from' => 'READY',
            'to' => 'ASSIGNED',
            'user' => $employee,
        ]);

        return $fresh;
    }
}
