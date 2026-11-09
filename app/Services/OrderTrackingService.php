<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\DeliveryOrder;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives the high-level operational status of a Customer Order from authoritative
 * production and delivery execution records (spec §30, §31, §32, §130–§135).
 *
 * The Customer Order's own stored status (sales/review lifecycle) is never
 * overwritten with manufacturing steps; this status is computed on read.
 */
class OrderTrackingService
{
    public const LABELS = [
        'DRAFT' => 'مسودة',
        'WAITING_PRODUCTION_REVIEW' => 'بانتظار مراجعة الإنتاج',
        'APPROVED_FOR_PRODUCTION' => 'معتمد للإنتاج',
        'IN_PRODUCTION' => 'قيد التصنيع',
        'PRODUCTION_BLOCKED' => 'متوقف في الإنتاج',
        'PRODUCTION_COMPLETED' => 'تم الإنتاج',
        'READY_FOR_DELIVERY' => 'جاهز للتوصيل',
        'OUT_FOR_DELIVERY' => 'خارج للتوصيل',
        'DELIVERED' => 'تم التسليم',
        'FULFILLED' => 'مكتمل تشغيلياً',
        'RETURNED' => 'مرتجع',
        'CANCELLED' => 'ملغى',
    ];

    public const BADGES = [
        'DRAFT' => 'bg-secondary',
        'WAITING_PRODUCTION_REVIEW' => 'bg-warning text-dark',
        'APPROVED_FOR_PRODUCTION' => 'bg-info text-dark',
        'IN_PRODUCTION' => 'bg-primary',
        'PRODUCTION_BLOCKED' => 'bg-danger',
        'PRODUCTION_COMPLETED' => 'bg-success',
        'READY_FOR_DELIVERY' => 'bg-info text-dark',
        'OUT_FOR_DELIVERY' => 'bg-warning text-dark',
        'DELIVERED' => 'bg-success',
        'FULFILLED' => 'bg-success',
        'RETURNED' => 'bg-dark text-white',
        'CANCELLED' => 'bg-danger',
    ];

    /**
     * Derive the operational status code for a Customer Order.
     */
    public function deriveStatus(CustomerOrder $order): string
    {
        if ($order->status === 'CANCELLED') {
            return 'CANCELLED';
        }

        if ($order->status === 'DRAFT') {
            return 'DRAFT';
        }

        if (in_array($order->status, ['PENDING_PRODUCTION_REVIEW', 'NEEDS_CHANGES'], true)) {
            return 'WAITING_PRODUCTION_REVIEW';
        }

        // APPROVED_FOR_PRODUCTION — derive from production + delivery execution.
        $productionOrders = ProductionOrder::where('customer_order_id', $order->id)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $released = $productionOrders->reject(fn (ProductionOrder $po) => in_array($po->status, ['DRAFT', 'READY_FOR_RELEASE'], true));

        if ($released->isEmpty()) {
            return 'APPROVED_FOR_PRODUCTION';
        }

        if ($this->hasBlockedOperation($released)) {
            return 'PRODUCTION_BLOCKED';
        }

        $productionComplete = $released->every(fn (ProductionOrder $po) => $po->status === 'COMPLETED');

        if (! $productionComplete) {
            return 'IN_PRODUCTION';
        }

        // Production complete — look downstream to delivery.
        $deliveries = DeliveryOrder::where('customer_order_id', $order->id)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        if ($deliveries->isEmpty()) {
            return 'PRODUCTION_COMPLETED';
        }

        if ($deliveries->contains(fn (DeliveryOrder $d) => $d->status === 'OUT_FOR_DELIVERY')) {
            return 'OUT_FOR_DELIVERY';
        }

        if ($deliveries->every(fn (DeliveryOrder $d) => $d->isCompleted())) {
            return 'FULFILLED';
        }

        if ($deliveries->contains(fn (DeliveryOrder $d) => $d->status === 'DELIVERED')) {
            return 'DELIVERED';
        }

        if ($deliveries->contains(fn (DeliveryOrder $d) => in_array($d->status, ['READY_FOR_DELIVERY', 'ASSIGNED'], true))) {
            return 'READY_FOR_DELIVERY';
        }

        return 'PRODUCTION_COMPLETED';
    }

