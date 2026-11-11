<?php

namespace App\Notifications;

/**
 * The catalogue of operational notifications: what the factory is told, and who is told by default.
 *
 * Each type declares its audiences as keys the AudienceResolver understands:
 *   user              — the people the caller passes in (an assignee, an order's creator)
 *   department        — every active member of the department the notification concerns
 *   permission:<name> — every active user whose role holds that permission
 *   role:<name>       — every active user with that role
 *
 * Permission audiences are preferred over role audiences: a new role that is granted the permission
 * starts receiving the notification without a code change.
 */
enum NotificationType: string
{
    // Sales and customer orders
    case OrderSubmittedForReview = 'ORDER_SUBMITTED_FOR_REVIEW';
    case OrderApprovedForProduction = 'ORDER_APPROVED_FOR_PRODUCTION';
    case OrderChangedAfterApproval = 'ORDER_CHANGED_AFTER_APPROVAL';
    case OrderCancelled = 'ORDER_CANCELLED';
    case PortalRequestSubmitted = 'PORTAL_REQUEST_SUBMITTED';
    case PortalOfferConfirmed = 'PORTAL_OFFER_CONFIRMED';
    case PortalChangesRequested = 'PORTAL_CHANGES_REQUESTED';

    // Production
    case ProductionOrderReleased = 'PRODUCTION_ORDER_RELEASED';
    case StageReady = 'STAGE_READY';
    case TaskAssigned = 'TASK_ASSIGNED';
    case TaskReassigned = 'TASK_REASSIGNED';
    case StageBlocked = 'STAGE_BLOCKED';
    case StageBlockResolved = 'STAGE_BLOCK_RESOLVED';
    case StageDueSoon = 'STAGE_DUE_SOON';
    case StageOverdue = 'STAGE_OVERDUE';
    case ProductionOrderCompleted = 'PRODUCTION_ORDER_COMPLETED';
    case ProductionOrderHeld = 'PRODUCTION_ORDER_HELD';
    case ProductionOrderCancelled = 'PRODUCTION_ORDER_CANCELLED';

    // Quality, rework and waste
    case QualityIncidentOpened = 'QUALITY_INCIDENT_OPENED';
    case ReworkCreated = 'REWORK_CREATED';
    case ReworkCompleted = 'REWORK_COMPLETED';
    case WastePendingApproval = 'WASTE_PENDING_APPROVAL';

    // Inventory and materials
    case MaterialRequestSubmitted = 'MATERIAL_REQUEST_SUBMITTED';
    case MaterialRequestFulfilled = 'MATERIAL_REQUEST_FULFILLED';
    case MaterialRequestPartiallyFulfilled = 'MATERIAL_REQUEST_PARTIALLY_FULFILLED';
    case MaterialBelowMinimum = 'MATERIAL_BELOW_MINIMUM';

    // Purchasing
    case PurchaseRequestPendingReview = 'PURCHASE_REQUEST_PENDING_REVIEW';
    case PurchaseOrderApproved = 'PURCHASE_ORDER_APPROVED';
    case PurchaseReceivedPartially = 'PURCHASE_RECEIVED_PARTIALLY';
    case PurchaseOrderLate = 'PURCHASE_ORDER_LATE';

    // Finished goods, delivery and installation
    case OrderReadyForDelivery = 'ORDER_READY_FOR_DELIVERY';
    case DeliveryAssigned = 'DELIVERY_ASSIGNED';
    case DeliveryToday = 'DELIVERY_TODAY';
    case DeliveryFailed = 'DELIVERY_FAILED';
    case GoodsWithDriverNotReturned = 'GOODS_WITH_DRIVER_NOT_RETURNED';
    case DeliveryCompleted = 'DELIVERY_COMPLETED';
    case InstallationCompleted = 'INSTALLATION_COMPLETED';
    case FinishedGoodsIdle = 'FINISHED_GOODS_IDLE';
    case CustomerReturnOpened = 'CUSTOMER_RETURN_OPENED';

