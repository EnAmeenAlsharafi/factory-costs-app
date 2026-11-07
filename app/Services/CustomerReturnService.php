<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\ProductionOrder;
use App\Models\QualityIncident;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CustomerReturnService
{
    public function __construct(protected FinishedGoodsService $finishedGoods) {}

    /**
     * Calculate total delivered quantity for a production order minus already accepted returns.
     */
    public function getNetDeliveredQuantity(ProductionOrder $productionOrder): float
    {
        // Dispatched OUT quantity minus returned DELIVERY_RETURN IN
        $dispatchedOut = (float) FinishedGoodsMovement::where('production_order_id', $productionOrder->id)
            ->where('movement_type', 'DELIVERY_DISPATCH')
            ->where('direction', 'OUT')
            ->sum('quantity');

        $deliveryReturnedIn = (float) FinishedGoodsMovement::where('production_order_id', $productionOrder->id)
            ->where('movement_type', 'DELIVERY_RETURN')
            ->where('direction', 'IN')
            ->sum('quantity');

        $netDispatched = max(0.0, $dispatchedOut - $deliveryReturnedIn);

        $alreadyReturned = (float) CustomerReturn::where('production_order_id', $productionOrder->id)
            ->whereNotIn('status', ['REJECTED', 'CANCELLED'])
            ->sum('quantity');

        return max(0.0, round($netDispatched - $alreadyReturned, 4));
    }

    /**
     * Report a customer return.
     */
    public function reportReturn(ProductionOrder $productionOrder, User $reporter, array $data): CustomerReturn
    {
        $qty = round((float) ($data['quantity_returned'] ?? $data['quantity'] ?? 0), 4);
        if ($qty <= 0) {
            throw new Exception('يلزم تحديد كمية إرجاع أكبر من الصفر.');
        }

        $netDelivered = $this->getNetDeliveredQuantity($productionOrder);
        if ($qty > (round($netDelivered, 4) + 0.00001)) {
            throw new Exception("كمية المرتجع المطلوبة ({$qty}) تتجاوز صافي الكمية المسلمة المتاحة للإرجاع ({$netDelivered}).");
        }

        $deliveryOrder = isset($data['delivery_order_id'])
            ? DeliveryOrder::find($data['delivery_order_id'])
            : null;

        return CustomerReturn::create([
            'return_number' => DocumentNumberService::generateCustomerReturnNumber(),
            'customer_order_id' => $productionOrder->customer_order_id,
            'delivery_order_id' => $deliveryOrder?->id,
            'production_order_id' => $productionOrder->id,
            'customer_order_line_id' => $productionOrder->customer_order_line_id,
            'quantity' => $qty,
            'reason_code' => $data['reason_code'],
            'condition_code' => $data['condition_code'] ?? 'NEEDS_INSPECTION',
            'status' => $data['status'] ?? 'REPORTED',
            'reported_at' => now(),
            'reported_by_user_id' => $reporter->id,
            'quality_incident_id' => $data['quality_incident_id'] ?? null,
            'notes' => $data['notes'] ?? $data['description'] ?? null,
        ]);
    }

    /**
     * Physically receive returned goods at the factory.
     * Creates CUSTOMER_RETURN IN movement in finished_goods_movements.
     */
    public function receiveReturnedGoods(CustomerReturn $return, User $receiver, ?string $conditionCode = null): CustomerReturn
    {
        // Returned goods always land in the inspection area; inspection decides whether they become sellable again.
        $quarantine = $this->finishedGoods->quarantineWarehouse();

        $receivedReturn = DB::transaction(function () use ($return, $receiver, $conditionCode, $quarantine) {
            $return = CustomerReturn::query()->whereKey($return->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($return->status, ['RECEIVED', 'REJECTED', 'RESOLVED'], true)) {
                throw new Exception('تم استلام أو تسوية مرتجع العملاء هذا مسبقاً.');
            }

            ProductionOrder::query()->whereKey($return->production_order_id)->lockForUpdate()->firstOrFail();

            FinishedGoodsMovement::create([
                'movement_number' => DocumentNumberService::generateFinishedGoodsMovementNumber(),
                'production_order_id' => $return->production_order_id,
                'customer_order_id' => $return->customer_order_id,
                'warehouse_id' => $quarantine->id,
                'customer_return_id' => $return->id,
                'movement_type' => 'CUSTOMER_RETURN',
                'direction' => 'IN',
                'quantity' => $return->quantity,
                'occurred_at' => now(),
                'performed_by_user_id' => $receiver->id,
                'notes' => "استلام مرتجع عميل إلى منطقة الفحص بموجب السند {$return->return_number} - سبب: {$return->reason_code}",
            ]);

            $return->update([
                'status' => 'RECEIVED',
                'condition_code' => $conditionCode ?? $return->condition_code,
                'received_at' => now(),
                'received_by_user_id' => $receiver->id,
            ]);

            return $return;
        });

        $return->setRawAttributes($receivedReturn->getAttributes(), true);

        return $return;
    }

    /**
     * Link an existing or new Stage 10 Quality Incident to the customer return.
     */
    public function linkQualityIncident(CustomerReturn $return, QualityIncident $incident): CustomerReturn
    {
        $return->update([
            'quality_incident_id' => $incident->id,
            'status' => 'LINKED_TO_QUALITY',
        ]);

        return $return;
    }

    /**
     * Approve a reported return so the goods can be collected.
     */
    public function approve(CustomerReturn $return, User $user): CustomerReturn
    {
        return $this->transition($return, ['REPORTED'], function (CustomerReturn $locked) use ($user) {
            $locked->update(['status' => 'APPROVED', 'approved_at' => now(), 'approved_by_user_id' => $user->id]);
        });
    }

    /**
     * Reject a return request before any goods are received.
     */
    public function reject(CustomerReturn $return, User $user, string $reason): CustomerReturn
    {
        return $this->transition($return, ['REPORTED', 'APPROVED'], function (CustomerReturn $locked) use ($user, $reason) {
            $locked->update([
                'status' => 'REJECTED',
                'resolution_notes' => "سبب الرفض: {$reason}",
                'resolved_at' => now(),
                'resolved_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Inspection decision for received goods: restock, repair, or scrap.
     */
    public function inspect(CustomerReturn $return, User $user, string $decision, ?string $notes = null): CustomerReturn
    {
        if (! array_key_exists($decision, CustomerReturn::INSPECTION_DECISIONS)) {
            throw new Exception('قرار الفحص غير صالح.');
        }

        return $this->transition($return, ['RECEIVED', 'LINKED_TO_QUALITY'], function (CustomerReturn $locked) use ($user, $decision, $notes) {
            $note = "قرار فحص المرتجع {$locked->return_number}: ".CustomerReturn::INSPECTION_DECISIONS[$decision].($notes ? " — {$notes}" : '');
            $quantity = (float) $locked->quantity;

            match ($decision) {
                'RESTOCK' => $this->finishedGoods->releaseFromQuarantine($locked->productionOrder, $quantity, $user, $note),
                'SCRAP' => $this->finishedGoods->scrapFromQuarantine($locked->productionOrder, $quantity, $user, $note),
                'REPAIR' => null,
            };

            $locked->update([
                'status' => $decision === 'REPAIR' ? 'UNDER_REPAIR' : 'INSPECTED',
                'inspection_decision' => $decision,
                'condition_code' => match ($decision) {
                    'RESTOCK' => 'GOOD',
                    'SCRAP' => 'SCRAP_CANDIDATE',
                    default => 'DAMAGED',
                },
                'inspected_at' => now(),
                'inspected_by_user_id' => $user->id,
                'notes' => $notes ? trim(($locked->notes ?? '')."\n".$notes) : $locked->notes,
            ]);
        });
    }

    /**
     * The repaired goods passed and go back to sellable stock.
     */
    public function completeRepair(CustomerReturn $return, User $user): CustomerReturn
    {
        return $this->transition($return, ['UNDER_REPAIR'], function (CustomerReturn $locked) use ($user) {
            $this->finishedGoods->releaseFromQuarantine($locked->productionOrder, (float) $locked->quantity, $user, "اكتمل إصلاح المرتجع {$locked->return_number} وأعيد للمخزون");
            $locked->update(['status' => 'INSPECTED']);
        });
    }

    /**
     * Close the case with what the customer received.
     */
    public function resolve(CustomerReturn $return, User $user, string $resolutionCode, ?string $notes = null): CustomerReturn
    {
        if (! array_key_exists($resolutionCode, CustomerReturn::RESOLUTIONS)) {
            throw new Exception('نوع الحل غير صالح.');
        }

        return $this->transition($return, ['INSPECTED'], function (CustomerReturn $locked) use ($user, $resolutionCode, $notes) {
            $locked->update([
                'status' => 'RESOLVED',
                'resolution_code' => $resolutionCode,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Lock the return, check its current status, then apply the change.
     *
     * @param  list<string>  $allowedStatuses
     */
    private function transition(CustomerReturn $return, array $allowedStatuses, callable $apply): CustomerReturn
    {
        $updated = DB::transaction(function () use ($return, $allowedStatuses, $apply) {
            $locked = CustomerReturn::query()->whereKey($return->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $allowedStatuses, true)) {
                throw new Exception("لا يمكن تنفيذ هذا الإجراء والمرتجع في حالة «{$locked->statusLabel()}». حدّث الصفحة.");
            }

            $apply($locked);

            return $locked;
        });

        $return->setRawAttributes($updated->getAttributes(), true);

        return $return;
    }
}
