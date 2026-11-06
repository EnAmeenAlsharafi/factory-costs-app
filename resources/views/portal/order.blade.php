@extends('layouts.portal')
@section('title', 'متابعة الطلب')
@section('content')
<div class="mb-3">
    <a class="link-secondary fs-7 text-decoration-none" href="{{ route('portal.orders.index') }}"><i class="fas fa-arrow-right ms-1" aria-hidden="true"></i> متابعة التنفيذ</a>
    <h1 class="h4 fw-bold mt-1 mb-2">الطلب <span class="ltr-isolate">{{ $order->order_number }}</span></h1>
    <x-status-badge domain="portal_order" :status="$progress['state']" size="lg" />
</div>

{{-- Each figure on its own line/cell: a phone cannot read a run-on sentence of numbers. --}}
<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">الكميات</h2>
        <dl class="portal-facts mb-3">
            <div><dt>الكمية المطلوبة</dt><dd>{{ number_format($progress['ordered_quantity'], 0) }}</dd></div>
            <div><dt>صافي المستلم</dt><dd>{{ number_format($progress['delivered_quantity'], 0) }}</dd></div>
            <div><dt>المرتجع للمصنع</dt><dd>{{ number_format($progress['returned_quantity'], 0) }}</dd></div>
        </dl>
        <div class="portal-meter" role="presentation"><span style="width: {{ round($progress['delivered_ratio'] * 100) }}%"></span></div>

        <hr class="my-3">

        <h2 class="h6 fw-bold mb-3">المواعيد</h2>
        <dl class="portal-facts mb-0">
            <div><dt>تاريخ الطلب</dt><dd><span class="ltr-isolate">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</span></dd></div>
            <div><dt>موعد التسليم المتفق عليه</dt><dd>
                @if($order->requested_delivery_date)
                    <span class="ltr-isolate">{{ $order->requested_delivery_date->format('Y-m-d') }}</span>
                @else
                    <span class="fw-normal text-muted">يُحدد مع خدمة العملاء</span>
                @endif
            </dd></div>
        </dl>
    </div>
</section>

<section class="card border-0 shadow-sm mb-3">
    <div class="card-body pb-0">
        <h2 class="h6 fw-bold mb-0">بنود الطلب</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-stack-sm align-middle mb-0">
            <thead><tr><th>المنتج</th><th>المواصفات</th><th>الكمية</th><th>قيمة البند</th></tr></thead>
            <tbody>
                @foreach($order->lines->values() as $lineIndex => $line)
                    <tr>
                        <td class="stack-head">
                        <span class="d-flex align-items-center gap-2 min-w-0">
                            @include('portal.line-image', ['imageUrl' => $lineCards[$lineIndex]['image_url'] ?? null, 'imageAlt' => $line->display_name])
                            <span class="min-w-0">{{ $line->display_name }}</span>
                        </span>
                        </td>
                        <td data-label="المواصفات">
                            @if($line->line_type === 'MANUFACTURED')
                                <span class="ltr-isolate">{{ $line->requested_width_cm }} × {{ $line->requested_length_cm }}</span> سم — {{ $line->has_storage ? 'مع تخزين' : 'بدون تخزين' }}
                                <br>{{ $line->fabricMaterial?->name_ar }} / {{ $line->fabricColor?->color_name_ar ?: $line->fabric_color_code }}
                            @else
                                منتج جاهز / بكج
                            @endif
                        </td>
                        <td data-label="الكمية" class="stack-half">{{ number_format($line->quantity, 0) }}</td>
                        <td data-label="قيمة البند" class="stack-half">{{ number_format($line->line_total, 2) }} ر.س</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">مالية الطلب</h2>
        <dl class="portal-facts mb-0">
            <div><dt>إجمالي الطلب</dt><dd>{{ number_format($order->total_amount, 2) }} ر.س</dd></div>
            <div><dt>المسدد المخصص للطلب</dt><dd>{{ number_format($order->confirmed_paid_amount, 2) }} ر.س</dd></div>
            <div><dt>المتبقي على الطلب</dt><dd class="{{ $order->outstanding_balance > 0 ? 'text-danger' : '' }}">{{ number_format($order->outstanding_balance, 2) }} ر.س</dd></div>
        </dl>
    </div>
</section>

<h2 class="h6 fw-bold mb-2">دفعات التوصيل</h2>
{{-- Phone: one card per delivery run. Tablet/desktop: the same rows as a table. --}}
<div class="task-list mobile-cards-only-lg mb-3">
    @forelse($order->deliveryOrders as $delivery)
        @php($presented = \App\Services\StatusPresenter::present('delivery', $delivery->status))
        <article class="task-card tone-{{ $presented['tone'] }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h3 class="task-card-title"><span class="ltr-isolate">{{ $delivery->delivery_number }}</span></h3>
                    <div class="task-card-ref">{{ number_format($delivery->lines->sum('quantity'), 0) }} قطعة</div>
                </div>
                <x-status-badge domain="delivery" :status="$delivery->status" />
            </div>
            <div class="task-card-meta">
                <span><i class="far fa-calendar" aria-hidden="true"></i> الموعد <span class="ltr-isolate">{{ $delivery->scheduled_delivery_date?->format('Y-m-d') ?? '—' }}</span></span>
                @if($delivery->delivered_at)
                    <span><i class="fas fa-circle-check" aria-hidden="true"></i> سُلّم <span class="ltr-isolate">{{ $delivery->delivered_at->format('Y-m-d') }}</span></span>
                @endif
            </div>
        </article>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-calendar-days fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="mb-0">لم يُجدول التوصيل بعد.</p>
        </div>
    @endforelse
</div>

<div class="card has-mobile-cards-lg mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>أمر التوصيل</th><th>الحالة</th><th>الموعد</th><th>الكمية</th><th>تاريخ التسليم</th></tr></thead>
            <tbody>
                @forelse($order->deliveryOrders as $delivery)
                    <tr>
                        <td><span class="ltr-isolate">{{ $delivery->delivery_number }}</span></td>
                        <td><x-status-badge domain="delivery" :status="$delivery->status" /></td>
                        <td><span class="ltr-isolate">{{ $delivery->scheduled_delivery_date?->format('Y-m-d') }}</span></td>
                        <td>{{ number_format($delivery->lines->sum('quantity'), 0) }}</td>
                        <td><span class="ltr-isolate">{{ $delivery->delivered_at?->format('Y-m-d') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center">لم يُجدول التوصيل بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($order->portalRequest)
    <x-mobile-action-bar>
        <a class="btn btn-outline-primary" href="{{ route('portal.requests.show', $order->portalRequest) }}">العرض المعتمد وطلب تعديل</a>
    </x-mobile-action-bar>
@endif
@endsection
