@extends('layouts.portal')
@section('title', 'إدخال طلب')
@section('content')
<h1 class="h3 mb-3">{{ $submission->exists ? 'تعديل مسودة الطلب' : 'طلب جديد للمصنع' }}</h1>
<p class="text-muted">اختر المنتجات والمواصفات. ستراجع خدمة العملاء الطلب وتحدد السعر قبل عرضه عليك للتأكيد.</p>
<form method="POST" enctype="multipart/form-data" action="{{ $submission->exists ? route('portal.requests.update', $submission) : route('portal.requests.store') }}">
    @csrf @if($submission->exists) @method('PUT') @endif
    @include('portal.request-fields', ['isReview' => false])
    <button type="submit" class="btn btn-primary mt-3">حفظ المسودة</button>
</form>
@endsection