    // Receivables
    case PaymentPendingConfirmation = 'PAYMENT_PENDING_CONFIRMATION';
    case PaymentConfirmed = 'PAYMENT_CONFIRMED';
    case OrderBlockedByPayment = 'ORDER_BLOCKED_BY_PAYMENT';
    case PaymentOverdue = 'PAYMENT_OVERDUE';
    case CreditLimitExceeded = 'CREDIT_LIMIT_EXCEEDED';
    case PaymentControlOverridden = 'PAYMENT_CONTROL_OVERRIDDEN';

    // Management announcement
    case Announcement = 'ANNOUNCEMENT';

    /**
     * Raised by the scheduled scan rather than by someone's action.
     */
    public function isAutomatic(): bool
    {
        return in_array($this, [
            self::StageDueSoon, self::StageOverdue, self::MaterialBelowMinimum, self::PurchaseOrderLate,
            self::DeliveryToday, self::GoodsWithDriverNotReturned, self::FinishedGoodsIdle,
            self::PaymentOverdue, self::CreditLimitExceeded,
        ], true);
    }

    public function source(): string
    {
        return match (true) {
            $this === self::Announcement => 'MANUAL',
            $this->isAutomatic() => 'SYSTEM',
            default => 'ACTION',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::StageBlocked, self::StageOverdue, self::QualityIncidentOpened, self::DeliveryFailed,
            self::GoodsWithDriverNotReturned, self::PaymentOverdue, self::CreditLimitExceeded => 'danger',

            self::OrderChangedAfterApproval, self::OrderCancelled, self::PortalChangesRequested,
            self::StageDueSoon, self::ProductionOrderHeld, self::ProductionOrderCancelled,
            self::ReworkCreated, self::WastePendingApproval, self::MaterialRequestPartiallyFulfilled,
            self::MaterialBelowMinimum, self::PurchaseOrderLate, self::FinishedGoodsIdle,
            self::CustomerReturnOpened, self::OrderBlockedByPayment, self::PaymentControlOverridden => 'warning',

            self::OrderApprovedForProduction, self::PortalOfferConfirmed, self::StageBlockResolved,
            self::ProductionOrderCompleted, self::ReworkCompleted, self::MaterialRequestFulfilled,
            self::PurchaseOrderApproved, self::OrderReadyForDelivery, self::DeliveryCompleted,
            self::InstallationCompleted, self::PaymentConfirmed => 'success',

            default => 'info',
        };
    }

