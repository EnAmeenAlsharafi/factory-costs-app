<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\DeliveryOrder;
use App\Models\FinishedGoodsMovement;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\ProductionOrderOperation;
use App\Models\PurchaseOrder;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use App\Services\CustomerCreditService;
use App\Services\FinishedGoodsService;
use App\Services\StageDeadlineService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Raises the notifications that nobody's action causes: things that become true as time passes.
 *
 * Runs from the scheduler (cron on shared hosting), and is safe to run as often as you like: every
 * notification it raises carries a dedupe key, so a late stage is announced once a day and escalated
 * as it keeps slipping, never repeated on every pass.
 */
class ScanOperationalNotifications extends Command
{
    protected $signature = 'notifications:scan {--prune : also delete notifications nobody needs any more}';

    protected $description = 'Raise deadline, stock, delivery and receivable notifications, and prune old ones';

    public function handle(NotificationService $notifications, StageDeadlineService $deadlines): int
    {
        $today = now()->toDateString();

        $this->scanStageDeadlines($notifications, $deadlines, $today);
        $this->scanMaterialStock($notifications, $today);
        $this->scanLatePurchaseOrders($notifications, $today);
        $this->scanDeliveries($notifications, $today);
        $this->scanIdleFinishedGoods($notifications, $today);
        $this->scanReceivables($notifications, $today);

        if ($this->option('prune')) {
            $pruned = $notifications->prune();
            $this->info("Pruned {$pruned['read']} read and {$pruned['unread']} unread notifications.");
        }

        return self::SUCCESS;
    }

    /**
     * A stage approaching its department's target, and one that has passed it.
     *
     * An overdue stage escalates instead of repeating: the department hears about it every day,
     * production management from the third day, the administrator from the fifth.
     */
    private function scanStageDeadlines(NotificationService $notifications, StageDeadlineService $deadlines, string $today): void
    {
        $stages = ProductionOrderOperation::query()
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['COMPLETED', 'SKIPPED'])
            ->with(['productionOrder', 'workCenter.department', 'blocks'])
            ->get();

