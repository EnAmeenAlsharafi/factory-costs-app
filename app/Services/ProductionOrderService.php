<?php

namespace App\Services;

use App\Models\CustomerOrderLine;
use App\Models\ProductionOperationProgress;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\QualityIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOrderService
{
    public function __construct(
        protected ProductionDependencyService $dependencies,
        protected ProductionEventService $events,
    ) {}

    /**
     * Create a new Production Order from a Customer Order Line.
     */
    public function createFromOrderLine(CustomerOrderLine $line, array $data): ProductionOrder
    {
        $order = $line->customerOrder;

        if ($order->status !== 'APPROVED_FOR_PRODUCTION') {
            throw ValidationException::withMessages([
                'customer_order_id' => 'يمكن فقط إنشاء أمر إنتاج لطلب عميل معتمد للإنتاج.',
            ]);
        }

        $alreadyReleased = ProductionOrder::where('customer_order_line_id', $line->id)
            ->where('status', '!=', 'CANCELLED')
            ->sum('released_quantity');

        $remainingToRelease = $line->quantity - $alreadyReleased;

        $requestedQty = (int) ($data['released_quantity'] ?? $line->quantity);

        if ($requestedQty <= 0) {
            throw ValidationException::withMessages([
                'released_quantity' => 'كمية إطلاق الإنتاج يجب أن تكون أكبر من الصفر.',
            ]);
        }

        if ($requestedQty > $remainingToRelease) {
            throw ValidationException::withMessages([
                'released_quantity' => "الكمية المطلوبة ({$requestedQty}) تتجاوز الكمية المتبقية للإطلاق ({$remainingToRelease}).",
            ]);
        }

        return DB::transaction(function () use ($line, $order, $data, $requestedQty) {
            $poNumber = DocumentNumberService::generateProductionOrderNumber();

            $recipeVersionId = $line->approved_recipe_version_id
                ?? $data['manufacturing_recipe_version_id']
                ?? null;

            $po = ProductionOrder::create([
                'production_order_number' => $poNumber,
                'customer_order_id' => $order->id,
                'customer_order_line_id' => $line->id,
                'product_model_id' => $line->product_model_id,
                'product_configuration_id' => $line->product_configuration_id,
                'manufacturing_recipe_version_id' => $recipeVersionId,
                'customer_product_alias_id' => $line->customer_product_alias_id,
                'production_routing_id' => $data['production_routing_id'] ?? null,
                'is_custom_design' => (bool) ($line->custom_design ?? $data['is_custom_design'] ?? false),
                'custom_design_name' => $line->custom_design_name ?? $data['custom_design_name'] ?? null,
                'requested_width_cm' => $line->requested_width_cm ?? 0,
                'requested_length_cm' => $line->requested_length_cm ?? 0,
                'reference_width_cm' => $line->reference_width_cm ?? 0,
                'reference_length_cm' => $line->reference_length_cm ?? 0,
                'has_storage' => (bool) $line->has_storage,
                'fabric_supplier_id' => $line->fabric_supplier_id,
                'fabric_material_id' => $line->fabric_material_id,
                'fabric_color_id' => $line->fabric_color_id,
                'fabric_color_code' => $line->fabric_color_code,
                'fabric_supplier_color_code' => $line->fabric_supplier_color_code ?? $line->fabricColor?->supplier_color_code,
                'ordered_quantity' => $line->quantity,
                'released_quantity' => $requestedQty,
                'completed_quantity' => 0,
                'priority' => $data['priority'] ?? $order->priority ?? 'NORMAL',
                'status' => 'DRAFT',
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_completion_date' => $data['planned_completion_date'] ?? null,
                'production_notes' => $data['production_notes'] ?? $line->notes ?? null,
            ]);

            return $po;
        });
    }

    /**
     * Release a Production Order to the shop floor using a selected routing.
     */
    public function releaseProductionOrder(ProductionOrder $po, ProductionRouting $routing, User $user): ProductionOrder
    {
        if ($po->customerOrder->status !== 'APPROVED_FOR_PRODUCTION') {
            throw ValidationException::withMessages([
                'production_order' => 'طلب العميل غير معتمد للإنتاج حالياً.',
            ]);
        }

        if (! in_array($po->status, ['DRAFT', 'READY_FOR_RELEASE'])) {
            throw ValidationException::withMessages([
                'production_order' => 'لا يمكن إطلاق أمر إنتاج حالته ليست مسودة أو جاهز للإطلاق.',
            ]);
        }

        if (! $po->is_custom_design && ! $po->manufacturing_recipe_version_id) {
            throw ValidationException::withMessages([
                'manufacturing_recipe_version_id' => 'يجب ربط المنتج بوصفة تصنيع معتمدة قبل الإطلاق.',
            ]);
        }

        $routingOps = $routing->operations()->with('dependencies')->get();

        if ($routingOps->isEmpty()) {
            throw ValidationException::withMessages([
                'production_routing_id' => 'مسار التصنيع المختار لا يحتوي على عمليات.',
            ]);
        }

        return DB::transaction(function () use ($po, $routing, $routingOps, $user) {
            // Remove previous draft operations if any
            $po->operations()->delete();

            $po->production_routing_id = $routing->id;
            $po->status = 'RELEASED';
            $po->released_by_user_id = $user->id;
            $po->released_at = now();
            $po->save();

            foreach ($routingOps as $rOp) {
                $hasDependencies = $rOp->dependencies->isNotEmpty();
                $isReady = ! $hasDependencies;

                $poOp = ProductionOrderOperation::create([
                    'production_order_id' => $po->id,
                    'routing_operation_id' => $rOp->id,
                    'work_center_id' => $rOp->work_center_id,
                    'operation_code' => $rOp->operation_code,
                    'operation_name_snapshot' => $rOp->name_ar,
                    'branch_key' => $rOp->branch_key,
                    'sequence_number' => $rOp->sequence_number,
                    'required_quantity' => $po->released_quantity,
                    'started_quantity' => 0,
                    'completed_quantity' => 0,
                    'rejected_quantity' => 0,
                    'status' => $isReady ? 'READY' : 'PENDING',
                    'ready_at' => $isReady ? now() : null,
                    'notes' => null,
                ]);

                if ($isReady) {
                    $this->events->record($poOp, 'READY', ['to' => 'READY', 'user' => $user]);
                }
            }

            return $po->fresh('operations.workCenter');
        });
    }

    /**
     * Record partial progress for a production operation.
     */
    public function recordProgress(ProductionOrderOperation $op, int $addedQty, string $eventType, ?string $notes, User $user): ProductionOrderOperation
    {
        $po = $op->productionOrder;

        if (in_array($po->status, ['ON_HOLD', 'CANCELLED', 'COMPLETED'])) {
            throw ValidationException::withMessages([
                'operation' => "لا يمكن تحديث الإنجاز، أمر الإنتاج في حالة: {$po->status_arabic}.",
            ]);
        }

        if ($op->status === 'BLOCKED') {
            throw ValidationException::withMessages([
                'operation' => 'لا يمكن تسجيل تقدم على عملية متوقفة. يجب حل العائق أولاً.',
            ]);
        }

        // Check department authorization
        if (! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
            if ($user->department_id && $user->department_id !== $op->workCenter->department_id) {
                throw ValidationException::withMessages([
                    'authorization' => 'غير مصرح لك بتحديث إنجاز عمليات خارج قسمك التشغيلي.',
                ]);
            }

            // An operation assigned to another employee cannot have progress recorded by this one.
            if ($op->assigned_user_id && $op->assigned_user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'authorization' => 'هذه المهمة مُسندة لموظف آخر، لا يمكنك تسجيل إنجاز عليها.',
                ]);
            }
        }

        if ($addedQty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية المنجزة يجب أن تكون أكبر من الصفر.',
            ]);
        }

        $newCompleted = $op->completed_quantity + $addedQty;

        if ($newCompleted > $op->required_quantity) {
            throw ValidationException::withMessages([
                'quantity' => "لا يمكن تسجيل كمية أكبر من المتبقي (المتبقي: {$op->remaining_quantity}).",
            ]);
        }

        // Validate join dependencies limit
        $maxEligible = $this->dependencies->maxEligibleQuantity($op);
        if ($newCompleted > $maxEligible) {
            throw ValidationException::withMessages([
                'quantity' => "الكمية المنجزة المتاحة لهذه العملية محددة باكتمال المراحل السابقة (الحد الأقصى المتاح حالياً: {$maxEligible}).",
            ]);
        }

        return DB::transaction(function () use ($op, $po, $addedQty, $eventType, $notes, $user) {
            $lockedOp = ProductionOrderOperation::where('id', $op->id)->lockForUpdate()->firstOrFail();

            if (! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
                if ($lockedOp->assigned_user_id && $lockedOp->assigned_user_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'authorization' => 'هذه المهمة مُسندة لموظف آخر، لا يمكنك تسجيل إنجاز عليها.',
                    ]);
                }
            }

            if ($lockedOp->assigned_user_id === null) {
                $lockedOp->assigned_user_id = $user->id;
                $lockedOp->assigned_at = now();
                $lockedOp->assigned_by_user_id = $user->id;
            }

            $newCompleted = $lockedOp->completed_quantity + $addedQty;

            if ($newCompleted > $lockedOp->required_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "لا يمكن تسجيل كمية أكبر من المتبقي (المتبقي: {$lockedOp->remaining_quantity}).",
                ]);
            }

            // Validate join dependencies limit
            $maxEligible = $this->dependencies->maxEligibleQuantity($lockedOp);
            if ($newCompleted > $maxEligible) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المنجزة المتاحة لهذه العملية محددة باكتمال المراحل السابقة (الحد الأقصى المتاح حالياً: {$maxEligible}).",
                ]);
            }

            $prevCompleted = $lockedOp->completed_quantity;
            $prevStatus = $lockedOp->status;
            $wasNotStarted = $lockedOp->started_at === null;

            $lockedOp->completed_quantity = $newCompleted;
            $lockedOp->started_quantity = max($lockedOp->started_quantity, $newCompleted);

            if ($lockedOp->started_at === null) {
                $lockedOp->started_at = now();
            }

            if ($lockedOp->completed_quantity >= $lockedOp->required_quantity) {
                $lockedOp->status = 'COMPLETED';
                $lockedOp->completed_at = now();
            } elseif ($lockedOp->completed_quantity > 0) {
                $lockedOp->status = 'PARTIALLY_COMPLETED';
            } else {
                $lockedOp->status = 'IN_PROGRESS';
            }

            $lockedOp->save();

            // Quantity ledger (existing, unchanged).
            ProductionOperationProgress::create([
                'production_order_operation_id' => $lockedOp->id,
                'event_type' => $eventType,
                'quantity' => $addedQty,
                'previous_completed_quantity' => $prevCompleted,
                'new_completed_quantity' => $newCompleted,
                'user_id' => $user->id,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);

            // Execution/timeline audit (append-only).
            if ($wasNotStarted) {
                $this->events->record($lockedOp, 'STARTED', ['from' => $prevStatus, 'to' => 'IN_PROGRESS', 'user' => $user]);
            }
            $this->events->record($lockedOp, 'PROGRESS_RECORDED', [
                'from' => $prevStatus,
                'to' => $lockedOp->status,
                'quantity' => $addedQty,
                'note' => $notes,
                'user' => $user,
            ]);
            if ($lockedOp->status === 'COMPLETED') {
                $this->events->record($lockedOp, 'COMPLETED', [
                    'from' => $prevStatus,
                    'to' => 'COMPLETED',
                    'quantity' => $lockedOp->completed_quantity,
                    'user' => $user,
                ]);
            }

            // Re-evaluate downstream operations (centralized dependency engine).
            $this->promoteReadyOperations($po, $user);

            // Re-evaluate overall Production Order completion
            $this->evaluateProductionOrderCompletion($po);

            return $lockedOp->fresh();
        });
    }

    /**
     * Correct operation progress (Admin / Production Manager controlled correction).
     */
    public function correctProgress(ProductionOrderOperation $op, int $newTotalQty, string $notes, User $user): ProductionOrderOperation
    {
        $po = $op->productionOrder;

        if ($newTotalQty < 0 || $newTotalQty > $op->required_quantity) {
            throw ValidationException::withMessages([
                'quantity' => "إجمالي الكمية بعد التصحيح يجب أن يكون بين 0 و {$op->required_quantity}.",
            ]);
        }

        return DB::transaction(function () use ($op, $po, $newTotalQty, $notes, $user) {
            $prevCompleted = $op->completed_quantity;
            $prevStatus = $op->status;
            $op->completed_quantity = $newTotalQty;
            $op->started_quantity = max($op->started_quantity, $newTotalQty);

            if ($op->completed_quantity >= $op->required_quantity) {
                $op->status = 'COMPLETED';
                $op->completed_at = now();
            } elseif ($op->completed_quantity > 0) {
                $op->status = 'PARTIALLY_COMPLETED';
            } else {
                $op->status = 'READY';
            }

            $op->save();

            ProductionOperationProgress::create([
                'production_order_operation_id' => $op->id,
                'event_type' => 'CORRECTION',
                'quantity' => $newTotalQty - $prevCompleted,
                'previous_completed_quantity' => $prevCompleted,
                'new_completed_quantity' => $newTotalQty,
                'user_id' => $user->id,
                'notes' => "تصحيح كمية الإنجاز: {$notes}",
                'occurred_at' => now(),
            ]);

            $this->events->record($op, 'PROGRESS_RECORDED', [
                'from' => $prevStatus,
                'to' => $op->status,
                'quantity' => $newTotalQty - $prevCompleted,
                'note' => "تصحيح كمية الإنجاز: {$notes}",
                'user' => $user,
                'meta' => ['correction' => true],
            ]);

            $this->promoteReadyOperations($po, $user);
            $this->evaluateProductionOrderCompletion($po);

            return $op->fresh();
        });
    }

    /**
     * Put a Production Order on hold.
     */
    public function holdOrder(ProductionOrder $po, string $reason): ProductionOrder
    {
        if (in_array($po->status, ['COMPLETED', 'CANCELLED'])) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعليق أمر إنتاج مكتمل أو ملغى.',
            ]);
        }

        $po->status = 'ON_HOLD';
        $po->hold_reason = $reason;
        $po->save();

        return $po;
    }

    /**
     * Resume a held Production Order.
     */
    public function resumeOrder(ProductionOrder $po): ProductionOrder
    {
        if ($po->status !== 'ON_HOLD') {
            throw ValidationException::withMessages([
                'status' => 'أمر الإنتاج ليس في حالة تعليق.',
            ]);
        }

        $po->status = $po->completed_quantity > 0 ? 'PARTIALLY_COMPLETED' : 'IN_PROGRESS';
        $po->hold_reason = null;
        $po->save();

        return $po;
    }

    /**
     * Cancel a Production Order.
     */
    public function cancelOrder(ProductionOrder $po, string $reason, User $user): ProductionOrder
    {
        if ($po->status === 'COMPLETED') {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إلغاء أمر إنتاج مكتمل.',
            ]);
        }

        if ($po->completed_quantity > 0 && ! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
            throw ValidationException::withMessages([
                'status' => 'إلغاء أمر إنتاج تم البدء فيه يتطلب صلاحيات مدير الإنتاج أو مدير النظام.',
            ]);
        }

        $po->status = 'CANCELLED';
        $po->cancellation_reason = $reason;
        $po->save();

        return $po;
    }

    /**
     * Calculate maximum quantity an operation is allowed to complete based on upstream dependencies.
     *
     * @deprecated Prefer ProductionDependencyService::maxEligibleQuantity(); retained for backward compatibility.
     */
    public function calculateMaxEligibleQuantity(ProductionOrderOperation $op): int
    {
        return $this->dependencies->maxEligibleQuantity($op);
    }

    /**
     * Promote WAITING operations whose dependencies are satisfied to READY, logging events.
     */
    protected function promoteReadyOperations(ProductionOrder $po, User $user): void
    {
        foreach ($this->dependencies->evaluateReadiness($po) as $readyOp) {
            $this->events->record($readyOp, 'READY', ['to' => 'READY', 'user' => $user]);
        }
    }

    /**
     * Evaluate final Production Order completed quantity and status.
     */
    protected function evaluateProductionOrderCompletion(ProductionOrder $po): void
    {
        $ops = $po->operations;
        if ($ops->isEmpty()) {
            return;
        }

        // Find the final operation (highest sequence or Packaging work center)
        $finalOp = $ops->sortByDesc('sequence_number')->first();

        $po->completed_quantity = $finalOp->completed_quantity;

        $hasOpenQualityIncidents = QualityIncident::where('production_order_id', $po->id)
            ->whereNotIn('status', ['RESOLVED', 'CANCELLED'])
            ->exists();

        if ($po->completed_quantity >= $po->released_quantity && ! $hasOpenQualityIncidents) {
            $po->status = 'COMPLETED';
            $po->completed_at = now();
        } elseif ($po->completed_quantity > 0 || $ops->where('completed_quantity', '>', 0)->isNotEmpty()) {
            $po->status = 'PARTIALLY_COMPLETED';
        } else {
            $po->status = 'IN_PROGRESS';
        }

        $po->save();
    }
}
