<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\DeliveryEvent;
use App\Models\DeliveryOrder;
use App\Models\Department;
use App\Models\FinishedGoodsMovement;
use App\Models\Material;
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
        // A failed/postponed delivery is closed either by a successor order (reschedule) or by cancelling it.
        'FAILED' => ['CANCELLED'],
        'RESCHEDULED' => ['CANCELLED'],
    ];

    public function __construct(
        protected FinishedGoodsService $finishedGoodsService,
        protected InventoryService $inventoryService,
        protected QualityIncidentService $qualityIncidentService
    ) {}

    /**
     * Create a new Delivery Order with customer address snapshotting.
     */
    /**
     * Delivery orders whose goods have not left the warehouse yet, so they still hold stock aside.
     */
    public const UNDISPATCHED_STATUSES = ['DRAFT', 'READY_FOR_DELIVERY', 'ASSIGNED'];

    /**
     * How many pieces of this order can go on a new delivery order now: finished goods received
     * from its production orders plus ready stock in the warehouse, less what open delivery orders
     * already hold. Zero while the order is still in production.
     */
    public function deliverableQuantity(CustomerOrder $customerOrder): float
    {
        $customerOrder->loadMissing('lines.productionOrders', 'lines.material');
        $heldByOpenDeliveries = DB::table('delivery_order_lines')
            ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_lines.delivery_order_id')
            ->where('delivery_orders.customer_order_id', $customerOrder->id)
            ->whereIn('delivery_orders.status', self::UNDISPATCHED_STATUSES);
        $heldByProductionOrder = (clone $heldByOpenDeliveries)->whereNotNull('delivery_order_lines.production_order_id')
            ->groupBy('delivery_order_lines.production_order_id')
            ->pluck(DB::raw('SUM(delivery_order_lines.quantity)'), 'delivery_order_lines.production_order_id');
        $onDeliveriesByLine = DB::table('delivery_order_lines')
            ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_lines.delivery_order_id')
            ->where('delivery_orders.customer_order_id', $customerOrder->id)
            ->where('delivery_orders.status', '!=', 'CANCELLED')
            ->groupBy('delivery_order_lines.customer_order_line_id')
            ->pluck(DB::raw('SUM(delivery_order_lines.quantity)'), 'delivery_order_lines.customer_order_line_id');

        $deliverable = 0.0;
        foreach ($customerOrder->lines as $line) {
            if ($line->line_type === 'MANUFACTURED') {
                foreach ($line->productionOrders->where('status', '!=', 'CANCELLED') as $productionOrder) {
                    $deliverable += max(0.0, $this->finishedGoodsService->getAvailableQuantity($productionOrder) - (float) ($heldByProductionOrder[$productionOrder->id] ?? 0));
                }
            } elseif ($line->line_type === 'READY_STOCK' && $line->material) {
                $stillToDeliver = (float) $line->quantity - (float) ($onDeliveriesByLine[$line->id] ?? 0);
                $deliverable += max(0.0, min($stillToDeliver, (float) $this->inventoryService->getReadyProductAvailability($line->material)['on_hand']));
            }
        }

        return round($deliverable, 4);
    }

    public function createDeliveryOrder(CustomerOrder $customerOrder, User $creator, array $data): DeliveryOrder
    {
        return DB::transaction(function () use ($customerOrder, $creator, $data) {
            $customerOrder->assertCustomerConfirmation(true);
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
                $requestedByMaterial = [];

                foreach ($data['lines'] as $lineData) {
                    $qty = (float) $lineData['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $lineType = $lineData['line_type'] ?? 'MANUFACTURED';

                    if ($lineType === 'READY_STOCK') {
                        $material = Material::query()->whereKey($lineData['material_id'])->lockForUpdate()->firstOrFail();
                        $lineId = (int) ($lineData['customer_order_line_id'] ?? 0);
                        $belongsToOrder = $customerOrder->lines()->whereKey($lineId)
                            ->where('line_type', 'READY_STOCK')
                            ->where('material_id', $material->id)
                            ->exists();

                        if (! $belongsToOrder) {
                            throw new Exception('بند المنتج الجاهز لا ينتمي إلى طلب العميل المحدد للتوصيل.');
                        }

                        // Physical on_hand, not the sales-side "available" figure — that already
                        // nets out this very order line's own commitment, which would make a
                        // confirmed order's own stock look insufficient to deliver itself.
                        $requestedByMaterial[$material->id] = ($requestedByMaterial[$material->id] ?? 0) + $qty;
                        $onHand = $this->inventoryService->getReadyProductAvailability($material)['on_hand'];
                        if ($requestedByMaterial[$material->id] > $onHand) {
                            throw new Exception("كمية التوصيل المطلوبة من {$material->name_ar} تتجاوز الرصيد الفعلي بالمستودع ({$onHand}).");
                        }

                        $delivery->lines()->create([
                            'customer_order_line_id' => $lineId,
                            'line_type' => 'READY_STOCK',
                            'material_id' => $material->id,
                            'quantity' => $qty,
                            'notes' => $lineData['notes'] ?? null,
                        ]);

                        continue;
                    }

                    $po = ProductionOrder::query()->whereKey($lineData['production_order_id'])->lockForUpdate()->firstOrFail();
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
                        'line_type' => 'MANUFACTURED',
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
                if ($line->line_type === 'READY_STOCK') {
                    $material = Material::query()->whereKey($line->material_id)->firstOrFail();
                    $onHand = $this->inventoryService->getReadyProductAvailability($material)['on_hand'];
                    if ((float) $line->quantity > $onHand) {
                        throw new Exception("الكمية المطلوبة بالتوصيل ({$line->quantity}) من {$material->name_ar} تتجاوز الرصيد الفعلي بالمستودع ({$onHand}).");
                    }

                    continue;
                }

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
                'assigned_at' => now(),
                'assigned_by_user_id' => $assigner->id,
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
                if ($line->line_type === 'READY_STOCK') {
                    $material = Material::query()->whereKey($line->material_id)->firstOrFail();
                    $this->inventoryService->deductReadyStockForDelivery($material, (float) $line->quantity, $line, $dispatcher);

                    continue;
                }

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
    public function markDelivered(DeliveryOrder $delivery, User $user, ?string $notes = null, ?string $recipientName = null): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $user, $notes, $recipientName) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, 'DELIVERED');

            $oldStatus = $delivery->status;
            $delivery->update([
                'status' => 'DELIVERED',
                'delivered_at' => now(),
                'recipient_name' => $recipientName ?: null,
                'delivery_notes' => $notes ?? $delivery->delivery_notes,
            ]);

            $summary = $notes ?? 'تم تسليم المنتجات للعميل بنجاح';
            if ($recipientName) {
                $summary .= " — المستلم: {$recipientName}";
            }
            $this->recordEvent($delivery, 'DELIVERED', $oldStatus, 'DELIVERED', $user, $summary);

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
     * The driver could not complete the delivery (FAILED) or the customer asked for another day (RESCHEDULED).
     *
     * Returned goods go back to sellable stock, or to the inspection area when the reason points at the
     * product itself (refused for a defect / wrong spec, transport damage). Quality reasons also open a
     * quality incident on each production order. Goods not returned stay recorded as "with the driver".
     */
    public function failOrReschedule(DeliveryOrder $delivery, User $user, string $newStatus, ?string $reason = null, ?string $newDate = null, bool $returnedToFactory = true, ?string $reasonCode = null): DeliveryOrder
    {
        if (! in_array($newStatus, ['FAILED', 'RESCHEDULED', 'CANCELLED'], true)) {
            throw new Exception('حالة غير صالحة للتعثر أو الإلغاء.');
        }

        $reasonCode = array_key_exists((string) $reasonCode, DeliveryOrder::FAILURE_REASONS) ? $reasonCode : 'OTHER';

        return DB::transaction(function () use ($delivery, $user, $newStatus, $reason, $newDate, $returnedToFactory, $reasonCode) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTransition($delivery->status, $newStatus);
            $oldStatus = $delivery->status;
            $reasonText = $reason ?: DeliveryOrder::FAILURE_REASONS[$reasonCode];

            $goodsLocation = 'FACTORY';
            if ($delivery->isDispatched()) {
                if ($returnedToFactory) {
                    $goodsLocation = $this->returnGoodsToFactory($delivery, $user, $reasonText, $reasonCode, $oldStatus, $newStatus);
                } else {
                    $goodsLocation = 'WITH_DRIVER';
                }
            }

            $delivery->update([
                'status' => $newStatus,
                'failure_reason_code' => $reasonCode,
                'goods_location' => $goodsLocation,
                'scheduled_delivery_date' => $newDate ?? $delivery->scheduled_delivery_date,
                'delivery_notes' => "سبب الحدوث: {$reasonText}",
            ]);

            $eventType = match ($newStatus) {
                'RESCHEDULED' => 'RESCHEDULED',
                'CANCELLED' => 'CANCELLED',
                default => 'FAILED',
            };
            $this->recordEvent($delivery, $eventType, $oldStatus, $newStatus, $user, $reasonText);

            if (in_array($reasonCode, DeliveryOrder::QUALITY_REASONS, true)) {
                $this->openQualityIncidents($delivery, $user, $reasonCode, $reasonText);
            }

            return $delivery;
        });
    }

    /**
     * The goods of a failed delivery that stayed with the driver are now back at the factory.
     */
    public function confirmGoodsReturned(DeliveryOrder $delivery, User $user): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $user) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();

            if (! $delivery->isAwaitingDecision() || $delivery->goods_location !== 'WITH_DRIVER') {
                throw new Exception('لا توجد بضاعة مسجلة مع فريق التوصيل لهذا الأمر.');
            }

            $reasonText = $delivery->failureReasonLabel() ?? 'تعذر التوصيل';
            $location = $this->returnGoodsToFactory($delivery, $user, $reasonText, $delivery->failure_reason_code ?? 'OTHER', $delivery->status, $delivery->status);
            $delivery->update(['goods_location' => $location]);

            return $delivery;
        });
    }

    /**
     * Management decision: deliver again on a new date. A successor delivery order is created
     * (a delivery line can only be dispatched once) and this one is closed with a link to it.
     */
    public function rescheduleAsNew(DeliveryOrder $delivery, User $user, string $newDate, ?string $notes = null): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $user, $newDate, $notes) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertDecisionPossible($delivery);

            $successor = $this->createDeliveryOrder($delivery->customerOrder, $user, [
                'scheduled_delivery_date' => $newDate,
                'scheduled_time_notes' => $delivery->scheduled_time_notes,
                'customer_name_snapshot' => $delivery->customer_name_snapshot,
                'customer_phone_snapshot' => $delivery->customer_phone_snapshot,
                'city_snapshot' => $delivery->city_snapshot,
                'district_snapshot' => $delivery->district_snapshot,
                'delivery_address_snapshot' => $delivery->delivery_address_snapshot,
                'location_notes' => $delivery->location_notes,
                'installation_required' => $delivery->installation_required,
                'delivery_notes' => trim("إعادة جدولة للأمر {$delivery->delivery_number}. ".($notes ?? '')),
                'lines' => $delivery->lines->map(fn ($line) => [
                    'line_type' => $line->line_type,
                    'production_order_id' => $line->production_order_id,
                    'material_id' => $line->material_id,
                    'customer_order_line_id' => $line->customer_order_line_id,
                    'quantity' => $line->quantity,
                    'notes' => $line->notes,
                ])->all(),
            ]);
            $this->markReady($successor, $user);

            $delivery->update(['successor_delivery_order_id' => $successor->id]);
            $this->recordEvent($delivery, 'RESCHEDULED', $delivery->status, $delivery->status, $user, "أعيدت الجدولة بأمر التوصيل {$successor->delivery_number} بتاريخ {$newDate}");

            return $successor->fresh();
        });
    }

    /**
     * Management decision: the delivery will not happen; the goods are already back.
     */
    public function cancelAfterFailure(DeliveryOrder $delivery, User $user, string $reason): DeliveryOrder
    {
        return DB::transaction(function () use ($delivery, $user, $reason) {
            $delivery = DeliveryOrder::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $this->assertDecisionPossible($delivery);
            $this->assertTransition($delivery->status, 'CANCELLED');

            $oldStatus = $delivery->status;
            $delivery->update(['status' => 'CANCELLED']);
            $this->recordEvent($delivery, 'CANCELLED', $oldStatus, 'CANCELLED', $user, "إلغاء التوصيل بعد التعثر: {$reason}");

            return $delivery;
        });
    }

    private function assertDecisionPossible(DeliveryOrder $delivery): void
    {
        if (! $delivery->isAwaitingDecision()) {
            throw new Exception('هذا الأمر ليس بانتظار قرار (قد يكون أعيدت جدولته أو أُلغي).');
        }

        if ($delivery->goods_location === 'WITH_DRIVER') {
            throw new Exception('البضاعة ما زالت مع فريق التوصيل. أكّد عودتها للمصنع أولاً.');
        }
    }

    /**
     * Book the dispatched goods back in. Returns where they went (FACTORY or QUARANTINE).
     */
    private function returnGoodsToFactory(DeliveryOrder $delivery, User $user, string $reasonText, string $reasonCode, string $oldStatus, string $newStatus): string
    {
        $toQuarantine = in_array($reasonCode, DeliveryOrder::QUARANTINE_REASONS, true);
        $warehouse = $toQuarantine ? $this->finishedGoodsService->quarantineWarehouse() : $this->finishedGoodsService->finishedGoodsWarehouse();

        foreach ($delivery->lines()->lockForUpdate()->get() as $line) {
            if ($line->line_type === 'READY_STOCK') {
                $this->inventoryService->returnReadyStockForDelivery($line, $user, $reasonText);

                continue;
            }

            FinishedGoodsMovement::create([
                'movement_number' => DocumentNumberService::generateFinishedGoodsMovementNumber(),
                'production_order_id' => $line->production_order_id,
                'customer_order_id' => $delivery->customer_order_id,
                'warehouse_id' => $warehouse->id,
                'delivery_order_id' => $delivery->id,
                'delivery_order_line_id' => $line->id,
                'movement_type' => 'DELIVERY_RETURN',
                'direction' => 'IN',
                'quantity' => $line->quantity,
                'occurred_at' => now(),
                'performed_by_user_id' => $user->id,
                'notes' => ($toQuarantine ? 'إعادة المنتجات لمنطقة الفحص' : 'إعادة المنتجات للمستودع')." بسبب تعثر التوصيل: {$reasonText}",
            ]);
        }

        $where = $toQuarantine ? 'منطقة الفحص' : 'المخزون';
        $this->recordEvent($delivery, 'RETURNED_TO_FACTORY', $oldStatus, $newStatus, $user, "إعادة البضاعة إلى {$where}: {$reasonText}");

        return $toQuarantine ? 'QUARANTINE' : 'FACTORY';
    }

    /**
     * A refusal for a defect or transport damage is a quality problem: open one incident per production order.
     */
    private function openQualityIncidents(DeliveryOrder $delivery, User $user, string $reasonCode, string $reasonText): void
    {
        $department = Department::where('code', 'DELIVERY_INSTALLATION')->first() ?? Department::first();
        if (! $department) {
            return;
        }

        $delivery->lines->whereNotNull('production_order_id')->groupBy('production_order_id')->each(function ($lines) use ($delivery, $user, $reasonCode, $reasonText, $department) {
            $this->qualityIncidentService->createIncident($lines->first()->productionOrder, $user, [
                'affected_quantity' => (int) ceil($lines->sum('quantity')),
                'detected_department_id' => $department->id,
                'incident_type' => $reasonCode === 'TRANSPORT_DAMAGE' ? 'LOADING_DAMAGE' : 'WORKMANSHIP_DEFECT',
                'description' => "رُصد عند التوصيل ({$delivery->delivery_number}): {$reasonText}",
                'severity' => 'HIGH',
            ]);
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
