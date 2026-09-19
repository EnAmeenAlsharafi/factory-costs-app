<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\CustomerPaymentEvent;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    public function createPayment(array $data, User $user): CustomerPayment
    {
        return DB::transaction(function () use ($data, $user) {
            $paymentNumber = CustomerPayment::generateNextNumber();

            $payment = CustomerPayment::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $data['customer_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'status' => $data['status'] ?? 'PENDING_CONFIRMATION',
                'received_by_user_id' => $data['received_by_user_id'] ?? null,
                'created_by_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            CustomerPaymentEvent::create([
                'customer_payment_id' => $payment->id,
                'event_type' => 'CREATED',
                'user_id' => $user->id,
                'notes' => 'تم إنشاء سجل الدفعة رقم '.$payment->payment_number,
                'payload' => [
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status,
                ],
            ]);

            return $payment;
        });
    }

    public function confirmPayment(CustomerPayment $payment, User $user): CustomerPayment
    {
        if ($payment->status === 'CONFIRMED') {
            return $payment;
        }

        if ($payment->status === 'REVERSED' || $payment->status === 'CANCELLED') {
            throw new Exception('لا يمكن تأكيد دفعة ملغاة أو معكوسة.');
        }

        return DB::transaction(function () use ($payment, $user) {
            $payment->update([
                'status' => 'CONFIRMED',
                'confirmed_by_user_id' => $user->id,
                'confirmed_at' => now(),
            ]);

            CustomerPaymentEvent::create([
                'customer_payment_id' => $payment->id,
                'event_type' => 'CONFIRMED',
                'user_id' => $user->id,
                'notes' => 'تم تأكيد الدفعة واعتمادها في حسابات التحصيل.',
            ]);

            return $payment;
        });
    }

    public function cancelPayment(CustomerPayment $payment, User $user, ?string $notes = null): CustomerPayment
    {
        if ($payment->status === 'CONFIRMED') {
            throw new Exception('لا يمكن إلغاء دفعة مؤكدة. يجب استخدام عكس الدفعة بدلاً من الإلغاء.');
        }

        if ($payment->status === 'REVERSED' || $payment->status === 'CANCELLED') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $user, $notes) {
            $payment->update([
                'status' => 'CANCELLED',
            ]);

            CustomerPaymentEvent::create([
                'customer_payment_id' => $payment->id,
                'event_type' => 'CANCELLED',
                'user_id' => $user->id,
                'notes' => $notes ?? 'تم إلغاء مسودة/طلب الدفعة.',
            ]);

            return $payment;
        });
    }

    public function reversePayment(CustomerPayment $payment, User $user, string $reason): CustomerPayment
    {
        if ($payment->status !== 'CONFIRMED') {
            throw new Exception('يمكن فقط عكس الدفعات المؤكدة.');
        }

        if (empty(trim($reason))) {
            throw new Exception('يجب تحديد سبب عكس الدفعة.');
        }

        return DB::transaction(function () use ($payment, $user, $reason) {
            $payment->update([
                'status' => 'REVERSED',
                'reversed_by_user_id' => $user->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            CustomerPaymentEvent::create([
                'customer_payment_id' => $payment->id,
                'event_type' => 'REVERSED',
                'user_id' => $user->id,
                'notes' => 'تم عكس الدفعة بسبب: '.$reason,
                'payload' => [
                    'reason' => $reason,
                    'reversed_by' => $user->id,
                ],
            ]);

            return $payment;
        });
    }
}
