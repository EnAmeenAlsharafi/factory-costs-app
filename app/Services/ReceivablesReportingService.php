<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReceivablesReportingService
{
    public function getDashboardSummary(): array
    {
        $openOrders = CustomerOrder::whereNotIn('status', ['CANCELLED', 'REJECTED'])->get();

        $totalOutstanding = $openOrders->sum('outstanding_balance');
        $overdueOutstanding = $openOrders->filter(fn ($o) => $o->payment_status === 'OVERDUE')->sum('outstanding_balance');
        $depositPendingCount = $openOrders->filter(fn ($o) => $o->payment_status === 'DEPOSIT_PENDING')->count();

        $unconfirmedPayments = CustomerPayment::where('status', 'PENDING_CONFIRMATION')->get();
        $unconfirmedCount = $unconfirmedPayments->count();
        $unconfirmedAmount = (float) $unconfirmedPayments->sum('amount');

        $confirmedPayments = CustomerPayment::where('status', 'CONFIRMED')->get();
        $unallocatedAmount = (float) $confirmedPayments->sum('unallocated_amount');

        $creditCustomers = Customer::where('is_credit_customer', true)->get();
        $exceededCreditCount = $creditCustomers->filter(function ($c) {
            $limit = (float) ($c->creditProfile ? $c->creditProfile->credit_limit : $c->credit_limit);

            return $limit > 0 && $c->current_credit_exposure > $limit;
        })->count();

        $eligibilityService = app(OrderPaymentEligibilityService::class);
        $productionBlockedCount = $openOrders->filter(fn ($o) => ! $eligibilityService->checkProductionEligibility($o)['eligible'])->count();
        $deliveryBlockedCount = $openOrders->filter(fn ($o) => ! $eligibilityService->checkDeliveryEligibility($o)['eligible'])->count();

        return [
            'total_outstanding' => (float) $totalOutstanding,
            'overdue_outstanding' => (float) $overdueOutstanding,
            'unconfirmed_payments_count' => $unconfirmedCount,
            'unconfirmed_payments_amount' => $unconfirmedAmount,
            'unallocated_credit_amount' => $unallocatedAmount,
            'exceeded_credit_customers_count' => $exceededCreditCount,
            'deposit_pending_orders_count' => $depositPendingCount,
            'production_blocked_orders_count' => $productionBlockedCount,
            'delivery_blocked_orders_count' => $deliveryBlockedCount,
        ];
    }

    public function getCustomerBalances(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::with(['creditProfile', 'orders' => fn ($q) => $q->whereNotIn('status', ['CANCELLED', 'REJECTED']), 'payments'])
            ->active();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_credit_customer']) && $filters['is_credit_customer'] !== '') {
            $query->where('is_credit_customer', (bool) $filters['is_credit_customer']);
        }

        return $query->paginate($perPage)->through(function ($customer) {
            $orders = $customer->orders;
            $openCount = $orders->count();
            $totalCommercial = (float) $orders->sum('total_amount');
            $confirmedPaid = (float) $orders->sum('confirmed_paid_amount');
            $outstanding = (float) $orders->sum('outstanding_balance');
            $unallocated = (float) $customer->unallocated_credit;

            $overdue = (float) $orders->filter(fn ($o) => $o->payment_status === 'OVERDUE')->sum('outstanding_balance');

            $creditProfile = $customer->creditProfile;
            $creditLimit = (float) ($creditProfile ? $creditProfile->credit_limit : $customer->credit_limit);
            $availableCredit = max(0.00, $creditLimit - $outstanding);

            return [
                'customer' => $customer,
                'open_orders_count' => $openCount,
                'total_commercial_value' => $totalCommercial,
                'confirmed_paid' => $confirmedPaid,
                'unallocated_credit' => $unallocated,
                'outstanding_balance' => $outstanding,
                'overdue_balance' => $overdue,
                'credit_limit' => $creditLimit,
                'available_credit' => $availableCredit,
                'is_credit_enabled' => $creditProfile ? $creditProfile->credit_enabled : $customer->is_credit_customer,
            ];
        });
    }

    public function getCustomerStatement(Customer $customer): Collection
    {
        $events = collect();

        // 1. Orders
        $orders = CustomerOrder::where('customer_id', $customer->id)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->get();

        foreach ($orders as $order) {
            $events->push([
                'date' => $order->order_date ? $order->order_date->format('Y-m-d') : $order->created_at->format('Y-m-d'),
                'raw_date' => $order->order_date ? $order->order_date->timestamp : $order->created_at->timestamp,
                'reference' => $order->order_number,
                'type' => 'CUSTOMER_ORDER',
                'type_label' => 'طلب عميل',
                'description' => 'قيمة الطلب رقم '.$order->order_number,
                'debit' => (float) $order->total_amount,
                'credit' => 0.00,
                'order' => $order,
            ]);
        }

        // 2. Confirmed Payments
        $payments = CustomerPayment::where('customer_id', $customer->id)
            ->where('status', 'CONFIRMED')
            ->get();

        foreach ($payments as $payment) {
            $events->push([
                'date' => $payment->payment_date->format('Y-m-d'),
                'raw_date' => $payment->payment_date->timestamp,
                'reference' => $payment->payment_number,
                'type' => 'PAYMENT_RECEIVED',
                'type_label' => 'استلام دفعة',
                'description' => 'سداد دفعة (طريقة: '.$payment->payment_method.') مرجع: '.($payment->reference_number ?: '-'),
                'debit' => 0.00,
                'credit' => (float) $payment->amount,
                'payment' => $payment,
            ]);
        }

        // Sort chronologically
        $sorted = $events->sortBy('raw_date')->values();

        // Calculate running balance (Debit - Credit)
        $running = 0.00;

        return $sorted->map(function ($item) use (&$running) {
            $running += ($item['debit'] - $item['credit']);
            $item['running_balance'] = $running;

            return $item;
        });
    }

    public function getAgingReport(): Collection
    {
        $orders = CustomerOrder::with('customer')
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->get()
            ->filter(fn ($o) => $o->outstanding_balance > 0);

        $buckets = [
            'current' => ['label' => 'جاري (غير متأخر)', 'count' => 0, 'amount' => 0.00, 'orders' => collect()],
            '1_30' => ['label' => '1 - 30 يوم', 'count' => 0, 'amount' => 0.00, 'orders' => collect()],
            '31_60' => ['label' => '31 - 60 يوم', 'count' => 0, 'amount' => 0.00, 'orders' => collect()],
            '61_90' => ['label' => '61 - 90 يوم', 'count' => 0, 'amount' => 0.00, 'orders' => collect()],
            'over_90' => ['label' => 'أكثر من 90 يوم', 'count' => 0, 'amount' => 0.00, 'orders' => collect()],
        ];

        foreach ($orders as $order) {
            $outstanding = $order->outstanding_balance;
            $dueDate = $order->payment_due_date;

            if (! $dueDate || $dueDate->isFuture() || $dueDate->isToday()) {
                $buckets['current']['count']++;
                $buckets['current']['amount'] += $outstanding;
                $buckets['current']['orders']->push($order);

                continue;
            }

            $daysOverdue = (int) $dueDate->diffInDays(now());

            if ($daysOverdue <= 30) {
                $buckets['1_30']['count']++;
                $buckets['1_30']['amount'] += $outstanding;
                $buckets['1_30']['orders']->push($order);
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60']['count']++;
                $buckets['31_60']['amount'] += $outstanding;
                $buckets['31_60']['orders']->push($order);
            } elseif ($daysOverdue <= 90) {
                $buckets['61_90']['count']++;
                $buckets['61_90']['amount'] += $outstanding;
                $buckets['61_90']['orders']->push($order);
            } else {
                $buckets['over_90']['count']++;
                $buckets['over_90']['amount'] += $outstanding;
                $buckets['over_90']['orders']->push($order);
            }
        }

        return collect($buckets);
    }
}
