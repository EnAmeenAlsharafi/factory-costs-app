<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\PaymentControlOverride;

class OrderPaymentEligibilityService
{
    public function __construct(
        protected CustomerCreditService $creditService
    ) {}

    public function checkProductionEligibility(CustomerOrder $order): array
    {
        $override = PaymentControlOverride::where('customer_order_id', $order->id)
            ->where('override_stage', 'PRODUCTION_RELEASE')
            ->latest()
            ->first();

        if ($override) {
            return [
                'eligible' => true,
                'status' => 'OVERRIDDEN',
                'reason' => 'تم السماح بالإنتاج بموجب استثناء إداري معتمد: '.$override->reason,
                'override' => $override,
                'required_amount' => $order->required_deposit_amount,
                'paid_amount' => $order->confirmed_paid_amount,
                'outstanding' => $order->outstanding_balance,
            ];
        }

        $terms = $order->payment_terms_type ?? 'FULL_BEFORE_PRODUCTION';
        $paid = $order->confirmed_paid_amount;
        $total = (float) $order->total_amount;
        $outstanding = $order->outstanding_balance;
        $requiredDeposit = $order->required_deposit_amount;

        if ($terms === 'FULL_BEFORE_PRODUCTION') {
            if ($outstanding > 0) {
                return [
                    'eligible' => false,
                    'status' => 'PAYMENT_REQUIRED',
                    'reason' => sprintf('شرط السداد: سداد كامل القيمة قبل الإنتاج. تم دفع %.2f ر.س من إجمالي %.2f ر.س.', $paid, $total),
                    'required_amount' => $total,
                    'paid_amount' => $paid,
                    'outstanding' => $outstanding,
                ];
            }
        } elseif ($terms === 'DEPOSIT_AND_BALANCE') {
            if (! $order->is_deposit_satisfied) {
                return [
                    'eligible' => false,
                    'status' => 'DEPOSIT_PENDING',
                    'reason' => sprintf('شرط السداد: عربون قبل الإنتاج. تم دفع %.2f ر.س والمطلوب للعربون %.2f ر.س.', $paid, $requiredDeposit),
                    'required_amount' => $requiredDeposit,
                    'paid_amount' => $paid,
                    'outstanding' => $outstanding,
                ];
            }
        } elseif ($terms === 'CREDIT') {
            $creditCheck = $this->creditService->checkOrderCreditEligibility($order);
            if (! $creditCheck['eligible']) {
                return [
                    'eligible' => false,
                    'status' => 'CREDIT_HOLD',
                    'reason' => 'موقوف بسبب الائتمان: '.$creditCheck['reason'],
                    'required_amount' => 0.00,
                    'paid_amount' => $paid,
                    'outstanding' => $outstanding,
                    'credit_check' => $creditCheck,
                ];
            }
        } elseif ($terms === 'CASH_ON_DELIVERY') {
            return [
                'eligible' => true,
                'status' => 'ALLOWED',
                'reason' => 'الدفع عند الاستلام (COD) - مسموح ببدء الإنتاج.',
                'required_amount' => 0.00,
                'paid_amount' => $paid,
                'outstanding' => $outstanding,
            ];
        }

        return [
            'eligible' => true,
            'status' => 'ALLOWED',
            'reason' => 'مستوفي لشروط السداد للإنتاج.',
            'required_amount' => $requiredDeposit,
            'paid_amount' => $paid,
            'outstanding' => $outstanding,
        ];
    }

    public function checkDeliveryEligibility(CustomerOrder $order): array
    {
        $override = PaymentControlOverride::where('customer_order_id', $order->id)
            ->where('override_stage', 'DELIVERY_DISPATCH')
            ->latest()
            ->first();

        if ($override) {
            return [
                'eligible' => true,
                'status' => 'OVERRIDDEN',
                'reason' => 'تم السماح بالتسليم بموجب استثناء إداري معتمد: '.$override->reason,
                'override' => $override,
                'paid_amount' => $order->confirmed_paid_amount,
                'outstanding' => $order->outstanding_balance,
            ];
        }

        $terms = $order->payment_terms_type ?? 'FULL_BEFORE_PRODUCTION';
        $paid = $order->confirmed_paid_amount;
        $outstanding = $order->outstanding_balance;

        if ($terms === 'CREDIT') {
            $creditCheck = $this->creditService->checkOrderCreditEligibility($order);
            if (! $creditCheck['eligible']) {
                return [
                    'eligible' => false,
                    'status' => 'CREDIT_HOLD',
                    'reason' => 'تسليم آجل موقوف بسبب الائتمان: '.$creditCheck['reason'],
                    'paid_amount' => $paid,
                    'outstanding' => $outstanding,
                    'credit_check' => $creditCheck,
                ];
            }

            return [
                'eligible' => true,
                'status' => 'ALLOWED',
                'reason' => 'عميل آجل - مسموح بالتسليم ضمن حد الائتمان.',
                'paid_amount' => $paid,
                'outstanding' => $outstanding,
            ];
        }

        if ($terms === 'CASH_ON_DELIVERY') {
            return [
                'eligible' => true,
                'status' => 'ALLOWED_COD',
                'reason' => sprintf('مسموح بالتسليم مع تحصيل المبلغ عند الاستلام (COD): %.2f ر.س.', $outstanding),
                'paid_amount' => $paid,
                'outstanding' => $outstanding,
            ];
        }

        // FULL_BEFORE_PRODUCTION or DEPOSIT_AND_BALANCE requiring balance before delivery
        if ($outstanding > 0) {
            return [
                'eligible' => false,
                'status' => 'PAYMENT_REQUIRED',
                'reason' => sprintf('يتطلب سداد كامل المتبقي (%.2f ر.س) قبل تسليم الطلب.', $outstanding),
                'paid_amount' => $paid,
                'outstanding' => $outstanding,
            ];
        }

        return [
            'eligible' => true,
            'status' => 'ALLOWED',
            'reason' => 'مستوفي لشرط السداد الكامل والتسليم مسموح.',
            'paid_amount' => $paid,
            'outstanding' => $outstanding,
        ];
    }
}
