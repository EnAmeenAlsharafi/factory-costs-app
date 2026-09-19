<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\CustomerPaymentEvent;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class PaymentAllocationService
{
    public function allocatePayment(
        CustomerPayment $payment,
        CustomerOrder $order,
        float $amount,
        ?string $type = 'PARTIAL_PAYMENT',
        ?User $user = null,
        ?string $notes = null
    ): CustomerPaymentAllocation {
        return DB::transaction(function () use ($payment, $order, $amount, $type, $user, $notes) {
            // Lock rows for transaction safety
            $paymentLocked = CustomerPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $orderLocked = CustomerOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($paymentLocked->customer_id !== $orderLocked->customer_id) {
                throw new Exception('لا يمكن تخصيص الدفعة لطلب ينتمي لعميل آخر.');
            }

            if ($paymentLocked->status !== 'CONFIRMED') {
                throw new Exception('يمكن فقط تخصيص الدفعات المؤكدة.');
            }

            if ($amount <= 0) {
                throw new Exception('يجب أن يكون مبلغ التخصيص أكبر من صفر.');
            }

            $unallocated = $paymentLocked->unallocated_amount;
            if (round($amount, 2) > round($unallocated, 2)) {
                throw new Exception(sprintf('مبلغ التخصيص المطلوب (%.2f) يتجاوز الرصيد المتاح في الدفعة (%.2f).', $amount, $unallocated));
            }

            $outstanding = $orderLocked->outstanding_balance;
            if (round($amount, 2) > round($outstanding, 2)) {
                throw new Exception(sprintf('مبلغ التخصيص المطلوب (%.2f) يتجاوز المتبقي على الطلب (%.2f).', $amount, $outstanding));
            }

            $userId = $user ? $user->id : (auth()->id() ?? $paymentLocked->created_by_user_id);

            $allocation = CustomerPaymentAllocation::create([
                'customer_payment_id' => $paymentLocked->id,
                'customer_order_id' => $orderLocked->id,
                'allocated_amount' => $amount,
                'allocation_type' => $type ?? 'PARTIAL_PAYMENT',
                'notes' => $notes,
                'created_by_user_id' => $userId,
            ]);

            CustomerPaymentEvent::create([
                'customer_payment_id' => $paymentLocked->id,
                'event_type' => 'ALLOCATED',
                'user_id' => $userId,
                'notes' => sprintf('تم تخصيص مبلغ %.2f ر.س على الطلب رقم %s', $amount, $orderLocked->order_number),
                'payload' => [
                    'order_id' => $orderLocked->id,
                    'order_number' => $orderLocked->order_number,
                    'allocated_amount' => $amount,
                ],
            ]);

            return $allocation;
        });
    }

    public function removeAllocation(CustomerPaymentAllocation $allocation, User $user): void
    {
        DB::transaction(function () use ($allocation, $user) {
            $payment = $allocation->payment;
            $orderNumber = $allocation->order ? $allocation->order->order_number : '#'.$allocation->customer_order_id;
            $amount = $allocation->allocated_amount;

            $allocation->delete();

            if ($payment) {
                CustomerPaymentEvent::create([
                    'customer_payment_id' => $payment->id,
                    'event_type' => 'REALLOCATED',
                    'user_id' => $user->id,
                    'notes' => sprintf('تم إلغاء تخصيص مبلغ %.2f ر.س عن الطلب %s', $amount, $orderNumber),
                ]);
            }
        });
    }

    public function handleOrderCancellation(CustomerOrder $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $allocations = $order->allocations()->get();

            foreach ($allocations as $allocation) {
                $payment = $allocation->payment;
                $amount = $allocation->allocated_amount;

                $allocation->delete();

                if ($payment) {
                    CustomerPaymentEvent::create([
                        'customer_payment_id' => $payment->id,
                        'event_type' => 'REALLOCATED',
                        'user_id' => $user->id,
                        'notes' => sprintf('تم تحرير تخصيص بقيمة %.2f ر.س لرصيد العميل غير المخصص بعد إلغاء الطلب %s', $amount, $order->order_number),
                    ]);
                }
            }
        });
    }

    public function handleOrderPriceReduction(CustomerOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $totalAmount = (float) $order->total_amount;
            $paidAmount = $order->confirmed_paid_amount;

            if ($paidAmount > $totalAmount) {
                $excess = round($paidAmount - $totalAmount, 2);
                $allocations = $order->allocations()
                    ->whereHas('payment', fn ($q) => $q->where('status', 'CONFIRMED'))
                    ->orderBy('id', 'desc')
                    ->get();

                foreach ($allocations as $allocation) {
                    if ($excess <= 0) {
                        break;
                    }

                    $currentAmount = (float) $allocation->allocated_amount;
                    if ($currentAmount <= $excess) {
                        $excess -= $currentAmount;
                        $allocation->delete();
                    } else {
                        $allocation->update([
                            'allocated_amount' => $currentAmount - $excess,
                        ]);
                        $excess = 0;
                    }
                }
            }
        });
    }
}
