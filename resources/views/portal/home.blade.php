@extends('layouts.portal')
@section('title', 'الرئيسية')
@section('content')
<div class="mb-3">
    <h1 class="h4 fw-bold mb-1">أهلًا، {{ $customer->commercial_name ?: $customer->name }}</h1>
    <p class="text-muted fs-7 mb-0">أنشئ طلبك وتابع عروضك والتنفيذ والمبالغ المسجلة على حسابك.</p>
</div>

<h2 class="h6 fw-bold text-muted mb-2">طلباتك</h2>
<div class="portal-tiles mb-3">
    @foreach([
        ['طلبات معتمدة', $summary['orders_count'], 'fa-circle-check', ''],
        ['قيد المراجعة', $openRequests, 'fa-hourglass-half', ''],
        ['استلمت منها كميات', $deliveredOrders, 'fa-box-open', ''],
    ] as [$label, $value, $icon, $tone])
        <div class="portal-tile {{ $tone }}">
            <div class="portal-tile-label"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
            <div class="portal-tile-value">{{ number_format((float) $value, 0) }}</div>
        </div>
    @endforeach
</div>

<h2 class="h6 fw-bold text-muted mb-2">حسابك</h2>
<div class="portal-tiles mb-4">
    @foreach([
        ['المتبقي عليك', $summary['outstanding'], 'fa-wallet', 'tone-danger'],
        ['متأخر السداد', $summary['overdue'], 'fa-triangle-exclamation', 'tone-danger'],
        ['رصيد دائن لك', $summary['credit'], 'fa-piggy-bank', 'tone-success'],
    ] as [$label, $value, $icon, $tone])
        <div class="portal-tile {{ (float) $value > 0 ? $tone : '' }}">
            <div class="portal-tile-label"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
            <div class="portal-tile-value">{{ number_format($value, 2) }} <span class="fs-7 fw-normal text-muted">ر.س</span></div>
        </div>
    @endforeach
</div>
<div class="d-grid d-md-block mb-4">
    <a class="btn btn-outline-primary" href="{{ route('portal.account') }}">عرض كشف الحساب التفصيلي</a>
</div>

<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <h2 class="h6 fw-bold mb-0">آخر الطلبات والعروض</h2>
    <a class="btn btn-sm btn-light" href="{{ route('portal.requests.index') }}">عرض الكل</a>
</div>
@include('portal.request-list')
<p class="text-muted fs-7 mt-3">المسودات والعروض التي لم تؤكدها لا تدخل ضمن المبالغ المتبقية. يمكنك متابعة طلباتك السابقة أيضًا من «متابعة التنفيذ».</p>

<x-mobile-action-bar>
    <a class="btn btn-primary" href="{{ route('portal.requests.create') }}"><i class="fas fa-plus ms-1" aria-hidden="true"></i> إنشاء طلب جديد</a>
</x-mobile-action-bar>
@endsection
