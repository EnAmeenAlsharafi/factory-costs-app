@extends('layouts.portal')
@section('title', 'متابعة التنفيذ')
@section('content')
<h1 class="h4 fw-bold mb-1">متابعة التنفيذ</h1>
<p class="text-muted fs-7 mb-3">طلباتك المعتمدة ومراحل تنفيذها وما استلمته منها.</p>

<form method="GET" class="d-flex gap-2 mb-3" role="search">
    <label for="search" class="visually-hidden">رقم الطلب أو مرجع المعرض</label>
    <input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="رقم الطلب أو مرجعك" inputmode="search" enterkeyhint="search">
    <button class="btn btn-primary flex-shrink-0" aria-label="بحث"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></button>
</form>

@if(request()->filled('search'))
    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="status-chip status-chip-secondary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> {{ request('search') }}</span>
        <a href="{{ route('portal.orders.index') }}" class="link-secondary align-self-center fs-7">مسح البحث</a>
    </div>
@endif

{{-- One card per order on every screen size: one column on phones, two from tablet width up. --}}
<div class="task-list portal-card-list">
    @forelse($orders as $order)
        @php($presented = \App\Services\StatusPresenter::present('portal_order', $progress[$order->id]['state']))
        <article class="task-card tone-{{ $presented['tone'] }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h2 class="task-card-title">
                        <a class="text-reset text-decoration-none" href="{{ route('portal.orders.show', $order) }}"><span class="ltr-isolate">{{ $order->order_number }}</span></a>
                    </h2>
                    @if($order->customer_reference)
                        <div class="task-card-ref">مرجعك: {{ $order->customer_reference }}</div>
                    @endif
                </div>
                <x-status-badge domain="portal_order" :status="$progress[$order->id]['state']" />
            </div>
            @include('portal.line-cards', ['lines' => $lineCards[$order->id] ?? [], 'detailsUrl' => route('portal.orders.show', $order)])
            <div class="task-card-meta">
                <span><i class="far fa-calendar" aria-hidden="true"></i> <span class="ltr-isolate">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</span></span>
                <span><i class="fas fa-layer-group" aria-hidden="true"></i> مستلم {{ number_format($progress[$order->id]['delivered_quantity'], 0) }} من {{ number_format($progress[$order->id]['ordered_quantity'], 0) }}</span>
            </div>
            <div class="portal-meter" role="presentation"><span style="width: {{ round($progress[$order->id]['delivered_ratio'] * 100) }}%"></span></div>
            <div class="task-card-actions">
                <a class="btn btn-outline-primary" href="{{ route('portal.orders.show', $order) }}">عرض التفاصيل</a>
            </div>
        </article>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-truck-fast fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="mb-0">لا توجد طلبات مسجلة.</p>
        </div>
    @endforelse
</div>

<div class="mt-3 d-flex justify-content-center">{{ $orders->links() }}</div>
@endsection