    /**
     * Default audiences for the type. The settings matrix may switch any of them off.
     *
     * @return list<string>
     */
    public function audiences(): array
    {
        return match ($this) {
            self::OrderSubmittedForReview => ['permission:orders.approve_production'],
            self::OrderApprovedForProduction => ['user'],
            self::OrderChangedAfterApproval => ['permission:orders.approve_production'],
            self::OrderCancelled => ['permission:orders.approve_production', 'permission:receivables.view'],
            self::PortalRequestSubmitted, self::PortalOfferConfirmed, self::PortalChangesRequested => ['permission:orders.create'],

            self::ProductionOrderReleased, self::StageReady => ['department'],
            self::TaskAssigned, self::TaskReassigned => ['user'],
            self::StageBlocked => ['department', 'permission:production.release'],
            self::StageBlockResolved => ['user', 'department'],
            self::StageDueSoon => ['department'],
            self::StageOverdue => ['department', 'permission:production.release'],
            self::ProductionOrderCompleted => ['permission:production.release', 'permission:finished_goods.receive'],
            self::ProductionOrderHeld, self::ProductionOrderCancelled => ['department', 'permission:production.release'],

            self::QualityIncidentOpened => ['department', 'permission:production.manage_quality'],
            self::ReworkCreated => ['department'],
            self::ReworkCompleted => ['permission:production.manage_quality'],
            self::WastePendingApproval => ['permission:production.approve_waste'],

            self::MaterialRequestSubmitted => ['permission:inventory.issue'],
            self::MaterialRequestFulfilled, self::MaterialRequestPartiallyFulfilled => ['user', 'department'],
            self::MaterialBelowMinimum => ['permission:purchasing.request'],

            self::PurchaseRequestPendingReview => ['permission:purchasing.review_request'],
            self::PurchaseOrderApproved, self::PurchaseReceivedPartially, self::PurchaseOrderLate => ['permission:purchasing.view'],

            self::OrderReadyForDelivery => ['permission:delivery.create'],
            self::DeliveryAssigned, self::DeliveryToday => ['user'],
            self::DeliveryFailed => ['permission:delivery.create', 'permission:orders.view'],
            self::GoodsWithDriverNotReturned => ['permission:delivery.create', 'permission:finished_goods.view'],
            self::DeliveryCompleted, self::InstallationCompleted => ['permission:orders.view'],
            self::FinishedGoodsIdle => ['permission:delivery.create'],
            self::CustomerReturnOpened => ['permission:delivery.manage_returns', 'permission:production.manage_quality'],

            self::PaymentPendingConfirmation => ['permission:receivables.payment.confirm'],
            self::PaymentConfirmed => ['user'],
            self::OrderBlockedByPayment => ['permission:receivables.view', 'permission:production.release'],
            self::PaymentOverdue, self::CreditLimitExceeded => ['permission:receivables.view'],
            self::PaymentControlOverridden => ['permission:receivables.view', 'role:admin'],

            self::Announcement => ['user', 'department'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OrderSubmittedForReview => 'طلب أُرسل لمراجعة الإنتاج',
            self::OrderApprovedForProduction => 'طلب اعتُمد للتصنيع',
            self::OrderChangedAfterApproval => 'تعديل طلب بعد الاعتماد',
            self::OrderCancelled => 'إلغاء طلب عميل',
            self::PortalRequestSubmitted => 'طلب جديد من بوابة العملاء',
            self::PortalOfferConfirmed => 'العميل أكّد العرض',
            self::PortalChangesRequested => 'العميل طلب تعديلاً على العرض',

            self::ProductionOrderReleased => 'إطلاق أمر إنتاج للورشة',
            self::StageReady => 'مرحلة جاهزة في القسم',
            self::TaskAssigned => 'إسناد مهمة لموظف',
            self::TaskReassigned => 'إعادة إسناد مهمة',
            self::StageBlocked => 'إيقاف مرحلة بعائق',
            self::StageBlockResolved => 'حل عائق مرحلة',
            self::StageDueSoon => 'اقتراب موعد المرحلة',
            self::StageOverdue => 'تأخر مرحلة عن موعد القسم',
            self::ProductionOrderCompleted => 'اكتمال أمر إنتاج',
            self::ProductionOrderHeld => 'تعليق أمر إنتاج',
            self::ProductionOrderCancelled => 'إلغاء أمر إنتاج',

            self::QualityIncidentOpened => 'بلاغ جودة جديد',
            self::ReworkCreated => 'إعادة عمل مطلوبة',
            self::ReworkCompleted => 'اكتمال إعادة العمل',
            self::WastePendingApproval => 'هدر بانتظار الاعتماد',

            self::MaterialRequestSubmitted => 'طلب خامات بانتظار الصرف',
            self::MaterialRequestFulfilled => 'صرف طلب الخامات كاملاً',
            self::MaterialRequestPartiallyFulfilled => 'صرف طلب الخامات جزئياً',
            self::MaterialBelowMinimum => 'خامة تحت الحد الأدنى',

            self::PurchaseRequestPendingReview => 'طلب شراء بانتظار المراجعة',
            self::PurchaseOrderApproved => 'اعتماد أمر شراء',
            self::PurchaseReceivedPartially => 'استلام جزئي لأمر شراء',
            self::PurchaseOrderLate => 'تأخر أمر شراء عن موعده',

            self::OrderReadyForDelivery => 'الطلب جاهز للتوصيل',
            self::DeliveryAssigned => 'إسناد أمر توصيل',
            self::DeliveryToday => 'توصيلات اليوم',
            self::DeliveryFailed => 'تعثر أو تأجيل توصيل',
            self::GoodsWithDriverNotReturned => 'بضاعة لم تُعد للمصنع',
            self::DeliveryCompleted => 'تم التسليم للعميل',
            self::InstallationCompleted => 'اكتمال التركيب',
            self::FinishedGoodsIdle => 'منتجات جاهزة راكدة',
            self::CustomerReturnOpened => 'مرتجع عميل جديد',

            self::PaymentPendingConfirmation => 'دفعة بانتظار التأكيد',
            self::PaymentConfirmed => 'تأكيد دفعة',
            self::OrderBlockedByPayment => 'طلب موقوف مالياً',
            self::PaymentOverdue => 'دفعة تجاوزت استحقاقها',
            self::CreditLimitExceeded => 'تجاوز حد ائتمان عميل',
            self::PaymentControlOverridden => 'استثناء إداري على قيد السداد',

            self::Announcement => 'إعلان من الإدارة',
        };
    }

    /**
     * Section of the settings matrix the type belongs to.
     */
    public function group(): string
    {
        return match ($this) {
            self::OrderSubmittedForReview, self::OrderApprovedForProduction, self::OrderChangedAfterApproval,
            self::OrderCancelled, self::PortalRequestSubmitted, self::PortalOfferConfirmed,
            self::PortalChangesRequested => 'المبيعات والطلبات',

            self::ProductionOrderReleased, self::StageReady, self::TaskAssigned, self::TaskReassigned,
            self::StageBlocked, self::StageBlockResolved, self::StageDueSoon, self::StageOverdue,
            self::ProductionOrderCompleted, self::ProductionOrderHeld, self::ProductionOrderCancelled => 'الإنتاج',

            self::QualityIncidentOpened, self::ReworkCreated, self::ReworkCompleted,
            self::WastePendingApproval => 'الجودة وإعادة العمل',

            self::MaterialRequestSubmitted, self::MaterialRequestFulfilled,
            self::MaterialRequestPartiallyFulfilled, self::MaterialBelowMinimum => 'المخزون والخامات',

            self::PurchaseRequestPendingReview, self::PurchaseOrderApproved,
            self::PurchaseReceivedPartially, self::PurchaseOrderLate => 'المشتريات',

            self::OrderReadyForDelivery, self::DeliveryAssigned, self::DeliveryToday, self::DeliveryFailed,
            self::GoodsWithDriverNotReturned, self::DeliveryCompleted, self::InstallationCompleted,
            self::FinishedGoodsIdle, self::CustomerReturnOpened => 'المنتجات الجاهزة والتوصيل',

            self::PaymentPendingConfirmation, self::PaymentConfirmed, self::OrderBlockedByPayment,
            self::PaymentOverdue, self::CreditLimitExceeded, self::PaymentControlOverridden => 'التحصيل',

            self::Announcement => 'إعلانات',
        };
    }

    public function icon(): string
    {
        return match ($this->group()) {
            'المبيعات والطلبات' => 'fa-file-invoice',
            'الإنتاج' => 'fa-industry',
            'الجودة وإعادة العمل' => 'fa-triangle-exclamation',
            'المخزون والخامات' => 'fa-boxes-stacked',
            'المشتريات' => 'fa-cart-shopping',
            'المنتجات الجاهزة والتوصيل' => 'fa-truck-fast',
            'التحصيل' => 'fa-money-bill-wave',
            default => 'fa-bullhorn',
        };
    }
}
