<?php

namespace App\Services;

use App\Models\ProductionOperationEvent;
use App\Models\ProductionOrderOperation;
use App\Models\User;

/**
 * Single writer of the append-only operation event history (spec §27, §62).
 *
 * Records are the canonical source for the unified order timeline. Nothing else
 * writes production_operation_events, so there is exactly one event source.
 */
class ProductionEventService
{
    public const TYPES = [
        'READY', 'ASSIGNED', 'CLAIMED', 'REASSIGNED', 'STARTED', 'PROGRESS_RECORDED',
        'BLOCKED', 'BLOCK_RESOLVED', 'COMPLETED', 'CANCELLED', 'REWORK_CREATED', 'REWORK_COMPLETED',
        'ORDER_HELD', 'ORDER_RESUMED', 'ORDER_CANCELLED',
    ];

    /**
     * Record an execution event for an operation, snapshotting actor/department
     * labels for historical resilience (spec §67–68).
     *
     * @param  array{from?: ?string, to?: ?string, quantity?: ?int, note?: ?string, user?: ?User, meta?: ?array}  $attributes
     */
    public function record(ProductionOrderOperation $operation, string $eventType, array $attributes = []): ProductionOperationEvent
    {
        $user = $attributes['user'] ?? null;
        $operation->loadMissing('workCenter.department');

        return ProductionOperationEvent::create([
            'production_order_id' => $operation->production_order_id,
            'production_order_operation_id' => $operation->id,
            'event_type' => $eventType,
            'from_status' => $attributes['from'] ?? null,
            'to_status' => $attributes['to'] ?? null,
            'quantity' => $attributes['quantity'] ?? null,
            'user_id' => $user?->id,
            'user_name_snapshot' => $user?->name,
            'department_name_snapshot' => $operation->workCenter?->department?->name_ar,
            'work_center_name_snapshot' => $operation->workCenter?->name_ar,
            'note' => $attributes['note'] ?? null,
            'meta' => $attributes['meta'] ?? null,
            'occurred_at' => now(),
        ]);
    }
}
