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
    /**
     * Allowed delivery status transitions (single source of truth for rules and for which UI actions appear).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['READY_FOR_DELIVERY', 'CANCELLED'],
        'READY_FOR_DELIVERY' => ['ASSIGNED', 'CANCELLED'],
        'ASSIGNED' => ['OUT_FOR_DELIVERY', 'CANCELLED'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'FAILED', 'RESCHEDULED'],
        'DELIVERED' => ['INSTALLATION_COMPLETED'],
    ];

    public function __construct(
        protected FinishedGoodsService $finishedGoodsService
    ) {}

    /**
     * Create a new Delivery Order with customer address snapshotting.
     */
    public function createDeliveryOrder(CustomerOrder $customerOrder, User $creator, array $data): DeliveryOrder
    {
        return DB::transaction(function () use ($customerOrder, $creator, $data) {
            $customerOrder = CustomerOrder::query()->whereKey($customerOrder->getKey())->lockForUpdate()->firstOrFail();
            $customer = $customerOrder->customer;
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
                $requestedByProductionOrder = [];

                foreach ($data['lines'] as $lineData) {
                    $po = ProductionOrder::query()->whereKey($lineData['production_order_id'])->lockForUpdate()->firstOrFail();
                    $qty = (float) $lineData['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $lineId = (int) ($lineData['customer_order_line_id'] ?? $po->customer_order_line_id);
                    $belongsToOrder = $customerOrder->lines()->whereKey($lineId)->exists();

                    if ((int) $po->customer_order_id !== (int) $customerOrder->id
                        || (int) $po->customer_order_line_id !== $lineId
                        || ! $belongsToOrder
                        || (int) $customerOrder->customer_id !== (int) $customer->id) {
                        throw new Exception('أمر الإنتاج أو بند الطلب لا ينتمي إلى طلب العميل المحدد للتوصيل.');
                    }

                    $requestedByProductionOrder[$po->id] = ($requestedByProductionOrder[$po->id] ?? 0) + $qty;
                    $available = $this->finishedGoodsService->getAvailableQuantity($po);
                    if ($requestedByProductionOrder[$po->id] > $available) {
                        throw new Exception("كمية التوصيل المطلوبة لأمر الإنتاج {$po->production_order_number} تتجاوز رصيد المنتجات الجاهزة المتاح ({$available}).");
                    }

                    $delivery->lines()->create([
                        'customer_order_line_id' => $lineId,
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
        return DB::transaction(function () use ($delivery, $user) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'READY_FOR_DELIVERY');

            foreach ($delivery->lines()->lockForUpdate()->get() as $line) {
                $productionOrder = ProductionOrder::query()->whereKey($line->production_order_id)->lockForUpdate()->firstOrFail();
                $available = $this->finishedGoodsService->getAvailableQuantity($productionOrder);
                if ((float) $line->quantity > $available) {
                    throw new Exception("الكمية المطلوبة بالتوصيل ({$line->quantity}) للطلب {$productionOrder->production_order_number} تتجاوز الرصيد المتاح بالمنتجات الجاهزة ({$available}).");
                }
            }

            $oldStatus = $delivery->status;
            $delivery->update(['status' => 'READY_FOR_DELIVERY']);
            $this->recordEvent($delivery, 'READY', $oldStatus, 'READY_FOR_DELIVERY', $user, 'جاهز للتوصيل والتحميل');

            return $delivery;
        });
    }

    /**
     * Assign delivery order to a delivery/installation driver/user.
     */
    public function assignDriver(DeliveryOrder $delivery, User $assignedDriver, User $assigner): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $assignedDriver, $assigner) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'ASSIGNED');

            $oldStatus = $delivery->status;
            $delivery->update([
                'assigned_user_id' => $assignedDriver->id,
                'status' => 'ASSIGNED',
            ]);

            $this->recordEvent($delivery, 'ASSIGNED', $oldStatus, 'ASSIGNED', $assigner, "تعيين مسؤول التوصيل: {$assignedDriver->name}");

            return $delivery;
        });
    }

    /**
     * Dispatch delivery order OUT_FOR_DELIVERY transactionally.
     * Generates DELIVERY_DISPATCH OUT movements in finished_goods_movements.
     */
    public function dispatchDelivery(DeliveryOrder $delivery, User $dispatcher): DeliveryOrder
    {
        $fgWarehouse = Warehouse::active()->where('code', 'FINISHED_GOODS')->first()
            ?? Warehouse::active()->first();

        return DB::transaction(function () use ($delivery, $dispatcher, $fgWarehouse) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'OUT_FOR_DELIVERY');

            foreach ($delivery->lines()->lockForUpdate()->get() as $line) {
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
        return DB::transaction(function () use ($delivery, $user, $notes) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'DELIVERED');

            $oldStatus = $delivery->status;
            $delivery->update([
                'status' => 'DELIVERED',
                'delivered_at' => now(),
                'delivery_notes' => $notes ?? $delivery->delivery_notes,
            ]);

            $this->recordEvent($delivery, 'DELIVERED', $oldStatus, 'DELIVERED', $user, $notes ?? 'تم تسليم المنتجات للعميل بنجاح');

            return $delivery;
        });
    }

    /**
     * Complete installation: mark status INSTALLATION_COMPLETED.
     */
    public function markInstalled(DeliveryOrder $delivery, User $user, ?string $notes = null): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $user, $notes) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'INSTALLATION_COMPLETED');

            if (! $delivery->installation_required) {
                throw new Exception('هذا الأمر لا يتطلب تركيباً؛ حالة التسليم هي الحالة النهائية.');
            }

            $oldStatus = $delivery->status;
            $delivery->update([
                'status' => 'INSTALLATION_COMPLETED',
                'installed_at' => now(),
                'installation_notes' => $notes ?? $delivery->installation_notes,
            ]);

            $this->recordEvent($delivery, 'INSTALLED', $oldStatus, 'INSTALLATION_COMPLETED', $user, $notes ?? 'تم التركيب والمعاينة النهائية بنجاح');

            return $delivery;
        });
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
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, $newStatus);
            $oldStatus = $delivery->status;

            if ($delivery->isDispatched() && $returnedToFactory) {
                foreach ($delivery->lines()->lockForUpdate()->get() as $line) {
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

            $eventType = match ($newStatus) {
                'RESCHEDULED' => 'RESCHEDULED',
                'CANCELLED' => 'CANCELLED',
                default => 'FAILED',
            };
            $this->recordEvent($delivery, $eventType, $oldStatus, $newStatus, $user, $reason ?? 'تعثر أمر التوصيل');

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

    private function assertTransition(string $from, string $to): void
    {
        $allowedTransitions = self::TRANSITIONS;

        if (! in_array($to, $allowedTransitions[$from] ?? [], true)) {
            $fromLabel = StatusPresenter::label('delivery', $from);
            $toLabel = StatusPresenter::label('delivery', $to);

            throw new Exception("انتقال حالة التوصيل من «{$fromLabel}» إلى «{$toLabel}» غير مسموح. قد تكون الحالة تغيّرت — حدّث الصفحة.");
        }
    }
}