    /**
     * The order's path through the factory as four steps, and the one thing to do next.
     *
     * Steps: review and approval → release to production → manufacturing in the departments →
     * delivery and installation. Production steps are skipped when every line is ready stock.
     *
     * @return array{steps: list<array{key: string, label: string, state: string, detail: ?string}>, next: array{title: string, text: string, action: ?array{label: string, url: string, icon: string}, tone: string}, deliverable: float, production_orders: Collection<int, ProductionOrder>}
     */
    public function journey(CustomerOrder $order): array
    {
        $order->loadMissing('lines.productionOrders');
        $user = auth()->user();
        $approved = in_array($order->status, ['APPROVED_FOR_PRODUCTION', 'APPROVED_FOR_FULFILLMENT'], true);
        $manufacturedLines = $order->lines->where('line_type', 'MANUFACTURED');
        $needsProduction = $manufacturedLines->isNotEmpty();
        $productionOrders = ProductionOrder::where('customer_order_id', $order->id)->where('status', '!=', 'CANCELLED')
            ->withCount('materialRequests')->orderBy('id')->get();
        $unreleasedQuantity = $manufacturedLines->sum(fn ($line) => max(0, (int) $line->quantity - (int) $line->productionOrders->where('status', '!=', 'CANCELLED')->sum('released_quantity')));
        $draftOrders = $productionOrders->whereIn('status', ['DRAFT', 'READY_FOR_RELEASE']);
        $released = $productionOrders->diff($draftOrders);
        $operations = ProductionOrderOperation::whereIn('production_order_id', $released->pluck('id'))->where('status', '!=', 'SKIPPED')->get();
        $unassigned = $operations->where('status', 'READY')->whereNull('assigned_user_id')->count();
        $withoutMaterials = $released->where('status', 'RELEASED')->where('material_requests_count', 0);
        $releaseDone = $approved && $unreleasedQuantity === 0 && $draftOrders->isEmpty();
        $productionDone = $releaseDone && $released->every(fn (ProductionOrder $po) => $po->status === 'COMPLETED');
        $deliveries = DeliveryOrder::where('customer_order_id', $order->id)->where('status', '!=', 'CANCELLED')->latest('id')->get();
        $deliverable = $approved ? app(DeliveryOrderService::class)->deliverableQuantity($order) : 0.0;
        $deliveryDone = $deliveries->isNotEmpty() && $deliverable <= 0 && (! $needsProduction || $productionDone)
            && $deliveries->every(fn (DeliveryOrder $delivery) => $delivery->isCompleted());

        $state = function (bool $done, bool $reachable): string {
            return $done ? 'done' : ($reachable ? 'current' : 'upcoming');
        };
        $steps = [
            ['key' => 'review', 'label' => 'المراجعة والاعتماد', 'state' => $state($approved, true), 'detail' => $approved ? $order->production_approved_at?->format('Y-m-d') : null],
            ['key' => 'release', 'label' => 'إطلاق الإنتاج', 'state' => $needsProduction ? $state($releaseDone, $approved) : 'skipped',
                'detail' => $needsProduction ? ($releaseDone ? $productionOrders->count().' أمر إنتاج' : ($approved ? $unreleasedQuantity.' قطعة بانتظار الإطلاق' : null)) : 'منتجات جاهزة'],
            ['key' => 'production', 'label' => 'التصنيع في الأقسام', 'state' => $needsProduction ? $state($productionDone, $releaseDone || $released->isNotEmpty()) : 'skipped',
                'detail' => $needsProduction ? ($operations->isNotEmpty() ? $operations->where('status', 'COMPLETED')->count().' من '.$operations->count().' مراحل' : null) : 'لا يحتاج تصنيعاً'],
            ['key' => 'delivery', 'label' => 'التوصيل والتركيب', 'state' => $state($deliveryDone, $deliverable > 0 || $deliveries->isNotEmpty()),
                'detail' => $deliveries->isNotEmpty() ? $deliveries->count().' أمر توصيل' : ($deliverable > 0 ? 'جاهز للتوصيل' : null)],
        ];

        $link = fn (string $label, string $url, string $icon, ?string $permission): ?array => $permission === null || $user?->can($permission) ? compact('label', 'url', 'icon') : null;
        $search = ['search' => $order->order_number];
        $next = match (true) {
            $order->status === 'CANCELLED' => ['title' => 'الطلب ملغى', 'text' => 'لا توجد خطوات تالية لهذا الطلب.', 'action' => null, 'tone' => 'danger'],
            $order->status === 'DRAFT' => ['title' => 'أرسل الطلب لمراجعة الإنتاج', 'text' => 'أكمل بيانات البنود ثم أرسل الطلب لمراجعة الإنتاج من الزر أعلى الصفحة.', 'action' => null, 'tone' => 'secondary'],
            ! $approved => ['title' => 'مراجعة الإنتاج واعتماد الطلب', 'text' => 'يراجع مدير الإنتاج المقاسات والوصفات ثم يعتمد الطلب للإنتاج.', 'action' => $link('مراجعة واعتماد الطلب', route('sales.orders.review', $order), 'fa-clipboard-check', 'orders.review_production'), 'tone' => 'warning'],
            $needsProduction && ! $releaseDone => ['title' => 'أطلق أوامر الإنتاج', 'text' => "الطلب معتمد لكن لم يدخل الإنتاج بعد: {$unreleasedQuantity} قطعة بانتظار الإطلاق مع اختيار مسار التصنيع.", 'action' => $link('إطلاق للإنتاج', route('production.pipeline.index', ['stage' => 'release'] + $search), 'fa-rocket', 'production.release'), 'tone' => 'primary'],
            $withoutMaterials->isNotEmpty() => ['title' => 'اطلب مواد الإنتاج', 'text' => 'أُطلق أمر الإنتاج ولم تُطلب مواده من المستودع بعد.', 'action' => $link('طلب المواد', route('production.pipeline.index', ['stage' => 'materials'] + $search), 'fa-boxes-packing', 'production.material_requests'), 'tone' => 'primary'],
            $unassigned > 0 => ['title' => 'أسند المراحل للأقسام', 'text' => "{$unassigned} مرحلة جاهزة للعمل ولم تُسند لموظف في القسم المختص.", 'action' => $link('إسناد للأقسام', route('production.pipeline.index', ['stage' => 'assign'] + $search), 'fa-people-arrows', 'production.assignment.manage'), 'tone' => 'primary'],
            $needsProduction && ! $productionDone => ['title' => 'قيد التصنيع في الأقسام', 'text' => 'المراحل تسير حسب مسار التصنيع؛ يُتاح التوصيل بعد اكتمال الإنتاج واستلام المنتج الجاهز.', 'action' => $released->first() ? $link('متابعة أمر الإنتاج', route('production.orders.show', $released->first()), 'fa-industry', 'production.view') : null, 'tone' => 'info'],
            $deliverable > 0 => ['title' => 'جاهز للتوصيل والتركيب', 'text' => 'المنتجات جاهزة في المستودع. أنشئ أمر التوصيل وحدد الموعد والسائق.', 'action' => $link('إنشاء أمر توصيل وتركيب', route('delivery.orders.create', ['customer_order_id' => $order->id]), 'fa-truck-ramp-box', 'delivery.create'), 'tone' => 'success'],
            $deliveries->isNotEmpty() && ! $deliveryDone => ['title' => 'قيد التوصيل', 'text' => 'تابع أمر التوصيل حتى التسليم والتركيب.', 'action' => $link('متابعة أمر التوصيل', route('delivery.orders.show', $deliveries->first()), 'fa-truck-fast', 'delivery.view'), 'tone' => 'info'],
            $deliveryDone => ['title' => 'اكتمل الطلب', 'text' => 'تم الإنتاج والتسليم لجميع البنود.', 'action' => null, 'tone' => 'success'],
            default => ['title' => 'بانتظار استلام المنتج الجاهز', 'text' => 'اكتمل التصنيع؛ يُتاح التوصيل عند استلام المنتجات الجاهزة في المستودع.', 'action' => null, 'tone' => 'info'],
        };

        return ['steps' => $steps, 'next' => $next, 'deliverable' => $deliverable, 'production_orders' => $released];
    }

