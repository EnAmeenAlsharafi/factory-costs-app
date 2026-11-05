@extends('layouts.app')
@section('title', 'طلبات بوابة العملاء')
@section('content')
<h1 class="h3 mb-3">طلبات بوابة العملاء</h1><p class="text-muted">راجع المواصفات وحدد السعر قبل إرسال العرض للعميل. الطلبات لا تدخل دورة الإنتاج إلا بعد تأكيد العميل.</p>
<form method="GET" class="d-flex flex-wrap gap-2 mb-3"><label class="visually-hidden" for="status">الحالة</label><select name="status" id="status" class="form-select w-auto"><option value="">كل الحالات</option>@foreach(\App\Models\CustomerPortalRequest::STATUSES as $value => $label)@if($value !== 'DRAFT')<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endif @endforeach</select><button class="btn btn-primary">تصفية</button></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>رقم الطلب</th><th>العميل / المعرض</th><th>الحالة</th><th>مرجع العميل</th><th>تنبيه</th><th>مراجعة</th></tr></thead><tbody>
@forelse($submissions as $submission)<tr><td><bdi>PR-{{ $submission->id }}</bdi></td><td>{{ $submission->customer->name }}</td><td>{{ $submission->status_label }}</td><td>{{ $submission->customer_reference }}</td><td>{{ $submission->change_note ? 'يوجد طلب تعديل' : '—' }}</td><td><a class="btn btn-outline-primary" href="{{ route('sales.portal-requests.show', $submission) }}">مراجعة الطلب</a></td></tr>@empty<tr><td colspan="6" class="text-center p-4">لا توجد طلبات واردة.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $submissions->links() }}</div>
@endsection
