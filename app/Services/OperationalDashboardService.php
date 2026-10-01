<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Models\DeliveryOrder;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReturn;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\QualityIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the role-oriented "what do I need to do now?" dashboard.
 *
 * Each section is included only when the user holds the permission that owns that work,
 * and every count is computed only for included sections (no wasted queries, no data leakage).
 */
class OperationalDashboardService
{
    public const ACTIVE_OPERATION_STATUSES = ['READY', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'];

    public const ACTIVE_DELIVERY_STATUSES = ['READY_FOR_DELIVERY', 'ASSIGNED', 'OUT_FOR_DELIVERY', 'DELIVERED'];

    public function __construct(protected ReceivablesReportingService $receivablesReportingService) {}

    /**
     * @return list<array{key: string, title: string, icon: string, tiles: list<array{label: string, value: int|float, display: string, icon: string, tone: string, url: string, attention: bool}>}>
     */
    public function sectionsFor(User $user): array
    {
        $sections = [];

        if ($user->can('production.update_progress')) {
            $sections[] = $this->productionTasksSection($user);
        }

        if ($user->can('delivery.complete') || $user->can('delivery.dispatch')) {
            $sections[] = $this->deliverySection($user);
        }

        if ($user->can('inventory.issue') || $user->can('inventory.receive')) {
            $sections[] = $this->warehouseSection($user);
        }

        if ($user->can('orders.review_production')) {
            $sections[] = $this->productionManagementSection();
        }

        if ($user->can('orders.create') && ! $user->can('orders.review_production')) {
            $sections[] = $this->customerServiceSection();
        }

        if ($user->can('purchasing.view')) {
            $sections[] = $this->purchasingSection();
        }

        if ($user->can('receivables.view')) {
            $sections[] = $this->receivablesSection();
        }

        return $sections;
    }

    /**
     * Operations visible to the user in the department work queue (same isolation rule as DepartmentQueueController).
     */
    public function operationsQueryFor(User $user): Builder
    {
        $query = ProductionOrderOperation::query()
            ->whereHas('productionOrder', fn (Builder $po) => $po->whereNotIn('status', ['ON_HOLD', 'CANCELLED', 'COMPLETED']));

        if ($this->isDepartmentScoped($user)) {
            $query->whereHas('workCenter', fn (Builder $wc) => $wc->where('department_id', $user->department_id));
        }

        return $query;
    }

    public function isDepartmentScoped(User $user): bool
    {
        return ! $user->isAdministrator() && ! $user->hasRole('production_manager') && (bool) $user->department_id;
    }

    /**
     * Deliveries the user is responsible for (drivers only see their own assignments).
     */
    public function deliveriesQueryFor(User $user): Builder
    {
        $query = DeliveryOrder::query();

        if (! $user->isAdministrator() && ! $user->hasRole('production_manager')) {
            $query->where('assigned_user_id', $user->id);
        }

        return $query;
    }

    private function productionTasksSection(User $user): array
    {
        $waiting = (clone $this->operationsQueryFor($user))->where('status', 'READY')->count();
        $inProgress = (clone $this->operationsQueryFor($user))->whereIn('status', ['IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count();
        $rework = (clone $this->operationsQueryFor($user))
            ->whereIn('status', self::ACTIVE_OPERATION_STATUSES)
            ->whereHas('targetReworkActions', fn (Builder $r) => $r->whereNotIn('status', ['COMPLETED', 'CANCELLED']))
            ->count();
        $completedTodayQuery = ProductionOrderOperation::query()
            ->where('status', 'COMPLETED')
            ->whereDate('completed_at', today());
        if ($this->isDepartmentScoped($user)) {
            $completedTodayQuery->whereHas('workCenter', fn (Builder $wc) => $wc->where('department_id', $user->department_id));
        }

        return [
            'key' => 'production_tasks',
            'title' => 'مهام قسمي الإنتاجية',
            'icon' => 'fa-list-check',
            'tiles' => [
                $this->tile('مهام بانتظار البدء', $waiting, 'fa-hourglass-start', 'info', route('production.queue.index', ['tab' => 'waiting'])),
                $this->tile('مهام قيد التنفيذ', $inProgress, 'fa-gears', 'warning', route('production.queue.index', ['tab' => 'in_progress'])),
                $this->tile('مهام إعادة عمل', $rework, 'fa-rotate', 'danger', route('production.queue.index', ['tab' => 'rework'])),
                $this->tile('أُنجزت اليوم', $completedTodayQuery->count(), 'fa-circle-check', 'success', route('production.queue.index', ['tab' => 'completed_today']), false),
            ],
        ];
    }

    private function deliverySection(User $user): array
    {
        $base = fn () => $this->deliveriesQueryFor($user);

        return [
            'key' => 'delivery',
            'title' => 'مهام التوصيل والتركيب',
            'icon' => 'fa-truck-fast',
            'tiles' => [
                $this->tile('توصيلات اليوم', $base()->whereIn('status', self::ACTIVE_DELIVERY_STATUSES)->whereDate('scheduled_delivery_date', today())->count(), 'fa-calendar-day', 'primary', route('delivery.orders.my-tasks', ['filter' => 'today'])),
                $this->tile('مُسندة وجاهزة للانطلاق', $base()->where('status', 'ASSIGNED')->count(), 'fa-user-check', 'info', route('delivery.orders.my-tasks', ['filter' => 'assigned'])),
                $this->tile('خارج للتوصيل', $base()->where('status', 'OUT_FOR_DELIVERY')->count(), 'fa-truck-fast', 'warning', route('delivery.orders.my-tasks', ['filter' => 'out'])),
                $this->tile('بانتظار التركيب', $base()->where('status', 'DELIVERED')->where('installation_required', true)->count(), 'fa-screwdriver-wrench', 'primary', route('delivery.orders.my-tasks', ['filter' => 'installation'])),
                $this->tile('متعذرة / معاد جدولتها', $base()->whereIn('status', ['FAILED', 'RESCHEDULED'])->count(), 'fa-triangle-exclamation', 'danger', route('delivery.orders.my-tasks', ['filter' => 'exceptions'])),
            ],
        ];
    }

    private function warehouseSection(User $user): array
    {
        $tiles = [];

        if ($user->can('inventory.issue')) {
            $tiles[] = $this->tile('طلبات مواد تنتظر الصرف', ProductionMaterialRequest::whereIn('status', ['SUBMITTED', 'PARTIALLY_FULFILLED'])->count(), 'fa-dolly', 'warning', route('production.material-requests.index', ['status' => 'SUBMITTED']));
        }

        if ($user->can('inventory.receive')) {
            $tiles[] = $this->tile('سندات استلام بانتظار الترحيل', MaterialReceipt::where('status', 'DRAFT')->count(), 'fa-truck-ramp-box', 'info', route('inventory.receipts.index', ['status' => 'DRAFT']));
        }

        $tiles[] = $this->tile('مواد منخفضة المخزون', $this->lowStockMaterialsCount(), 'fa-triangle-exclamation', 'danger', route('inventory.balances.index'));

        if ($user->can('inventory.return')) {
            $tiles[] = $this->tile('مرتجعات مواد بانتظار الترحيل', MaterialReturn::where('status', 'DRAFT')->count(), 'fa-rotate-left', 'secondary', route('inventory.returns.index', ['status' => 'DRAFT']));
        }

        return [
            'key' => 'warehouse',
            'title' => 'أعمال المستودع',
            'icon' => 'fa-warehouse',
            'tiles' => $tiles,
        ];
    }

    private function productionManagementSection(): array
    {
        $activeStatuses = ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'];

        return [
            'key' => 'production_management',
            'title' => 'متابعة الإنتاج',
            'icon' => 'fa-industry',
            'tiles' => [
                $this->tile('طلبات بانتظار مراجعة الإنتاج', CustomerOrder::where('status', 'PENDING_PRODUCTION_REVIEW')->count(), 'fa-clipboard-check', 'warning', route('sales.orders.index', ['status' => 'PENDING_PRODUCTION_REVIEW'])),
                $this->tile('أوامر إنتاج متأخرة', ProductionOrder::whereIn('status', $activeStatuses)->whereNotNull('planned_completion_date')->whereDate('planned_completion_date', '<', today())->count(), 'fa-clock', 'danger', route('production.orders.index')),
                $this->tile('أوامر إنتاج متوقفة', ProductionOrder::where('status', 'ON_HOLD')->count(), 'fa-circle-pause', 'secondary', route('production.orders.index', ['status' => 'ON_HOLD'])),
                $this->tile('نواقص بانتظار الشراء', PurchaseRequest::where('source_type', 'PRODUCTION_SHORTAGE')->whereIn('status', ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED'])->count(), 'fa-cart-flatbed', 'warning', route('purchasing.planning.index')),
                $this->tile('حوادث جودة مفتوحة', QualityIncident::whereNotIn('status', ['RESOLVED', 'CANCELLED', 'CLOSED'])->count(), 'fa-triangle-exclamation', 'danger', route('production.quality-incidents.index')),
                $this->tile('إعادة عمل مفتوحة', ProductionReworkAction::whereNotIn('status', ['COMPLETED', 'CANCELLED'])->count(), 'fa-rotate', 'danger', route('production.rework.index')),
            ],
        ];
    }

    private function customerServiceSection(): array
    {
        return [
            'key' => 'customer_service',
            'title' => 'طلبات العملاء',
            'icon' => 'fa-headset',
            'tiles' => [
                $this->tile('طلبات مسودة لم تُرسل', CustomerOrder::where('status', 'DRAFT')->count(), 'fa-file-pen', 'secondary', route('sales.orders.index', ['status' => 'DRAFT'])),
                $this->tile('بانتظار مراجعة الإنتاج', CustomerOrder::where('status', 'PENDING_PRODUCTION_REVIEW')->count(), 'fa-hourglass-half', 'warning', route('sales.orders.index', ['status' => 'PENDING_PRODUCTION_REVIEW']), false),
                $this->tile('معتمدة للإنتاج', CustomerOrder::where('status', 'APPROVED_FOR_PRODUCTION')->count(), 'fa-industry', 'primary', route('sales.orders.index', ['status' => 'APPROVED_FOR_PRODUCTION']), false),
            ],
        ];
    }

    private function purchasingSection(): array
    {
        $openPoStatuses = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'];

        return [
            'key' => 'purchasing',
            'title' => 'المشتريات',
            'icon' => 'fa-cart-shopping',
            'tiles' => [
                $this->tile('طلبات شراء بانتظار الإجراء', PurchaseRequest::whereIn('status', ['SUBMITTED', 'UNDER_REVIEW'])->count(), 'fa-file-signature', 'warning', route('purchasing.requests.index', ['status' => 'SUBMITTED'])),
                $this->tile('أوامر شراء مفتوحة', PurchaseOrder::whereIn('status', $openPoStatuses)->count(), 'fa-file-invoice', 'primary', route('purchasing.orders.index'), false),
                $this->tile('أوامر شراء متأخرة التوريد', PurchaseOrder::whereIn('status', $openPoStatuses)->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', today())->count(), 'fa-clock', 'danger', route('purchasing.orders.index')),
                $this->tile('أوامر مستلمة جزئياً', PurchaseOrder::where('status', 'PARTIALLY_RECEIVED')->count(), 'fa-circle-half-stroke', 'info', route('purchasing.orders.index', ['status' => 'PARTIALLY_RECEIVED'])),
            ],
        ];
    }

    private function receivablesSection(): array
    {
        $summary = $this->receivablesReportingService->getDashboardSummary();

        return [
            'key' => 'receivables',
            'title' => 'التحصيل والذمم',
            'icon' => 'fa-hand-holding-dollar',
            'tiles' => [
                $this->tile('دفعات بانتظار التأكيد', CustomerPayment::where('status', 'PENDING_CONFIRMATION')->count(), 'fa-money-check-dollar', 'warning', route('receivables.payments.index', ['status' => 'PENDING_CONFIRMATION'])),
                $this->moneyTile('أرصدة متأخرة السداد', (float) $summary['overdue_outstanding'], 'fa-clock', 'danger', route('receivables.reports.aging')),
                $this->tile('طلبات موقوفة التسليم', (int) $summary['delivery_blocked_orders_count'], 'fa-ban', 'danger', route('receivables.customers.index')),
                $this->tile('عملاء تجاوزوا حد الائتمان', (int) $summary['exceeded_credit_customers_count'], 'fa-credit-card', 'warning', route('receivables.credit.index')),
            ],
        ];
    }

    public function lowStockMaterialsCount(): int
    {
        return Material::where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(inventory_lots.remaining_quantity), 0) FROM inventory_lots WHERE inventory_lots.material_id = materials.id AND inventory_lots.status = ?) <= materials.reorder_point', ['ACTIVE'])
            ->count();
    }

    /**
     * @return array{label: string, value: int|float, display: string, icon: string, tone: string, url: string, attention: bool}
     */
    private function tile(string $label, int $value, string $icon, string $tone, string $url, bool $highlightWhenNonZero = true): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'display' => number_format($value),
            'icon' => $icon,
            'tone' => $tone,
            'url' => $url,
            'attention' => $highlightWhenNonZero && $value > 0,
        ];
    }

    /**
     * @return array{label: string, value: int|float, display: string, icon: string, tone: string, url: string, attention: bool}
     */
    private function moneyTile(string $label, float $value, string $icon, string $tone, string $url): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'display' => number_format($value, 2).' ر.س',
            'icon' => $icon,
            'tone' => $tone,
            'url' => $url,
            'attention' => $value > 0,
        ];
    }
}
