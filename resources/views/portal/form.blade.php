@extends('layouts.portal')
@section('title', 'إدخال طلب')
@section('content')
<div class="mb-3">
    <a class="link-secondary fs-7 text-decoration-none" href="{{ route('portal.requests.index') }}"><i class="fas fa-arrow-right ms-1" aria-hidden="true"></i> طلباتي وعروضي</a>
    <h1 class="h4 fw-bold mt-1 mb-1">{{ $submission->exists ? 'تعديل مسودة الطلب' : 'طلب جديد للمصنع' }}</h1>
    <p class="text-muted fs-7 mb-0">اختر المنتجات والمواصفات. ستراجع خدمة العملاء الطلب وتحدد السعر قبل عرضه عليك للتأكيد.</p>
</div>

<form method="POST" action="{{ $submission->exists ? route('portal.requests.update', $submission) : route('portal.requests.store') }}">
    @csrf @if($submission->exists) @method('PUT') @endif
    @include('portal.request-fields', ['isReview' => false])

    <x-mobile-action-bar>
        <button type="submit" class="btn btn-primary">حفظ المسودة</button>
    </x-mobile-action-bar>
</form>
@endsection
