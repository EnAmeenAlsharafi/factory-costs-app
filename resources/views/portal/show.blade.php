@extends('layouts.portal')
@section('title', 'تفاصيل الطلب والعرض')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><h1 class="h3">طلب <bdi>PR-{{ $submission->id }}</bdi></h1><span class="badge bg-primary align-self-center p-2">{{ $submission->status_label }}</span></div>
<p class="text-muted">مرجعك: {{ $submission->customer_reference ?: '—' }} — نسخة الطلب: {{ $submission->revision }}</p>
@if($submission->public_response)<div class="alert alert-info"><strong>رسالة خدمة العملاء:</strong> {{ $submission->public_response }}</div>@endif
@if($submission->change_note)<div class="alert alert-warning"><strong>طلب التعديل:</strong> {{ $submission->change_note }}</div>@endif
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    @include('portal.line-summary', ['showPrices' => in_array($submission->status, ['OFFERED', 'CONFIRMED'])])
    <div class="row g-3 mt-2"><div class="col-md-6">تاريخ التسليم المطلوب: {{ $submission->requested_delivery_date?->format('Y-m-d') ?: 'غير محدد' }}<br>عنوان التسليم: {{ $submission->delivery_city }} — {{ $submission->delivery_address }}<br>ملاحظاتك: {{ $submission->customer_notes ?: '—' }}</div>
    @if(in_array($submission->status, ['OFFERED', 'CONFIRMED']))<div class="col-md-6"><strong class="fs-4">الإجمالي: {{ number_format($submission->offer_total, 2) }} ر.س</strong><br>موعد التسليم المقترح: {{ $submission->promised_delivery_date?->format('Y-m-d') }}<br>{{ \App\Models\CustomerPortalRequest::PAYMENT_TERMS[$submission->payment_terms_type] ?? '' }}<br>العربون: {{ number_format($submission->deposit_required_amount ?? 0, 2) }} ر.س<br>موعد السداد: {{ $submission->payment_due_date?->format('Y-m-d') ?: 'حسب شروط العرض' }}</div>@endif</div>
</div></div>
<div class="d-flex flex-wrap gap-2 mb-4">
    @if(in_array($submission->status, ['DRAFT', 'CHANGES_REQUESTED']))<a class="btn btn-outline-primary" href="{{ route('portal.requests.edit', $submission) }}">تعديل المسودة</a>@endif
    @if($submission->status === 'DRAFT')<form method="POST" action="{{ route('portal.requests.submit', $submission) }}">@csrf<input type="hidden" name="revision" value="{{ $submission->revision }}"><button class="btn btn-primary">إرسال للمصنع للمراجعة والتسعير</button></form>@endif
    @if($submission->status === 'OFFERED')<form method="POST" action="{{ route('portal.requests.confirm', $submission) }}" onsubmit="return confirm('هل تؤكد موافقتك على السعر والمواصفات وشروط السداد في هذا العرض؟');">@csrf<input type="hidden" name="revision" value="{{ $submission->revision }}"><button class="btn btn-success">أوافق على العرض وأؤكد الطلب</button></form>@endif
    @if($submission->customer_order_id)<a class="btn btn-outline-primary" href="{{ route('portal.orders.show', $submission->customer_order_id) }}">متابعة التنفيذ</a>@endif
</div>
@if(in_array($submission->status, ['SUBMITTED', 'OFFERED', 'CONFIRMED']))
    <form class="card border-0 shadow-sm mb-4" method="POST" action="{{ route('portal.requests.changes', $submission) }}"><div class="card-body">@csrf<input type="hidden" name="revision" value="{{ $submission->revision }}"><label for="message" class="form-label">طلب تعديل أو استفسار عن المواصفات</label><textarea id="message" name="message" class="form-control mb-3" rows="2" maxlength="5000" required></textarea><button class="btn btn-outline-primary">إرسال طلب التعديل</button><small class="text-muted d-block mt-2">إذا بدأ التنفيذ، يراجع المصنع إمكانية التعديل قبل تغيير أي مواصفات.</small></div></form>
@endif
<h2 class="h5">سجل الطلب</h2><ul class="list-group">@foreach($submission->events as $event)<li class="list-group-item"><strong>{{ $event->label }}</strong> — {{ $event->created_at->format('Y-m-d H:i') }}<div>{{ $event->message }}</div></li>@endforeach</ul>
@endsection
