@extends('layouts.portal')
@section('title', 'طلباتي وعروضي')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><h1 class="h3">طلباتي وعروضي</h1><a class="btn btn-primary" href="{{ route('portal.requests.create') }}">طلب جديد</a></div>
<form class="d-flex flex-wrap gap-2 mb-3" method="GET"><label for="status" class="visually-hidden">حالة الطلب</label><select id="status" name="status" class="form-select w-auto"><option value="">جميع الحالات</option>@foreach(\App\Models\CustomerPortalRequest::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><button class="btn btn-outline-primary">تصفية</button></form>
@include('portal.request-list')
<div class="mt-3">{{ $submissions->links() }}</div>
@endsection
