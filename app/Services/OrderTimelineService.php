<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\DeliveryEvent;
use App\Models\FinishedGoodsReceipt;
use App\Models\ProductionOperationEvent;
use App\Models\ProductionOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified order journey — "سجل رحلة الطلب" (spec §28, §121, §123).
 *
 * Aggregated dynamically from authoritative event sources (no duplicate unified
 * table): production_operation_events + finished_goods_receipts + delivery_events
 * + customer-order milestones. Each source is a single bounded query (no N+1, §70).
 */
class OrderTimelineService
{
    /**
     * Build the chronological timeline for a Customer Order, newest events capped
     * at $limit, returned ascending for display.
     *
     * @return list<array{occurred_at: Carbon, category: string, type: string, label: string, actor: ?string, note: ?string, icon: string}>
     */
    public function forCustomerOrder(CustomerOrder $order, int $limit = 50, ?string $role = null): array
    {
        $rows = collect();

        // 1. Customer Order milestones.
        $rows->push($this->row($order->created_at, 'order', 'ORDER_CREATED', 'تم إنشاء طلب العميل', $order->createdBy?->name, null, 'fa-file-circle-plus'));
        if ($order->production_reviewed_at) {
            $rows->push($this->row($order->production_reviewed_at, 'order', 'PRODUCTION_REVIEWED', 'تمت مراجعة الطلب للإنتاج', $order->productionReviewedBy?->name, null, 'fa-clipboard-check'));
        }
        if ($order->production_approved_at) {
            $rows->push($this->row($order->production_approved_at, 'order', 'PRODUCTION_APPROVED', 'تم اعتماد الطلب للإنتاج', $order->productionApprovedBy?->name, null, 'fa-circle-check'));
        }

        $productionOrderIds = ProductionOrder::where('customer_order_id', $order->id)->pluck('id');

        // 2. Production execution events.
        if ($productionOrderIds->isNotEmpty() && $this->roleSeesProduction($role)) {
            ProductionOperationEvent::whereIn('production_order_id', $productionOrderIds)
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->each(function (ProductionOperationEvent $e) use ($rows) {
                    $rows->push($this->row(
                        $e->occurred_at,
                        'production',
                        $e->event_type,
                        $this->decorateProductionLabel($e),
                        $e->user_name_snapshot,
                        $e->note,
                        $this->productionIcon($e->event_type),
                        $e->id,
                    ));
                });
        }

        // 3. Finished-goods handover (posted receipts only — spec §51).
        FinishedGoodsReceipt::where('customer_order_id', $order->id)
            ->where('status', 'POSTED')
            ->get()
            ->each(function (FinishedGoodsReceipt $r) use ($rows) {
                $rows->push($this->row($r->posted_at ?? $r->updated_at, 'finished_goods', 'FINISHED_GOODS_RECEIVED', 'تم تحويل المنتج إلى المنتجات الجاهزة', $r->receivedByUser?->name, null, 'fa-boxes-stacked', $r->id));
            });

        // 4. Delivery / installation events.
        $deliveryIds = $order->deliveryOrders()->pluck('id');
        if ($deliveryIds->isNotEmpty()) {
            DeliveryEvent::whereIn('delivery_order_id', $deliveryIds)
                ->with('user')
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->each(function (DeliveryEvent $e) use ($rows) {
                    $rows->push($this->row(
                        $e->occurred_at,
                        'delivery',
                        $e->event_type,
                        $this->deliveryLabel($e->event_type),
                        $e->user?->name,
                        $e->notes,
                        $this->deliveryIcon($e->event_type),
                        $e->id,
                    ));
                });
        }

        return $this->orderAndCap($rows, $limit);
    }

