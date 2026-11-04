<?php

namespace App\Services\Sales;

/**
 * Decides whether a staged Salla order should be imported, skipped, or
 * imported with warnings (spec §6.2). Shared by the preview (read-only) and
 * confirm (write) services so both apply exactly the same rules.
 */
class SalesOrderClassifier
{
    private const SKIP_ORDER_STATUSES = ['ملغي', 'بإنتظار الدفع', 'مسترجع'];

    private const REFUNDED_PAYMENT_STATUSES = ['مسترد', 'refunded'];

    private const ALREADY_FULFILLED_STATUSES = ['تم التوصيل', 'تم الشحن'];

    private const PENDING_BANK_TRANSFER_STATUSES = ['بانتظار التحويل البنكي', 'قيد التحويل البنكي'];

    private const COD_PAYMENT_METHODS = ['الدفع عند الاستلام', 'cash on delivery', 'cod'];

    private const PARTIAL_REFUND_STATUSES = ['مسترد جزئياً', 'استرجاع جزئي'];

    /**
     * Shipping methods handled by Sadir's own internal delivery team.
     * Configurable here rather than scattered through the decision logic.
     */
    public const INTERNAL_SHIPPING_METHODS = ['سدير', 'مندوب سدير'];

    /**
     * @param  array<string, mixed>  $orderData  Parsed order-level fields (see SalesImportParserService::HEADER_MAP).
     * @param  array<int, string|null>  $lineTargetTypes  Resolved target_type (or null = unmapped) for every line.
     * @return array{decision: string, skip_reason: ?string, warnings: array<int, string>}
     */
    public function classify(array $orderData, array $lineTargetTypes): array
    {
        $orderStatus = trim((string) ($orderData['order_status'] ?? ''));
        $paymentStatus = trim((string) ($orderData['payment_status'] ?? ''));
        $paymentMethod = trim((string) ($orderData['payment_method'] ?? ''));
        $shippingMethod = trim((string) ($orderData['shipping_method'] ?? ''));

        if (in_array($orderStatus, self::SKIP_ORDER_STATUSES, true)) {
            return ['decision' => 'SKIP', 'skip_reason' => "حالة الطلب في سلة: {$orderStatus}", 'warnings' => []];
        }

        if (in_array(mb_strtolower($paymentStatus), array_map('mb_strtolower', self::REFUNDED_PAYMENT_STATUSES), true)) {
            return ['decision' => 'SKIP', 'skip_reason' => 'الدفعة مستردة بالكامل.', 'warnings' => []];
        }

        $isInternalShipping = in_array($shippingMethod, self::INTERNAL_SHIPPING_METHODS, true);
        $hasManufacturedOrBundle = ! empty(array_intersect($lineTargetTypes, ['MANUFACTURED', 'BUNDLE']));

        if (! $isInternalShipping && ! $hasManufacturedOrBundle) {
            return ['decision' => 'SKIP', 'skip_reason' => 'شحن خارجي ولا يحتوي على منتج مصنّع أو بكج — يُدار بالكامل داخل سلة.', 'warnings' => []];
        }

        $warnings = [];

        if (in_array($orderStatus, self::ALREADY_FULFILLED_STATUSES, true)) {
            $warnings[] = "الطلب مُسجَّل في سلة بالفعل كـ \"{$orderStatus}\".";
        }

        if (in_array($paymentStatus, self::PENDING_BANK_TRANSFER_STATUSES, true)) {
            $warnings[] = 'الدفع عبر تحويل بنكي لم يُؤكَّد بعد.';
        }

        if (in_array(mb_strtolower($paymentMethod), self::COD_PAYMENT_METHODS, true)) {
            $warnings[] = 'الدفع عند الاستلام — سيُحصَّل المبلغ عند التسليم.';
        }

        if (in_array($paymentStatus, self::PARTIAL_REFUND_STATUSES, true)) {
            $warnings[] = 'يوجد استرجاع جزئي مسجَّل على هذا الطلب في سلة.';
        }

        if (in_array(null, $lineTargetTypes, true)) {
            $warnings[] = 'يحتوي الطلب على عناصر غير مطابَقة (بحاجة لمطابقة يدوية).';
        }

        return ['decision' => 'IMPORT', 'skip_reason' => null, 'warnings' => $warnings];
    }
}
