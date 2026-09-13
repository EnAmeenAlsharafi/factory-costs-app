<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\ProductionOrder;
use App\Models\QualityIncident;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class CustomerReturnService
{
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
        if (in_array($return->status, ['RECEIVED', 'REJECTED', 'RESOLVED'], true)) {
            throw new Exception('تم استلام أو تسوية مرتجع العملاء هذا مسبقاً.');
        }

        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first()
            ?? Warehouse::active()->first();

        return DB::transaction(function () use ($return, $receiver, $conditionCode, $fgWarehouse) {
            FinishedGoodsMovement::create([
                'movement_number' => DocumentNumberService::generateFinishedGoodsMovementNumber(),
                'production_order_id' => $return->production_order_id,
                'customer_order_id' => $return->customer_order_id,
                'warehouse_id' => $fgWarehouse->id,
                'customer_return_id' => $return->id,
                'movement_type' => 'CUSTOMER_RETURN',
                'direction' => 'IN',
                'quantity' => $return->quantity,
                'occurred_at' => now(),
                'performed_by_user_id' => $receiver->id,
                'notes' => "استلام مرتجع عميل بموجب السند {$return->return_number} - سبب: {$return->reason_code}",
            ]);

            $return->update([
                'status' => 'RECEIVED',
                'condition_code' => $conditionCode ?? $return->condition_code,
                'received_at' => now(),
                'received_by_user_id' => $receiver->id,
            ]);

            return $return;
        });
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
}
