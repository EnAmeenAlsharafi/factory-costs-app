@extends('layouts.portal')
@section('title', 'تفاصيل الطلب والعرض')
@section('content')
@php
    $showPrices = in_array($submission->status, ['OFFERED', 'CONFIRMED'], true);
    $isEditable = in_array($submission->status, ['DRAFT', 'CHANGES_REQUESTED'], true);
@endphp

<div class="mb-3">
    <a class="link-secondary fs-7 text-decoration-none" href="{{ route('portal.requests.index') }}"><i class="fas fa-arrow-right ms-1" aria-hidden="true"></i> طلباتي وعروضي</a>
    <h1 class="h4 fw-bold mt-1 mb-2">طلب <span class="ltr-isolate">PR-{{ $submission->id }}</span>@if($submission->order) <small class="fs-6 text-muted fw-normal">— رقم الطلب <span class="ltr-isolate">{{ $submission->order->order_number }}</span></small>@endif</h1>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-status-badge domain="portal_request" :status="$submission->status" size="lg" />
        <span class="text-muted fs-7">نسخة {{ $submission->revision }}</span>
    </div>
    @if($submission->customer_reference)
        <p class="text-muted fs-7 mt-2 mb-0">مرجعك: {{ $submission->customer_reference }}</p>
    @endif
</div>

@if($submission->public_response)
    <div class="alert alert-info"><strong class="d-block">رسالة خدمة العملاء</strong>{{ $submission->public_response }}</div>
@endif
@if($submission->change_note)
    <div class="alert alert-warning"><strong class="d-block">طلب التعديل</strong>{{ $submission->change_note }}</div>
@endif

{{-- The offer figures come first on a phone: this is what the customer opens the page to see. --}}
@if($showPrices)
    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="portal-tile-label mb-1"><i class="fas fa-file-invoice" aria-hidden="true"></i> إجمالي العرض</div>
            <div class="h3 fw-bold mb-3">{{ number_format($submission->offer_total, 2) }} <span class="fs-6 fw-normal text-muted">ر.س</span></div>
            <dl class="portal-facts mb-0">
                <div><dt>موعد التسليم المقترح</dt><dd><span class="ltr-isolate">{{ $submission->promised_delivery_date?->format('Y-m-d') ?? '—' }}</span></dd></div>
                <div><dt>شروط السداد</dt><dd>{{ \App\Models\CustomerPortalRequest::PAYMENT_TERMS[$submission->payment_terms_type] ?? '—' }}</dd></div>
                <div><dt>العربون المطلوب</dt><dd>{{ number_format($submission->deposit_required_amount ?? 0, 2) }} ر.س</dd></div>
                <div><dt>موعد السداد</dt><dd>
                    @if($submission->payment_due_date)
                        <span class="ltr-isolate">{{ $submission->payment_due_date->format('Y-m-d') }}</span>
                    @else
                        <span class="fw-normal text-muted">حسب شروط العرض</span>
                    @endif
                </dd></div>
            </dl>
        </div>
    </section>
@endif

<section class="card border-0 shadow-sm mb-3">
    <div class="card-body pb-0"><h2 class="h6 fw-bold mb-0">بنود الطلب</h2></div>
    @include('portal.line-summary', ['showPrices' => $showPrices])
</section>

<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">التسليم</h2>
        <dl class="portal-facts mb-0">
            <div><dt>تاريخ التسليم المطلوب</dt><dd>
                @if($submission->requested_delivery_date)
                    <span class="ltr-isolate">{{ $submission->requested_delivery_date->format('Y-m-d') }}</span>
                @else
                    <span class="fw-normal text-muted">غير محدد</span>
                @endif
            </dd></div>
            <div><dt>مدينة التسليم</dt><dd>{{ $submission->delivery_city ?: '—' }}</dd></div>
        </dl>
        <hr class="my-3">
        <dl class="mb-0">
            <dt class="text-muted fs-7 fw-semibold">عنوان التسليم</dt>
            <dd class="fw-bold">{{ $submission->delivery_address ?: '—' }}</dd>
            <dt class="text-muted fs-7 fw-semibold">ملاحظاتك</dt>
            <dd class="mb-0">{{ $submission->customer_notes ?: '—' }}</dd>
        </dl>
    </div>
</section>

{{-- Secondary actions stay in the flow; the primary one sits in the thumb-reachable bar below. --}}
<div class="d-grid gap-2 d-md-flex flex-md-wrap mb-3">
    @if($isEditable)
        <a class="btn btn-outline-primary" href="{{ route('portal.requests.edit', $submission) }}">تعديل المسودة</a>
    @endif
    @if($submission->customer_order_id)
        <a class="btn btn-outline-primary" href="{{ route('portal.orders.show', $submission->customer_order_id) }}">متابعة التنفيذ</a>
    @endif
</div>

@if(in_array($submission->status, ['SUBMITTED', 'OFFERED', 'CONFIRMED'], true))
    <div class="accordion mb-3" id="portal-change-request">
        <div class="accordion-item border-0 shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#portal-change-body" aria-expanded="false" aria-controls="portal-change-body">
                    طلب تعديل أو استفسار
                </button>
            </h2>
            <div id="portal-change-body" class="accordion-collapse collapse" data-bs-parent="#portal-change-request">
                <div class="accordion-body">
                    <form method="POST" action="{{ route('portal.requests.changes', $submission) }}">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $submission->revision }}">
                        <label for="message" class="form-label">اكتب ما تريد تعديله في المواصفات أو استفسارك</label>
                        <textarea id="message" name="message" class="form-control mb-3" rows="3" maxlength="5000" required></textarea>
                        <button class="btn btn-outline-primary w-100">إرسال طلب التعديل</button>
                        <small class="text-muted d-block mt-2">إذا بدأ التنفيذ، يراجع المصنع إمكانية التعديل قبل تغيير أي مواصفات.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

<h2 class="h6 fw-bold mb-2">سجل الطلب</h2>
<ul class="list-group shadow-sm">
    @forelse($submission->events as $event)
        <li class="list-group-item">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <strong>{{ $event->label }}</strong>
                <small class="text-muted"><span class="ltr-isolate">{{ $event->created_at->format('Y-m-d H:i') }}</span></small>
            </div>
            @if($event->message)
                <div class="fs-7 mt-1">{{ $event->message }}</div>
            @endif
        </li>
    @empty
        <li class="list-group-item text-muted">لا توجد حركات مسجلة بعد.</li>
    @endforelse
</ul>

@if($submission->status === 'OFFERED')
    <x-mobile-action-bar>
        <form method="POST" action="{{ route('portal.requests.confirm', $submission) }}" onsubmit="return confirm('هل تؤكد موافقتك على السعر والمواصفات وشروط السداد في هذا العرض؟');">
            @csrf
            <input type="hidden" name="revision" value="{{ $submission->revision }}">
            <button class="btn btn-success">أوافق على العرض وأؤكد الطلب</button>
        </form>
    </x-mobile-action-bar>
@elseif($submission->status === 'DRAFT')
    <x-mobile-action-bar>
        <form method="POST" action="{{ route('portal.requests.submit', $submission) }}">
            @csrf
            <input type="hidden" name="revision" value="{{ $submission->revision }}">
            <button class="btn btn-primary">إرسال للمصنع للمراجعة والتسعير</button>
        </form>
    </x-mobile-action-bar>
@endif
@endsection
