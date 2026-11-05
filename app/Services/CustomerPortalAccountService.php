<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use Illuminate\Support\Collection;

class CustomerPortalAccountService
{
    /** @return array{summary: array<string, int|float>, entries: Collection} */
    public function account(Customer $customer): array
    {
        $orders = $customer->orders()->whereNotIn('status', ['DRAFT', 'CANCELLED', 'REJECTED'])
            ->withSum(['allocations as confirmed_paid' => fn ($query) => $query->whereHas('payment', fn ($payment) => $payment->where('status', 'CONFIRMED'))], 'allocated_amount')->get();
        $payments = $customer->payments()->where('status', 'CONFIRMED')->get();
        $entries = collect();
        $opening = $this->cents($customer->opening_balance);
        if ($opening !== 0) {
            $entries->push(['date' => null, 'sort' => '', 'reference' => 'رصيد افتتاحي', 'debit' => max(0, $opening) / 100, 'credit' => max(0, -$opening) / 100]);
        }
        $commercial = 0;
        $overdue = 0;
        foreach ($orders as $order) {
            $amount = $this->cents($order->total_amount);
            $commercial += $amount;
            if ($order->payment_due_date?->startOfDay()->lt(today())) {
                $overdue += max(0, $amount - $this->cents($order->confirmed_paid));
            }
            $entries->push(['date' => $order->order_date?->toDateString(), 'sort' => $order->order_date?->toDateString().'-O-'.str_pad((string) $order->id, 12, '0', STR_PAD_LEFT), 'reference' => 'طلب '.$order->order_number, 'debit' => $amount / 100, 'credit' => 0]);
        }
        $paid = 0;
        foreach ($payments as $payment) {
            $amount = $this->cents($payment->amount);
            $paid += $amount;
            $entries->push(['date' => $payment->payment_date?->toDateString(), 'sort' => $payment->payment_date?->toDateString().'-P-'.str_pad((string) $payment->id, 12, '0', STR_PAD_LEFT), 'reference' => 'دفعة '.$payment->payment_number, 'debit' => 0, 'credit' => $amount / 100]);
        }
        $balance = 0;
        $entries = $entries->sortBy('sort')->values()->map(function (array $entry) use (&$balance): array {
            $balance += $this->cents($entry['debit']) - $this->cents($entry['credit']);
            $entry['balance'] = $balance / 100;

            return $entry;
        });
        $net = $opening + $commercial - $paid;

        return ['summary' => ['opening_balance' => $opening / 100, 'orders_count' => $orders->count(), 'commercial_total' => $commercial / 100, 'confirmed_paid' => $paid / 100, 'balance' => $net / 100, 'outstanding' => max(0, $net) / 100, 'credit' => max(0, -$net) / 100, 'overdue' => min(max(0, $net), $overdue) / 100], 'entries' => $entries];
    }

    /** @return array<string, int|float|string> */
    public function progress(CustomerOrder $order): array
    {
        $order->loadMissing(['lines', 'deliveryOrders.lines', 'productionOrders', 'customerReturns']);
        $quantity = (float) $order->lines->whereIn('line_type', ['MANUFACTURED', 'READY_STOCK'])->sum('quantity');
        $delivered = (float) $order->deliveryOrders->whereIn('status', ['DELIVERED', 'INSTALLATION_COMPLETED'])->sum(fn ($delivery) => $delivery->lines->sum('quantity'));
        $returned = (float) $order->customerReturns->whereIn('status', ['RECEIVED', 'RESOLVED'])->sum('quantity');
        $netDelivered = max(0, $delivered - $returned);
        $status = match (true) {
            $order->status === 'CANCELLED' => 'ملغى',
            $quantity > 0 && $netDelivered >= $quantity => 'تم التسليم',
            $netDelivered > 0 => 'تم التسليم جزئيًا',
            $order->deliveryOrders->contains('status', 'OUT_FOR_DELIVERY') => 'خرج للتوصيل',
            $order->deliveryOrders->whereIn('status', ['READY_FOR_DELIVERY', 'ASSIGNED'])->isNotEmpty() => 'جاهز للتوصيل',
            $order->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty() => 'قيد التصنيع والتجهيز',
            $order->status === 'PENDING_PRODUCTION_REVIEW' => 'بانتظار اعتماد الإنتاج',
            $order->status === 'NEEDS_CHANGES' => 'قيد مراجعة المواصفات',
            $order->status === 'DRAFT' => 'مسودة لدى المصنع',
            default => 'معتمد وبانتظار التجهيز',
        };

        return ['status' => $status, 'ordered_quantity' => $quantity, 'delivered_quantity' => $netDelivered, 'returned_quantity' => $returned];
    }

    protected function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