        foreach ($stages as $stage) {
            $status = $deadlines->status($stage);
            $order = $stage->productionOrder;

            if (! $order || in_array($order->status, ['ON_HOLD', 'CANCELLED', 'COMPLETED'], true)) {
                continue;
            }

            if ($status['state'] === 'warning') {
                $notifications->raise(NotificationType::StageDueSoon, [
                    'title' => 'اقترب موعد تسليم مرحلة في قسمك',
                    'body' => "{$stage->operation_name_snapshot} — أمر الإنتاج {$order->production_order_number} — {$status['label']}",
                    'url' => route('production.queue.index'),
                    'subject' => $stage,
                    'department_id' => $stage->workCenter?->department_id,
                    'dedupe_key' => "STAGE_DUE_SOON:{$stage->id}:{$today}",
                ]);

                continue;
            }

            if ($status['state'] !== 'overdue') {
                continue;
            }

            $lateDays = $status['days'];
            $audiences = ['department'];
            if ($lateDays >= 3) {
                $audiences[] = 'permission:production.release';
            }
            if ($lateDays >= 5) {
                $audiences[] = 'role:admin';
            }

            $notifications->raise(NotificationType::StageOverdue, [
                'title' => 'مرحلة تأخرت عن موعد القسم',
                'body' => "{$stage->operation_name_snapshot} — أمر الإنتاج {$order->production_order_number} — {$status['label']}"
                    .($status['blocked'] ? ' (خُصم وقت التوقف على العوائق)' : ''),
                'url' => route('production.orders.show', $order->id),
                'subject' => $stage,
                'department_id' => $stage->workCenter?->department_id,
                'audiences' => $audiences,
                'dedupe_key' => "STAGE_OVERDUE:{$stage->id}:{$today}",
            ]);
        }
    }

    private function scanMaterialStock(NotificationService $notifications, string $today): void
    {
        $onHandByMaterial = InventoryLot::query()
            ->where('status', 'ACTIVE')
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(remaining_quantity) as total')
            ->pluck('total', 'material_id');

        $materials = Material::query()
            ->where('is_active', true)
            ->where('min_stock_level', '>', 0)
            ->with('baseUnit')
            ->get()
            ->filter(fn (Material $material) => (float) ($onHandByMaterial[$material->id] ?? 0) <= (float) $material->min_stock_level);

        foreach ($materials as $material) {
            $onHand = round((float) ($onHandByMaterial[$material->id] ?? 0), 2);
            $notifications->raise(NotificationType::MaterialBelowMinimum, [
                'title' => 'خامة تحت الحد الأدنى',
                'body' => "{$material->name_ar} — الرصيد {$onHand} {$material->baseUnit?->name_ar} والحد الأدنى {$material->min_stock_level}",
                'url' => route('purchasing.planning.index'),
                'subject' => $material,
                'dedupe_key' => "MATERIAL_BELOW_MINIMUM:{$material->id}:{$today}",
            ]);
        }
    }

    private function scanLatePurchaseOrders(NotificationService $notifications, string $today): void
    {
        $orders = PurchaseOrder::query()
            ->whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])
            ->whereNotNull('expected_delivery_date')
            ->whereDate('expected_delivery_date', '<', $today)
            ->with('supplier')
            ->get();

        foreach ($orders as $order) {
            $lateDays = (int) Carbon::parse($order->expected_delivery_date)->diffInDays(now());
            $notifications->raise(NotificationType::PurchaseOrderLate, [
                'title' => 'أمر شراء تأخر عن موعد التسليم',
                'body' => "{$order->purchase_order_number} — المورد {$order->supplier?->name} — متأخر {$lateDays} يوماً",
                'url' => route('purchasing.orders.show', $order->id),
                'subject' => $order,
                'dedupe_key' => "PURCHASE_ORDER_LATE:{$order->id}:{$today}",
            ]);
        }
    }

    /**
     * Each driver's run for today, and goods that left the warehouse and never came back.
     */
    private function scanDeliveries(NotificationService $notifications, string $today): void
    {
        DeliveryOrder::query()
            ->where('status', 'ASSIGNED')
            ->whereNotNull('assigned_user_id')
            ->whereDate('scheduled_delivery_date', $today)
            ->get()
            ->groupBy('assigned_user_id')
            ->each(fn ($deliveries, $driverId) => $notifications->raise(NotificationType::DeliveryToday, [
                'title' => 'توصيلات اليوم',
                'body' => 'لديك '.$deliveries->count().' أمر توصيل مجدول اليوم: '.$deliveries->pluck('delivery_number')->implode('، '),
                'url' => route('delivery.orders.my-tasks', ['filter' => 'today']),
                'users' => [$driverId],
                'dedupe_key' => "DELIVERY_TODAY:{$driverId}:{$today}",
            ]));

        DeliveryOrder::query()
            ->where('goods_location', 'WITH_DRIVER')
            ->whereIn('status', ['FAILED', 'RESCHEDULED'])
            ->with('assignedUser')
            ->get()
            ->each(fn (DeliveryOrder $delivery) => $notifications->raise(NotificationType::GoodsWithDriverNotReturned, [
                'title' => 'بضاعة لم تُعد إلى المصنع',
                'body' => "{$delivery->delivery_number} — ما زالت مع {$delivery->assignedUser?->name} — العميل {$delivery->customer_name_snapshot}",
                'url' => route('delivery.orders.show', $delivery->id),
                'subject' => $delivery,
                'dedupe_key' => "GOODS_WITH_DRIVER:{$delivery->id}:{$today}",
            ]));
    }

    /**
     * Finished goods that have been sitting in the warehouse without a delivery order.
     */
    private function scanIdleFinishedGoods(NotificationService $notifications, string $today, int $idleDays = 7): void
    {
        $quarantineId = app(FinishedGoodsService::class)->quarantineWarehouse()->id;

        FinishedGoodsMovement::query()
            ->where('movement_type', 'PRODUCTION_RECEIPT')
            ->where('warehouse_id', '!=', $quarantineId)
            ->where('occurred_at', '<', now()->subDays($idleDays))
            ->with('customerOrder', 'productionOrder')
            ->get()
            ->groupBy('production_order_id')
            ->each(function ($movements, $productionOrderId) use ($notifications, $today, $idleDays) {
                $productionOrder = $movements->first()->productionOrder;
                if (! $productionOrder || app(FinishedGoodsService::class)->getAvailableQuantity($productionOrder) <= 0) {
                    return;
                }

                $notifications->raise(NotificationType::FinishedGoodsIdle, [
                    'title' => 'منتجات جاهزة راكدة بلا أمر توصيل',
                    'body' => "أمر الإنتاج {$productionOrder->production_order_number} — طلب العميل "
                        .($movements->first()->customerOrder?->order_number ?? '-')." — أكثر من {$idleDays} أيام في المستودع",
                    'url' => route('finished-goods.index'),
                    'subject' => $productionOrder,
                    'dedupe_key' => "FINISHED_GOODS_IDLE:{$productionOrderId}:{$today}",
                ]);
            });
    }

    private function scanReceivables(NotificationService $notifications, string $today): void
    {
        CustomerOrder::query()
            ->whereNotIn('status', ['DRAFT', 'CANCELLED'])
            ->whereNotNull('payment_due_date')
            ->whereDate('payment_due_date', '<', $today)
            ->withPaymentTotals()
            ->with('customer')
            ->get()
            ->filter(fn (CustomerOrder $order) => $order->outstanding_balance > 0)
            ->each(fn (CustomerOrder $order) => $notifications->raise(NotificationType::PaymentOverdue, [
                'title' => 'دفعة تجاوزت تاريخ استحقاقها',
                'body' => "طلب العميل {$order->order_number} — {$order->customer?->name} — المتبقي "
                    .number_format($order->outstanding_balance, 2).' ر.س',
                'url' => route('sales.orders.show', $order->id),
                'subject' => $order,
                'dedupe_key' => "PAYMENT_OVERDUE:{$order->id}:{$today}",
            ]));

        $credit = app(CustomerCreditService::class);
        Customer::query()
            ->where('is_active', true)
            ->where('is_credit_customer', true)
            ->with('creditProfile')
            ->get()
            ->each(function (Customer $customer) use ($credit, $notifications, $today) {
                $exposure = $credit->calculateExposure($customer);
                if ($exposure['status'] !== 'EXCEEDED') {
                    return;
                }

                $notifications->raise(NotificationType::CreditLimitExceeded, [
                    'title' => 'عميل تجاوز حد الائتمان',
                    'body' => "{$customer->name} — التعرض ".number_format($exposure['current_exposure'], 2)
                        .' ر.س والحد '.number_format($exposure['credit_limit'], 2).' ر.س',
                    'url' => route('receivables.customers.show', $customer->id),
                    'subject' => $customer,
                    'dedupe_key' => "CREDIT_LIMIT_EXCEEDED:{$customer->id}:{$today}",
                ]);
            });
    }
}