    public function statusLabel(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public function statusBadgeClass(string $status): string
    {
        return self::BADGES[$status] ?? 'bg-secondary';
    }

    /**
     * Reusable "Order Tracking Summary" payload (spec §57, §130).
     *
     * @return array{status: string, status_label: string, badge_class: string, current_stage: string, stages_completed: int, stages_total: int, has_open_rework: bool, is_blocked: bool, last_update: ?Carbon}
     */
    public function summary(CustomerOrder $order): array
    {
        $status = $this->deriveStatus($order);

        $operations = ProductionOrderOperation::whereHas(
            'productionOrder',
            fn ($po) => $po->where('customer_order_id', $order->id)->where('status', '!=', 'CANCELLED')
        )->with('workCenter.department', 'activeBlock')->get();

        $counted = $operations->where('status', '!=', 'SKIPPED');
        $stagesTotal = $counted->count();
        $stagesCompleted = $counted->where('status', 'COMPLETED')->count();

        $hasOpenRework = ProductionReworkAction::whereHas(
            'productionOrder',
            fn ($po) => $po->where('customer_order_id', $order->id)
        )->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->exists();

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'badge_class' => $this->statusBadgeClass($status),
            'current_stage' => $this->currentStage($operations, $status),
            'stages_completed' => $stagesCompleted,
            'stages_total' => $stagesTotal,
            'has_open_rework' => $hasOpenRework,
            'is_blocked' => $status === 'PRODUCTION_BLOCKED',
            'last_update' => $operations->max('updated_at') ?? $order->updated_at,
        ];
    }

