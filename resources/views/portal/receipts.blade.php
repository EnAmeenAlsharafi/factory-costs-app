@extends('layouts.portal')
@section('title', 'سجل الاستلام')
@section('content')
<h1 class="h4 fw-bold mb-1">سجل الاستلام من المصنع</h1>
<p class="text-muted fs-7 mb-3">تظهر هنا دفعات التوصيل المسلّمة. قد يُسلّم الطلب الواحد على عدة دفعات؛ صافي المستلم بعد المرتجعات ظاهر في تفاصيل الطلب.</p>

{{-- Phone: one card per receipt. Tablet/desktop: the same rows as a table. --}}
<div class="task-list mobile-cards-only-lg">
    @forelse($receipts as $receipt)
        <article class="task-card tone-success">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h2 class="task-card-title">
                        <a class="text-reset text-decoration-none" href="{{ route('portal.orders.show', $receipt->customer_order_id) }}"><span class="ltr-isolate">{{ $receipt->customerOrder->order_number }}</span></a>
                    </h2>
                    <div class="task-card-ref">دفعة <span class="ltr-isolate">{{ $receipt->delivery_number }}</span></div>
                </div>
                <x-status-badge domain="delivery" :status="$receipt->status" />
            </div>
            <div class="task-card-meta">
                <span><i class="fas fa-layer-group" aria-hidden="true"></i> {{ number_format($receipt->lines->sum('quantity'), 0) }} قطعة</span>
                <span><i class="fas fa-circle-check" aria-hidden="true"></i> سُلّم <span class="ltr-isolate">{{ $receipt->delivered_at?->format('Y-m-d') ?? '—' }}</span></span>
                @if($receipt->installed_at)
                    <span><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i> رُكّب <span class="ltr-isolate">{{ $receipt->installed_at->format('Y-m-d') }}</span></span>
                @endif
            </div>
            <div class="task-card-actions">
                <a class="btn btn-outline-primary" href="{{ route('portal.orders.show', $receipt->customer_order_id) }}">عرض الطلب</a>
            </div>
        </article>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-box-open fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="mb-0">لا توجد دفعات مسلّمة حتى الآن.</p>
        </div>
    @endforelse
</div>

<div class="card has-mobile-cards-lg">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطلب</th><th>دفعة التوصيل</th><th>القطع المسلّمة</th><th>تاريخ التسليم</th><th>التركيب</th></tr></thead>
            <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td><a href="{{ route('portal.orders.show', $receipt->customer_order_id) }}"><span class="ltr-isolate">{{ $receipt->customerOrder->order_number }}</span></a></td>
                        <td><span class="ltr-isolate">{{ $receipt->delivery_number }}</span></td>
                        <td>{{ number_format($receipt->lines->sum('quantity'), 0) }}</td>
                        <td><span class="ltr-isolate">{{ $receipt->delivered_at?->format('Y-m-d') }}</span></td>
                        <td><span class="ltr-isolate">{{ $receipt->installed_at?->format('Y-m-d') ?: '—' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center p-4">لا توجد دفعات مسلّمة حتى الآن.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex justify-content-center">{{ $receipts->links() }}</div>
@endsection
