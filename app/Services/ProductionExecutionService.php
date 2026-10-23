<?php

namespace App\Services;

use App\Models\ProductionOperationBlock;
use App\Models\ProductionOrderOperation;
use App\Models\User;
use App\Services\Concerns\AuthorizesOperationExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shop-floor execution façade: start / progress / complete / block / unblock (spec §14–22, §63, §64).
 *
 * Progress and completion delegate to ProductionOrderService (the authoritative,
 * locked, dependency-aware quantity engine) so there is no second execution engine.
 */
class ProductionExecutionService
{
    use AuthorizesOperationExecution;

    private const TERMINAL_STATUSES = ['COMPLETED', 'SKIPPED'];

    public function __construct(
        protected ProductionOrderService $orders,
        protected ProductionEventService $events,
        protected ProductionAssignmentService $assignments,
    ) {}

    /**
     * Start execution of an operation (spec §14). Idempotent: a second start is a no-op.
     */
    public function start(ProductionOrderOperation $operation, User $employee): ProductionOrderOperation
    {
        $this->assertOrderActive($operation);
        $this->assertEmployeeMayExecute($employee, $operation, 'production.operation.start');

        return DB::transaction(function () use ($operation, $employee) {
            $locked = ProductionOrderOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['IN_PROGRESS', 'PARTIALLY_COMPLETED'], true)) {
                return $locked; // Already started — safe against double-start (spec §63).
            }

            if ($locked->status === 'BLOCKED') {
                throw ValidationException::withMessages([
                    'operation' => 'العملية متوقفة بسبب عائق. يجب حل العائق قبل البدء.',
                ]);
            }

            if (! in_array($locked->status, ['READY', 'ASSIGNED'], true)) {
                throw ValidationException::withMessages([
                    'operation' => 'لا يمكن بدء العملية قبل أن تصبح جاهزة للتنفيذ.',
                ]);
            }

            // An operation assigned to another employee cannot be started by this one.
            if ($locked->assigned_user_id && $locked->assigned_user_id !== $employee->id && ! $this->hasDepartmentOverride($employee)) {
                throw ValidationException::withMessages([
                    'operation' => 'هذه المهمة مُسندة لموظف آخر.',
                ]);
            }

            $fromStatus = $locked->status;

            if ($locked->assigned_user_id === null) {
                $locked->assigned_user_id = $employee->id;
                $locked->assigned_at = now();
                $locked->assigned_by_user_id = $employee->id;
            }

            $locked->status = 'IN_PROGRESS';
            $locked->started_at = $locked->started_at ?? now();
            $locked->save();

            $this->events->record($locked, 'STARTED', ['from' => $fromStatus, 'to' => 'IN_PROGRESS', 'user' => $employee]);

            return $locked->fresh();
        });
    }

    /**
     * Record partial progress (spec §15, §16) — delegates to the locked quantity engine.
     */
    public function recordProgress(ProductionOrderOperation $operation, int $quantity, ?string $note, User $employee): ProductionOrderOperation
    {
        return $this->orders->recordProgress($operation, $quantity, 'PROGRESS', $note, $employee);
    }

    /**
     * Complete the remaining quantity of an operation (spec §17). Idempotent when already complete.
     */
    public function complete(ProductionOrderOperation $operation, User $employee, ?string $note = null): ProductionOrderOperation
    {
        $remaining = $operation->remaining_quantity;

        if ($remaining <= 0) {
            return $operation->fresh();
        }

        return $this->orders->recordProgress($operation, $remaining, 'COMPLETE', $note, $employee);
    }

    /**
     * Block an operation with a structured reason (spec §20, §21).
     */
    public function block(ProductionOrderOperation $operation, string $reasonCode, ?string $note, User $employee, array $links = []): ProductionOperationBlock
    {
        if (! $employee->hasPermission('production.operation.block')) {
            throw ValidationException::withMessages([
                'authorization' => 'لا تملك صلاحية إيقاف العمليات.',
            ]);
        }

        $this->assertEmployeeMayExecute($employee, $operation, 'production.operation.block');

        return DB::transaction(function () use ($operation, $reasonCode, $note, $employee, $links) {
            $locked = ProductionOrderOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, self::TERMINAL_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'operation' => 'لا يمكن إيقاف عملية مكتملة أو ملغاة.',
                ]);
            }

            if ($locked->status === 'BLOCKED') {
                throw ValidationException::withMessages([
                    'operation' => 'العملية متوقفة بالفعل.',
                ]);
            }

            $previousStatus = $locked->status;

            $block = ProductionOperationBlock::create([
                'production_order_operation_id' => $locked->id,
                'production_order_id' => $locked->production_order_id,
                'reason_code' => $reasonCode,
                'note' => $note,
                'previous_status' => $previousStatus,
                'blocked_by_user_id' => $employee->id,
                'blocked_at' => now(),
                'production_material_requirement_id' => $links['material_requirement_id'] ?? null,
                'production_material_request_id' => $links['material_request_id'] ?? null,
                'is_active' => true,
            ]);

            $locked->status = 'BLOCKED';
            $locked->save();

            $this->events->record($locked, 'BLOCKED', [
                'from' => $previousStatus,
                'to' => 'BLOCKED',
                'note' => $note,
                'user' => $employee,
                'meta' => ['reason_code' => $reasonCode, 'block_id' => $block->id],
            ]);

            return $block;
        });
    }

    /**
     * Resolve the active block on an operation and restore its previous state (spec §22).
     */
    public function resolveBlock(ProductionOrderOperation $operation, ?string $resolutionNote, User $actor): ProductionOrderOperation
    {
        if (! $actor->hasPermission('production.operation.unblock')) {
            throw ValidationException::withMessages([
                'authorization' => 'لا تملك صلاحية حل العوائق.',
            ]);
        }

        return DB::transaction(function () use ($operation, $resolutionNote, $actor) {
            $locked = ProductionOrderOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();

            $block = $locked->activeBlock()->lockForUpdate()->first();

            if ($locked->status !== 'BLOCKED' || ! $block) {
                throw ValidationException::withMessages([
                    'operation' => 'لا يوجد عائق نشط على هذه العملية.',
                ]);
            }

            $block->resolved_by_user_id = $actor->id;
            $block->resolved_at = now();
            $block->resolution_note = $resolutionNote;
            $block->is_active = false;
            $block->save();

            // Restore the state the operation held before it was blocked (spec §22).
            $restored = $block->previous_status;
            $locked->status = $restored;
            $locked->save();

            $this->events->record($locked, 'BLOCK_RESOLVED', [
                'from' => 'BLOCKED',
                'to' => $restored,
                'note' => $resolutionNote,
                'user' => $actor,
                'meta' => ['block_id' => $block->id],
            ]);

            return $locked->fresh();
        });
    }

    protected function assertOrderActive(ProductionOrderOperation $operation): void
    {
        $status = $operation->productionOrder->status;

        if (in_array($status, ['ON_HOLD', 'CANCELLED', 'COMPLETED'], true)) {
            throw ValidationException::withMessages([
                'operation' => "لا يمكن تنفيذ العملية، أمر الإنتاج في حالة: {$operation->productionOrder->status_arabic}.",
            ]);
        }

        if (in_array($operation->status, self::TERMINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'operation' => 'العملية مكتملة أو ملغاة.',
            ]);
        }
    }
}
