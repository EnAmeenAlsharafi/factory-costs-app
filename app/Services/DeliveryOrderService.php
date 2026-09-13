<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\DeliveryEvent;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class DeliveryOrderService
{
    public function __construct(
        protected FinishedGoodsService $finishedGoodsService
    ) {}

    /**
     * Create a new Delivery Order with customer address snapshotting.
     */
    public function createDeliveryOrder(CustomerOrder $customerOrder, User $creator, array $data): DeliveryOrder
    {
        $customer = $customerOrder->customer;

        return DB::transaction(function () use ($customerOrder, $customer, $creator, $data) {
            $deliveryNumber = DocumentNumberService::generateDeliveryNumber();

            $delivery = DeliveryOrder::create([
                'delivery_number' => $deliveryNumber,
                'customer_order_id' => $customerOrder->id,
                'customer_id' => $customer->id,
                'sales_channel_id' => $customerOrder->sales_channel_id,
                'scheduled_delivery_date' => $data['scheduled_delivery_date'] ?? null,
                'scheduled_time_notes' => $data['scheduled_time_notes'] ?? null,
                'status' => 'DRAFT',
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'customer_name_snapshot' => $data['customer_name_snapshot'] ?? $customer->name ?? $customer->name_ar,
                'customer_phone_snapshot' => $data['customer_phone_snapshot'] ?? $customer->phone,
                'city_snapshot' => $data['city_snapshot'] ?? $customer->city,
                'district_snapshot' => $data['district_snapshot'] ?? $customer->district,
                'delivery_address_snapshot' => $data['delivery_address_snapshot'] ?? $customer->address,
                'location_notes' => $data['location_notes'] ?? null,
                'installation_required' => $data['installation_required'] ?? true,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'installation_notes' => $data['installation_notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $po = ProductionOrder::findOrFail($lineData['production_order_id']);
                    $qty = (float) $lineData['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $delivery->lines()->create([
                        'customer_order_line_id' => $lineData['customer_order_line_id'] ?? $po->customer_order_line_id,
                        'production_order_id' => $po->id,
                        'quantity' => $qty,
                        'notes' => $lineData['notes'] ?? null,
                    ]);
                }
            }

            $this->recordEvent($delivery, 'CREATED', null, 'DRAFT', $creator, 'إنشاء أمر التوصيل');

            return $delivery;
        });
    }

    /**
     * Mark delivery as READY_FOR_DELIVERY after validating finished goods availability.
     */
    public function markReady(DeliveryOrder $delivery, User $user): DeliveryOrder
    {
        if ($delivery->status !== 'DRAFT') {
            throw new Exception('يمكن فقط نقل المسودة إلى حالة جاهز للتوصيل.');
        }

        foreach ($delivery->lines as $line) {
            $available = $this->finishedGoodsService->getAvailableQuantity($line->productionOrder);
            if ((float) $line->quantity > $available) {
                throw new Exception("الكمية المطلوبة بالتوصيل ({$line->quantity}) للطلب {$line->productionOrder->production_order_number} تتجاوز الرصيد المتاح بالمنتجات الجاهزة ({$available}).");
            }
        }

        $oldStatus = $delivery->status;
        $delivery->update(['status' => 'READY_FOR_DELIVERY']);
        $this->recordEvent($delivery, 'READY', $oldStatus, 'READY_FOR_DELIVERY', $user, 'جاهز للتوصيل والتحميل');

        return $delivery;
    }

    /**
     * Assign delivery order to a delivery/installation driver/user.
     */
    public function assignDriver(DeliveryOrder $delivery, User $assignedDriver, User $assigner): DeliveryOrder
    {
        $oldStatus = $delivery->status;
        $delivery->update([
            'assigned_user_id' => $assignedDriver->id,
            'status' => $delivery->status === 'DRAFT' ? 'ASSIGNED' : $delivery->status,
        ]);

        $this->recordEvent($delivery, 'ASSIGNED', $oldStatus, $delivery->status, $assigner, "تعيين مسؤول التوصيل: {$assignedDriver->name}");

        return $delivery;
    }

    /**
     * Dispatch delivery order OUT_FOR_DELIVERY transactionally.
     * Generates DELIVERY_DISPATCH OUT movements in finished_goods_movements.
     */
    public function dispatchDelivery(DeliveryOrder $delivery, User $dispatcher): DeliveryOrder
    {
        if (in_array($delivery->status, ['OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED', 'CANCELLED'], true)) {
            throw new Exception('أمر التوصيل خرج بالفعل أو انتهى.');
        }

        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first()
            ?? Warehouse::active()->first();

        return DB::transaction(function () use ($delivery, $dispatcher, $fgWarehouse) {
            foreach ($delivery->lines as $line) {
                $po = ProductionOrder::where('id', $line->production_order_id)->lockForUpdate()->firstOrFail();
                $available = $this->finishedGoodsService->getAvailableQuantity($po, $fgWarehouse);

                if ((float) $line->quantity > $available) {
                    throw new Exception("تعذر الخروج للتوصيل: الكمية المتاحة حالياً ({$available}) أقل من كمية أمر التوصيل ({$line->quantity}) لأمر الإنتاج {$po->production_order_number}.");
                }

                FinishedGoodsMovement::create([
                    'movement_number' => DocumentNumberService::generateFinishedGoodsMovementNumber(),
                    'production_order_id' => $po->id,
                    'customer_order_id' => $delivery->customer_order_id,
                    'warehouse_id' => $fgWarehouse->id,
                    'delivery_order_id' => $delivery->id,
                    'delivery_order_line_id' => $line->id,
                    'movement_type' => 'DELIVERY_DISPATCH',
                    'direction' => 'OUT',
                    'quantity' => $line->quantity,
                    'occurred_at' => now(),
                    'performed_by_user_id' => $dispatcher->id,
                    'notes' => "خروج للتوصيل بموجب أمر التوصيل {$delivery->delivery_number}",
                ]);
            }

            $oldStatus = $delivery->status;
            $delivery->update([
                'status' => 'OUT_FOR_DELIVERY',
                'dispatched_by_user_id' => $dispatcher->id,
                'dispatched_at' => now(),
            ]);

            $this->recordEvent($delivery, 'DISPATCHED', $oldStatus, 'OUT_FOR_DELIVERY', $dispatcher, 'خروج الشحنة مع فريق التوصيل');

            return $delivery;
        });
    }

    /**
     * Complete delivery: mark status DELIVERED.
     */
    public function markDelivered(DeliveryOrder $delivery, User $user, ?string $notes = null): DeliveryOrder
    {
        if ($delivery->status === 'CANCELLED') {
            throw new Exception('لا يمكن تسليم أمر توصيل ملغى.');
        }

        $oldStatus = $delivery->status;
        $delivery->update([
            'status' => 'DELIVERED',
            'delivered_at' => now(),
            'delivery_notes' => $notes ?? $delivery->delivery_notes,
        ]);

        $this->recordEvent($delivery, 'DELIVERED', $oldStatus, 'DELIVERED', $user, $notes ?? 'تم تسليم المنتجات للعميل بنجاح');

        return $delivery;
    }

    /**
     * Complete installation: mark status INSTALLATION_COMPLETED.
     */
    public function markInstalled(DeliveryOrder $delivery, User $user, ?string $notes = null): DeliveryOrder
    {
        if (! in_array($delivery->status, ['DELIVERED', 'OUT_FOR_DELIVERY'], true)) {
            throw new Exception('يمكن فقط تسجيل إكمال التركيب للطلبات المسلمة أو الخارجة للتوصيل.');
        }

        $oldStatus = $delivery->status;
        $delivery->update([
            'status' => 'INSTALLATION_COMPLETED',
            'installed_at' => now(),
            'delivered_at' => $delivery->delivered_at ?? now(),
            'installation_notes' => $notes ?? $delivery->installation_notes,
        ]);

        $this->recordEvent($delivery, 'INSTALLED', $oldStatus, 'INSTALLATION_COMPLETED', $user, $notes ?? 'تم التركيب والمعاينة النهائية بنجاح');

        return $delivery;
    }

    /**
     * Mark delivery FAILED or RESCHEDULED. If items physically return to factory, create DELIVERY_RETURN IN movement.
     */
    public function failOrReschedule(DeliveryOrder $delivery, User $user, string $newStatus, ?string $reason = null, ?string $newDate = null, bool $returnedToFactory = true): DeliveryOrder
    {
        if (! in_array($newStatus, ['FAILED', 'RESCHEDULED', 'CANCELLED'], true)) {
            throw new Exception('حالة غير صالحة للتعثر أو الإلغاء.');
        }

        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first()
            ?? Warehouse::active()->first();

        return DB::transaction(function () use ($delivery, $user, $newStatus, $reason, $newDate, $returnedToFactory, $fgWarehouse) {
            $oldStatus = $delivery->status;

            // If it was dispatched out and physically returned back to factory warehouse, restore FG availability
            if ($delivery->isDispatched() && $returnedToFactory) {
                foreach ($delivery->lines as $line) {
                    FinishedGoodsMovement::create([
                        'movement_number' => DocumentNumberService::generateFinishedGoodsMovementNumber(),
                        'production_order_id' => $line->production_order_id,
                        'customer_order_id' => $delivery->customer_order_id,
                        'warehouse_id' => $fgWarehouse->id,
                        'delivery_order_id' => $delivery->id,
                        'delivery_order_line_id' => $line->id,
                        'movement_type' => 'DELIVERY_RETURN',
                        'direction' => 'IN',
                        'quantity' => $line->quantity,
                        'occurred_at' => now(),
                        'performed_by_user_id' => $user->id,
                        'notes' => "إعادة المنتجات للمستودع بسبب تعثر/إعادة جدولة التوصيل: {$reason}",
                    ]);
                }
                $this->recordEvent($delivery, 'RETURNED_TO_FACTORY', $oldStatus, $newStatus, $user, "إعادة البضاعة للمصنع: {$reason}");
            }

            $delivery->update([
                'status' => $newStatus,
                'scheduled_delivery_date' => $newDate ?? $delivery->scheduled_delivery_date,
                'delivery_notes' => $reason ? "سبب الحدوث: {$reason}" : $delivery->delivery_notes,
            ]);

            $this->recordEvent($delivery, $newStatus === 'RESCHEDULED' ? 'RESCHEDULED' : 'FAILED', $oldStatus, $newStatus, $user, $reason ?? 'تعثر أمر التوصيل');

            return $delivery;
        });
    }

    /**
     * Record a delivery event.
     */
    public function recordEvent(DeliveryOrder $delivery, string $type, ?string $oldStatus, ?string $newStatus, User $user, ?string $notes = null): DeliveryEvent
    {
        return DeliveryEvent::create([
            'delivery_order_id' => $delivery->id,
            'event_type' => $type,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $user->id,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }
}