    /**
     * Production-only timeline for a single Production Order (used on PO show).
     *
     * @return list<array{occurred_at: Carbon, category: string, type: string, label: string, actor: ?string, note: ?string, icon: string}>
     */
    public function forProductionOrder(ProductionOrder $productionOrder, int $limit = 50): array
    {
        $rows = ProductionOperationEvent::where('production_order_id', $productionOrder->id)
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ProductionOperationEvent $e) => $this->row(
                $e->occurred_at,
                'production',
                $e->event_type,
                $this->decorateProductionLabel($e),
                $e->user_name_snapshot,
                $e->note,
                $this->productionIcon($e->event_type),
                $e->id,
            ));

        return $this->orderAndCap(collect($rows), $limit);
    }

    /**
     * Sorts rows chronologically and caps them to the most recent $limit.
     *
     * occurred_at alone is not a reliable tiebreaker: database timestamp
     * columns commonly store only second-level precision, so two events
     * recorded a moment apart can carry an identical occurred_at. Each row's
     * `seq` (the source record's auto-increment id, which is assigned in
     * true creation order) breaks ties and keeps the timeline chronological.
     *
     * @param  Collection<int, array>  $rows
     * @return list<array>
     */
    private function orderAndCap(Collection $rows, int $limit): array
    {
        $sortKey = fn (array $r) => sprintf('%020d-%020d', $r['occurred_at']?->getTimestamp() ?? 0, $r['seq'] ?? 0);

        return $rows
            ->sortByDesc($sortKey)
            ->take($limit)
            ->sortBy($sortKey)
            ->values()
            ->map(fn (array $r) => Arr::except($r, 'seq'))
            ->all();
    }

    private function row(?Carbon $at, string $category, string $type, string $label, ?string $actor, ?string $note, string $icon, int $seq = 0): array
    {
        return [
            'occurred_at' => $at,
            'category' => $category,
            'type' => $type,
            'label' => $label,
            'actor' => $actor,
            'note' => $note,
            'icon' => $icon,
            'seq' => $seq,
        ];
    }

    private function decorateProductionLabel(ProductionOperationEvent $e): string
    {
        $base = $e->label_arabic;
        $stage = $e->work_center_name_snapshot ?? $e->department_name_snapshot;

        if ($stage && in_array($e->event_type, ['STARTED', 'COMPLETED', 'READY', 'BLOCKED', 'BLOCK_RESOLVED'], true)) {
            return "{$base} — {$stage}";
        }

        return $base;
    }

    private function roleSeesProduction(?string $role): bool
    {
        // Delivery drivers see a delivery-relevant timeline (spec §29); everyone else sees production detail.
        return $role !== 'delivery';
    }

    private function productionIcon(string $type): string
    {
        return match ($type) {
            'READY' => 'fa-flag',
            'ASSIGNED', 'CLAIMED', 'REASSIGNED' => 'fa-user-check',
            'STARTED' => 'fa-play',
            'PROGRESS_RECORDED' => 'fa-arrow-trend-up',
            'BLOCKED' => 'fa-hand',
            'BLOCK_RESOLVED' => 'fa-unlock',
            'COMPLETED' => 'fa-circle-check',
            'REWORK_CREATED', 'REWORK_COMPLETED' => 'fa-rotate',
            default => 'fa-circle',
        };
    }

    private function deliveryLabel(string $type): string
    {
        return match ($type) {
            'CREATED' => 'تم إنشاء أمر التوصيل',
            'READY' => 'أصبح المنتج جاهزاً للتوصيل',
            'ASSIGNED' => 'تم إسناد التوصيل',
            'DISPATCHED' => 'خرج الطلب للتوصيل',
            'DELIVERED' => 'تم التسليم',
            'INSTALLED' => 'تم التركيب',
            'FAILED' => 'تعذر التسليم',
            'RESCHEDULED' => 'تمت إعادة جدولة التوصيل',
            'RETURNED_TO_FACTORY' => 'تم إرجاع المنتج للمصنع',
            'NOTE' => 'ملاحظة توصيل',
            default => $type,
        };
    }

    private function deliveryIcon(string $type): string
    {
        return match ($type) {
            'READY' => 'fa-box-open',
            'ASSIGNED' => 'fa-user-check',
            'DISPATCHED' => 'fa-truck-fast',
            'DELIVERED' => 'fa-house-circle-check',
            'INSTALLED' => 'fa-screwdriver-wrench',
            'FAILED' => 'fa-triangle-exclamation',
            'RESCHEDULED' => 'fa-calendar-day',
            'RETURNED_TO_FACTORY' => 'fa-rotate-left',
            default => 'fa-truck',
        };
    }
}
