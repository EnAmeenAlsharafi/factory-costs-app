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

    /**
     * Customer-facing fulfilment progress of an approved order.
     *
     * `state` is the machine key (presented via StatusPresenter's `portal_order` domain so the
     * label, tone and icon stay in one place); `status` keeps the plain Arabic label.
     *
     * @return array{state: string, status: string, ordered_quantity: float, delivered_quantity: float, returned_quantity: float, delivered_ratio: float}
     */
    public function progress(CustomerOrder $order): array
    {
        $order->loadMissing(['lines', 'deliveryOrders.lines', 'productionOrders', 'customerReturns']);
        $quantity = (float) $order->lines->whereIn('line_type', ['MANUFACTURED', 'READY_STOCK'])->sum('quantity');
        $delivered = (float) $order->deliveryOrders->whereIn('status', ['DELIVERED', 'INSTALLATION_COMPLETED'])->sum(fn ($delivery) => $delivery->lines->sum('quantity'));
        $returned = (float) $order->customerReturns->whereIn('status', ['RECEIVED', 'RESOLVED'])->sum('quantity');
        $netDelivered = max(0, $delivered - $returned);
        $state = match (true) {
            $order->status === 'CANCELLED' => 'CANCELLED',
            $quantity > 0 && $netDelivered >= $quantity => 'DELIVERED',
            $netDelivered > 0 => 'PARTIALLY_DELIVERED',
            $order->deliveryOrders->contains('status', 'OUT_FOR_DELIVERY') => 'OUT_FOR_DELIVERY',
            $order->deliveryOrders->whereIn('status', ['READY_FOR_DELIVERY', 'ASSIGNED'])->isNotEmpty() => 'READY_FOR_DELIVERY',
            $order->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty() => 'IN_PRODUCTION',
            $order->status === 'PENDING_PRODUCTION_REVIEW' => 'PENDING_PRODUCTION_REVIEW',
            $order->status === 'NEEDS_CHANGES' => 'NEEDS_CHANGES',
            $order->status === 'DRAFT' => 'DRAFT',
            default => 'APPROVED',
        };

        return [
            'state' => $state,
            'status' => StatusPresenter::label('portal_order', $state),
            'ordered_quantity' => $quantity,
            'delivered_quantity' => $netDelivered,
            'returned_quantity' => $returned,
            'delivered_ratio' => $quantity > 0 ? min(1.0, $netDelivered / $quantity) : 0.0,
        ];
    }

    protected function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
