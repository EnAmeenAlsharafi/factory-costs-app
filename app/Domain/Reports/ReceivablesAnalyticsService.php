<?php

namespace App\Domain\Reports;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CustomerCreditService;
use App\Services\ReceivablesReportingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Management view of receivables. All balances, aging and credit figures come from the Stage 13 services
 * (ReceivablesReportingService / CustomerCreditService); nothing here recalculates receivables differently.
 * No accounting provisions are made.
 */
class ReceivablesAnalyticsService
{
    public const BUCKET_ORDER = ['current', '1_30', '31_60', '61_90', 'over_90'];

    public function __construct(
        protected ReceivablesReportingService $receivables,
        protected CustomerCreditService $credit,
    ) {}

    /**
     * @return Collection<string, array{label: string, count: int, amount: float}>
     */
    public function agingBuckets(): Collection
    {
        return $this->receivables->getAgingReport()->map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'count' => (int) $bucket['count'],
            'amount' => round((float) $bucket['amount'], 2),
            'customer_ids' => $bucket['orders']->pluck('customer_id')->unique()->values()->all(),
        ]);
    }

    /**
     * Customer balances (authoritative Stage 13 calculation, paginated) with worst aging bucket and credit status.
     *
     * @param  array<string, mixed>  $filters
     */
    public function customerBalances(array $filters = []): LengthAwarePaginator
    {
        $buckets = $this->agingBuckets();
        $worstBucket = [];
        foreach (self::BUCKET_ORDER as $key) {
            foreach ($buckets[$key]['customer_ids'] ?? [] as $customerId) {
                $worstBucket[$customerId] = $buckets[$key]['label'];
            }
        }

        return $this->receivables->getCustomerBalances($filters, 25)->through(function (array $row) use ($worstBucket) {
            /** @var Customer $customer */
            $customer = $row['customer'];
            // CustomerCreditService creates a missing profile on read; reports must not write, so non-credit
            // customers without a profile are evaluated read-only as "credit disabled".
            $exposure = $customer->creditProfile || $customer->is_credit_customer
                ? $this->credit->calculateExposure($customer)
                : ['status' => 'DISABLED', 'hold_when_exceeded' => false, 'current_exposure' => (float) $customer->current_credit_exposure, 'available_credit' => 0.0];
            $onHold = $exposure['status'] === 'EXCEEDED' && $exposure['hold_when_exceeded'];

            return [
                'customer' => ['text' => $customer->name, 'url' => route('receivables.customers.show', $customer)],
                'code' => $customer->customer_code,
                'outstanding' => $row['outstanding_balance'],
                'overdue' => $row['overdue_balance'],
                'aging_bucket' => $worstBucket[$customer->id] ?? null,
                'credit_limit' => $row['credit_limit'] > 0 ? $row['credit_limit'] : null,
                'exposure' => (float) $exposure['current_exposure'],
                'available_credit' => $row['credit_limit'] > 0 ? (float) $exposure['available_credit'] : null,
                'unallocated' => $row['unallocated_credit'] > 0 ? $row['unallocated_credit'] : null,
                'credit_status' => $onHold
                    ? ['label' => 'موقوف ائتمانياً', 'tone' => 'danger']
                    : match ($exposure['status']) {
                        'EXCEEDED' => ['label' => 'تجاوز الحد', 'tone' => 'warning'],
                        'WARNING' => ['label' => 'قريب من الحد', 'tone' => 'warning'],
                        'DISABLED' => ['label' => 'بدون ائتمان', 'tone' => 'secondary'],
                        default => ['label' => 'ضمن الحد', 'tone' => 'success'],
                    },
            ];
        });
    }

    /**
     * Customer collection activity (payment date basis). Not a cash-flow statement.
     *
     * @param  array<string, mixed>  $filters
     */
    public function collections(ReportPeriod $period, array $filters = []): ReportDataset
    {
        $query = CustomerPayment::query()
            ->with('customer')
            ->whereBetween('payment_date', $period->dateRange())
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['payment_method']), fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->orderByDesc('payment_date')->orderByDesc('id');

        $totals = DB::table('customer_payments')
            ->whereBetween('payment_date', $period->dateRange())
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'CONFIRMED' THEN amount ELSE 0 END), 0) AS confirmed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'PENDING_CONFIRMATION' THEN amount ELSE 0 END), 0) AS pending")
            ->selectRaw("SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING_CONFIRMATION' THEN 1 ELSE 0 END) AS pending_count")
            ->first();
        $byMethod = DB::table('customer_payments')
            ->whereBetween('payment_date', $period->dateRange())
            ->where('status', 'CONFIRMED')
            ->groupBy('payment_method')->selectRaw('payment_method, SUM(amount) AS amount, COUNT(*) AS n')->get();

        $statusLabels = [
            'CONFIRMED' => ['label' => 'مؤكدة', 'tone' => 'success'],
            'PENDING_CONFIRMATION' => ['label' => 'بانتظار التأكيد', 'tone' => 'warning'],
            'REVERSED' => ['label' => 'معكوسة', 'tone' => 'danger'],
            'CANCELLED' => ['label' => 'ملغاة', 'tone' => 'secondary'],
        ];

        return new ReportDataset($query->toBase(), function (object $row) use ($statusLabels) {
            return [
                'id' => $row->id,
                'payment' => ['text' => $row->payment_number, 'url' => route('receivables.payments.show', $row->id)],
                'payment_date' => $row->payment_date,
                'customer_id' => $row->customer_id,
                'customer' => null,
                'amount' => (float) $row->amount,
                'method' => $row->payment_method,
                'status' => $statusLabels[$row->status] ?? ['label' => $row->status, 'tone' => 'secondary'],
                'unallocated' => null,
            ];
        }, function (Collection $rows) {
            $payments = CustomerPayment::with('customer')->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

            return $rows->map(function (array $row) use ($payments) {
                $payment = $payments->get($row['id']);
                $row['customer'] = $payment?->customer?->name;
                $row['unallocated'] = $payment && $payment->status === 'CONFIRMED' ? (float) $payment->unallocated_amount : null;

                return $row;
            });
        }, [
            'confirmed' => (float) $totals->confirmed,
            'pending' => (float) $totals->pending,
            'confirmed_count' => (int) $totals->confirmed_count,
            'pending_count' => (int) $totals->pending_count,
            'by_method' => $byMethod,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->receivables->getDashboardSummary();
    }
}
