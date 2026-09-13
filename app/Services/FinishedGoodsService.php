<?php

namespace App\Services;

use App\Models\FinishedGoodsMovement;
use App\Models\FinishedGoodsReceipt;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class FinishedGoodsService
{
    /**
     * Calculate net finished goods availability for a production order in a warehouse.
     */
    public function getAvailableQuantity(ProductionOrder $productionOrder, ?Warehouse $warehouse = null): float
    {
        $query = FinishedGoodsMovement::where('production_order_id', $productionOrder->id);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $inQty = (float) (clone $query)->where('direction', 'IN')->sum('quantity');
        $outQty = (float) (clone $query)->where('direction', 'OUT')->sum('quantity');

        return max(0.0, round($inQty - $outQty, 4));
    }

    /**
     * Calculate total physical completed quantity already received into finished goods for a production order.
     */
    public function getTotalReceivedQuantity(ProductionOrder $productionOrder): float
    {
        return (float) FinishedGoodsReceipt::where('production_order_id', $productionOrder->id)
            ->where('status', 'POSTED')
            ->sum('quantity');
    }

    /**
     * Create a draft Finished Goods Receipt.
     */
    public function createReceipt(ProductionOrder $productionOrder, User $user, array $data): FinishedGoodsReceipt
    {
        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first()
            ?? Warehouse::active()->first();

        $warehouseId = $data['warehouse_id'] ?? $fgWarehouse->id;
        $qty = (float) ($data['received_quantity'] ?? $data['quantity'] ?? 0);

        if ($qty <= 0) {
            throw new Exception('يلزم تحديد كمية منتجات جاهزة أكبر من الصفر.');
        }

        $alreadyReceived = $this->getTotalReceivedQuantity($productionOrder);
        $maxEligible = max(0.0, (float) $productionOrder->completed_quantity - $alreadyReceived);

        if ($qty > $maxEligible) {
            throw new Exception("الكمية المطلوبة ({$qty}) تتجاوز الحد الأقصى المتاح للتسليم ({$maxEligible}) بناءً على إنجاز التغليف والإنتاج المكتمل فعلياً.");
        }

        return FinishedGoodsReceipt::create([
            'receipt_number' => DocumentNumberService::generateFinishedGoodsReceiptNumber(),
            'production_order_id' => $productionOrder->id,
            'customer_order_id' => $productionOrder->customer_order_id,
            'warehouse_id' => $warehouseId,
            'quantity' => $qty,
            'status' => 'DRAFT',
            'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
            'received_by_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Post a draft Finished Goods Receipt transactionally.
     */
    public function postReceipt(FinishedGoodsReceipt $receipt, User $user): FinishedGoodsReceipt
    {
        if (! $receipt->isDraft()) {
            throw new Exception('يمكن فقط ترحيل سندات استلام المنتجات الجاهزة في حالة المسودة.');
        }

        return DB::transaction(function () use ($receipt, $user) {
            $po = ProductionOrder::where('id', $receipt->production_order_id)->lockForUpdate()->firstOrFail();

            $alreadyReceived = (float) FinishedGoodsReceipt::where('production_order_id', $po->id)
                ->where('status', 'POSTED')
                ->where('id', '!=', $receipt->id)
                ->sum('quantity');

            $maxEligible = max(0.0, (float) $po->completed_quantity - $alreadyReceived);

            if ((float) $receipt->quantity > $maxEligible) {
                throw new Exception("الكمية المرحلتة ({$receipt->quantity}) تتجاوز رصيد الإنجاز المتاح بالتسليم ({$maxEligible}).");
            }

            $movementNumber = DocumentNumberService::generateFinishedGoodsMovementNumber();

            FinishedGoodsMovement::create([
                'movement_number' => $movementNumber,
                'production_order_id' => $po->id,
                'customer_order_id' => $po->customer_order_id,
                'warehouse_id' => $receipt->warehouse_id,
                'finished_goods_receipt_id' => $receipt->id,
                'movement_type' => 'PRODUCTION_RECEIPT',
                'direction' => 'IN',
                'quantity' => $receipt->quantity,
                'occurred_at' => now(),
                'performed_by_user_id' => $user->id,
                'notes' => "تسليم منتجات جاهزة بموجب السند {$receipt->receipt_number}",
            ]);

            $receipt->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'received_by_user_id' => $user->id,
            ]);

            return $receipt;
        });
    }
}