    /**
     * Ordered process-tracker rows for a single Production Order (spec §58, §60).
     *
     * @return list<array{code: string, name: string, status: string, status_label: string, badge_class: string, completed: int, required: int, branch_key: ?string}>
     */
    public function productionProgress(ProductionOrder $productionOrder): array
    {
        return $productionOrder->operations()
            ->with('workCenter')
            ->get()
            ->map(fn (ProductionOrderOperation $op) => [
                'code' => $op->operation_code,
                'name' => $op->operation_name_snapshot,
                'status' => $op->status,
                'status_label' => $op->status_arabic,
                'badge_class' => $op->status_badge_class,
                'completed' => (int) $op->completed_quantity,
                'required' => (int) $op->required_quantity,
                'branch_key' => $op->branch_key,
            ])->all();
    }

    /**
     * @param  Collection<int, ProductionOrder>  $productionOrders
     */
    private function hasBlockedOperation($productionOrders): bool
    {
        return ProductionOrderOperation::whereIn('production_order_id', $productionOrders->pluck('id'))
            ->where('status', 'BLOCKED')
            ->exists();
    }

    /**
     * Human-readable current manufacturing stage (spec §31, §131, §132).
     *
     * @param  Collection<int, ProductionOrderOperation>  $operations
     */
    private function currentStage($operations, string $status): string
    {
        if ($status === 'PRODUCTION_BLOCKED') {
            $blocked = $operations->firstWhere('status', 'BLOCKED');
            $reason = $blocked?->activeBlock?->reason_arabic;

            return $reason ? "متوقف — {$reason}" : 'متوقف في الإنتاج';
        }

        if (in_array($status, ['READY_FOR_DELIVERY', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FULFILLED', 'PRODUCTION_COMPLETED'], true)) {
            return $this->statusLabel($status);
        }

        if ($status !== 'IN_PRODUCTION') {
            return $this->statusLabel($status);
        }

        $active = $operations->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED']);

        if ($active->isEmpty()) {
            $active = $operations->whereIn('status', ['READY', 'ASSIGNED']);
        }

        $names = $active
            ->map(fn (ProductionOrderOperation $op) => $op->workCenter?->department?->name_ar ?? $op->operation_name_snapshot)
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return 'قيد التصنيع';
        }

        return 'قيد التصنيع — '.$names->implode(' + ');
    }
}
