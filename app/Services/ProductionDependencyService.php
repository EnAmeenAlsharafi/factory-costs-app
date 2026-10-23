<?php

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use Illuminate\Support\Facades\DB;

/**
 * Centralized routing-dependency evaluation (spec §18, §58).
 *
 * This is the single authority for "what quantity may an operation reach" and
 * "which WAITING operations are now READY". Controllers never re-implement it.
 *
 * Quantity-aware partial flow is the existing, preserved rule (spec §19): an
 * operation may progress up to the minimum completed quantity of its upstream
 * dependencies, and becomes READY once that eligible quantity is above zero.
 */
class ProductionDependencyService
{
    /**
     * Maximum quantity an operation may complete given upstream dependencies.
     */
    public function maxEligibleQuantity(ProductionOrderOperation $op): int
    {
        if (! $op->routing_operation_id) {
            return $op->required_quantity;
        }

        $dependencyIds = DB::table('production_routing_dependencies')
            ->where('operation_id', $op->routing_operation_id)
            ->pluck('depends_on_operation_id')
            ->all();

        if (empty($dependencyIds)) {
            return $op->required_quantity;
        }

        $upstream = ProductionOrderOperation::where('production_order_id', $op->production_order_id)
            ->whereIn('routing_operation_id', $dependencyIds)
            ->get();

        if ($upstream->isEmpty()) {
            return $op->required_quantity;
        }

        return (int) $upstream->min('completed_quantity');
    }

    /**
     * Promote WAITING (PENDING) operations whose dependencies are now satisfied to READY.
     *
     * @return list<ProductionOrderOperation> operations newly transitioned to READY
     */
    public function evaluateReadiness(ProductionOrder $po): array
    {
        $newlyReady = [];

        $ops = $po->operations()->get();

        foreach ($ops as $op) {
            if ($op->status !== 'PENDING') {
                continue;
            }

            if ($this->maxEligibleQuantity($op) > 0) {
                $op->status = 'READY';
                $op->ready_at = $op->ready_at ?? now();
                $op->save();
                $newlyReady[] = $op;
            }
        }

        return $newlyReady;
    }
}
