@extends('layouts.app')

@section('title', 'مهامي - التوصيل والتركيب')
@section('page-title', 'مهامي')

@section('content')
@php
    $emptyMessages = [
        'all' => 'لا توجد مهام توصيل مسندة إليك حالياً. ستظهر هنا فور إسناد أمر توصيل لك.',
        'today' => 'لا توجد توصيلات مجدولة لك اليوم.',
        'assigned' => 'لا توجد مهام جاهزة للانطلاق حالياً.',
        'out' => 'لا توجد شحنات خارجة للتوصيل معك الآن.',
        'installation' => 'لا توجد طلبات بانتظار التركيب.',
        'exceptions' => 'لا توجد توصيلات متعذرة أو مؤجلة — ممتاز.',
    ];
@endphp
<div class="d-flex flex-column gap-3 mx-auto" style="max-width: 760px;">
    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-truck-fast text-warning fs-5" aria-hidden="true"></i>
            <span>مهامي — التوصيل والتركيب</span>
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">اضغط على المهمة لعرض العميل والعنوان والمنتجات، أو نفّذ الإجراء التالي مباشرة.</p>
    </div>

    <nav class="segment-tabs" aria-label="تصفية المهام">
        @foreach ($filters as $filterKey => $filterLabel)
            <a href="{{ route('delivery.orders.my-tasks', ['filter' => $filterKey]) }}"
               class="segment-tab {{ $filter === $filterKey ? 'active' : '' }}"
               @if ($filter === $filterKey) aria-current="page" @endif>
                {{ $filterLabel }} <span class="count">{{ $filterCounts[$filterKey] }}</span>
            </a>
        @endforeach
    </nav>

    @forelse ($myDeliveries as $delivery)
        @php
            $presented = \App\Services\StatusPresenter::present('delivery', $delivery->status);
            $itemCount = (float) $delivery->lines->sum('quantity');
        @endphp
        <article class="task-card tone-{{ $presented['tone'] }}" aria-labelledby="delivery-title-{{ $delivery->id }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h2 id="delivery-title-{{ $delivery->id }}" class="task-card-title">
                        <a href="{{ route('delivery.orders.show', $delivery) }}" class="text-reset text-decoration-none">{{ $delivery->customer_name_snapshot }}</a>
                    </h2>
                    <div class="task-card-ref"><span class="ltr-isolate">{{ $delivery->delivery_number }}</span></div>
                </div>
                <x-status-badge domain="delivery" :status="$delivery->status" />
            </div>

            <div class="task-card-meta">
                <span><i class="fas fa-location-dot" aria-hidden="true"></i>{{ collect([$delivery->city_snapshot, $delivery->district_snapshot])->filter()->implode(' — ') ?: 'العنوان غير محدد' }}</span>
                <span><i class="far fa-calendar" aria-hidden="true"></i>
                    @if ($delivery->scheduled_delivery_date)
                        {{ $delivery->scheduled_delivery_date->isToday() ? 'اليوم' : $delivery->scheduled_delivery_date->format('Y-m-d') }}
                    @else
                        بدون موعد
                    @endif
                    @if ($delivery->scheduled_time_notes) ({{ $delivery->scheduled_time_notes }}) @endif
                </span>
                <span><i class="fas fa-couch" aria-hidden="true"></i>{{ rtrim(rtrim(number_format($itemCount, 2), '0'), '.') }} قطعة</span>
                @if ($delivery->installation_required)
                    <span><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i>يتطلب تركيب</span>
                @endif
            </div>

            <ul class="list-unstyled mb-0 fs-7 border-top pt-2">
                @foreach ($delivery->lines as $line)
                    @php $linePo = $line->productionOrder; @endphp
                    <li class="d-flex justify-content-between gap-2 py-1">
                        <span class="min-w-0">
                            {{ $linePo?->productModel?->name_ar ?? $linePo?->customerOrderLine?->productModel?->name_ar ?? 'منتج أثاث' }}
                            @if ($linePo?->requested_width_cm)
                                <span class="text-muted">— <span class="ltr-isolate">{{ (int) $linePo->requested_width_cm }}×{{ (int) $linePo->requested_length_cm }}</span></span>
                            @endif
                        </span>
                        <strong class="flex-shrink-0">× {{ (float) $line->quantity }}</strong>
                    </li>
                @endforeach
            </ul>

            <div class="task-card-actions">
                @if ($delivery->customer_phone_snapshot)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $delivery->customer_phone_snapshot) }}" class="btn btn-outline-success">
                        <i class="fas fa-phone" aria-hidden="true"></i> اتصال
                    </a>
                @endif
                <a href="{{ route('delivery.orders.show', $delivery) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-folder-open" aria-hidden="true"></i> التفاصيل
                </a>
            </div>
            <div class="task-card-actions">
                @include('delivery.orders.partials.next-actions', ['delivery' => $delivery, 'failSheetId' => 'fail-sheet-'.$delivery->id, 'assignSheetId' => null])
            </div>
            @if (in_array($delivery->status, ['FAILED', 'RESCHEDULED'], true))
                <p class="fs-7 text-muted mb-0"><i class="fas fa-circle-info" aria-hidden="true"></i> هذه المهمة بانتظار قرار الإدارة لإعادة الجدولة أو الإلغاء.</p>
            @endif
        </article>

        @if ($delivery->status === 'OUT_FOR_DELIVERY')
            @include('delivery.orders.partials.fail-sheet', ['delivery' => $delivery, 'sheetId' => 'fail-sheet-'.$delivery->id])
        @endif
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold">{{ $emptyMessages[$filter] }}</p>
        </div>
    @endforelse

    @if ($myDeliveries->hasPages())
        <div class="d-flex justify-content-center">{{ $myDeliveries->links() }}</div>
    @endif
</div>
@endsection
