@extends('layouts.app')
@section('title', 'مراجعة طلب بوابة العملاء')
@section('content')
<a href="{{ route('sales.portal-requests.index') }}">العودة للطلبات الواردة</a>
<h1 class="h3 mt-3">مراجعة <bdi>PR-{{ $submission->id }}</bdi> — {{ $submission->customer->name }}</h1><p>{{ $submission->status_label }} — النسخة {{ $submission->revision }}</p>
@if($submission->change_note)<div class="alert alert-warning">طلب تعديل من العميل: {{ $submission->change_note }}</div>@endif
@if($submission->customer_order_id)<a class="btn btn-outline-primary mb-3" href="{{ route('sales.orders.show', $submission->customer_order_id) }}">فتح طلب البيع المعتمد</a>@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('sales.portal-requests.offer', $submission) }}">
    @csrf @include('portal.request-fields', ['isReview' => true])
    <div class="card border-0 shadow-sm mt-3"><div class="card-body"><h2 class="h5">شروط العرض المرسل للعميل</h2><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="promised_delivery_date">موعد التسليم المقترح</label><input class="form-control" type="date" id="promised_delivery_date" name="promised_delivery_date" value="{{ old('promised_delivery_date', $submission->promised_delivery_date?->toDateString()) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="payment_terms_type">شروط السداد</label><select class="form-select" id="payment_terms_type" name="payment_terms_type" required>@foreach(\App\Models\CustomerPortalRequest::PAYMENT_TERMS as $value => $label)<option value="{{ $value }}" @selected(old('payment_terms_type', $submission->payment_terms_type) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="deposit_required_amount">العربون المطلوب (ر.س)</label><input class="form-control" type="number" min="0" step="0.01" id="deposit_required_amount" name="deposit_required_amount" value="{{ old('deposit_required_amount', $submission->deposit_required_amount) }}"></div>
        <div class="col-md-6"><label class="form-label" for="payment_due_date">موعد استحقاق السداد (إلزامي للبيع الآجل)</label><input class="form-control" type="date" id="payment_due_date" name="payment_due_date" value="{{ old('payment_due_date', $submission->payment_due_date?->toDateString()) }}"></div>
        <div class="col-12"><label class="form-label" for="public_response">رسالة وشروط إضافية تظهر للعميل</label><textarea class="form-control" id="public_response" name="public_response" maxlength="5000" rows="3">{{ old('public_response', $submission->public_response) }}</textarea></div>
    </div><p class="text-muted mt-3">إرسال عرض جديد يتطلب تأكيد العميل مجددًا. لا يمكن تعديل العرض بعد إنشاء أوامر التنفيذ؛ تُراجع طلبات التعديل عندها مع مدير الإنتاج.</p><button class="btn btn-primary">إرسال العرض للعميل</button></div></div>
</form>
@if(in_array($submission->status, ['SUBMITTED', 'OFFERED']))<form method="POST" action="{{ route('sales.portal-requests.return', $submission) }}" class="card mt-3"><div class="card-body">@csrf<input type="hidden" name="revision" value="{{ $submission->revision }}"><label class="form-label" for="return_message">إعادة للعميل لاستكمال بيانات</label><textarea class="form-control mb-3" id="return_message" name="message" maxlength="5000" required></textarea><button class="btn btn-outline-secondary">إعادة للاستكمال</button></div></form>@endif
<h2 class="h5 mt-4">سجل العروض والتأكيدات</h2><ul class="list-group">@foreach($submission->events as $event)<li class="list-group-item">{{ $event->label }} — نسخة {{ $event->revision }} — {{ $event->created_at->format('Y-m-d H:i') }}<div>{{ $event->message }}</div>@if($event->snapshot)<div class="text-muted">إجمالي النسخة: {{ number_format($event->snapshot['offer_total'], 2) }} ر.س</div>@endif</li>@endforeach</ul>
@endsection
