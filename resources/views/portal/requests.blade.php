@extends('layouts.portal')
@section('title', 'طلباتي وعروضي')
@section('content')
@php($activeFilterCount = request()->filled('status') ? 1 : 0)
<h1 class="h4 fw-bold mb-3">طلباتي وعروضي</h1>

<x-filter-sheet id="portal-requests-filter" :action="route('portal.requests.index')" :active-count="$activeFilterCount" :reset-url="route('portal.requests.index')" title="تصفية الطلبات">
    <div class="col-12 col-md-auto">
        <label for="status" class="form-label">حالة الطلب</label>
        <select id="status" name="status" class="form-select">
            <option value="">جميع الحالات</option>
            @foreach(\App\Models\CustomerPortalRequest::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</x-filter-sheet>

@if($activeFilterCount > 0)
    <div class="d-flex flex-wrap gap-2 mb-3" aria-label="الفلاتر النشطة">
        <span class="status-chip status-chip-secondary">{{ \App\Models\CustomerPortalRequest::STATUSES[request('status')] ?? request('status') }}</span>
        <a href="{{ route('portal.requests.index') }}" class="link-secondary align-self-center fs-7">مسح الفلاتر</a>
    </div>
@endif

@include('portal.request-list')
<div class="mt-3 d-flex justify-content-center">{{ $submissions->links() }}</div>

<x-mobile-action-bar>
    <a class="btn btn-primary" href="{{ route('portal.requests.create') }}"><i class="fas fa-plus ms-1" aria-hidden="true"></i> طلب جديد</a>
</x-mobile-action-bar>
@endsection
