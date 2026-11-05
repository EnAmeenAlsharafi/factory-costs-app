@extends('layouts.portal')
@section('title', 'الرئيسية')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="h3 fw-bold">أهلًا، {{ $customer->commercial_name ?: $customer->name }}</h1><p class="text-muted mb-0">أنشئ طلبك وتابع عروضك والتنفيذ والمبالغ المسجلة على حسابك.</p></div><a class="btn btn-primary" href="{{ route('portal.requests.create') }}">إنشاء طلب جديد</a></div>
<div class="row g-3 mb-4">
    @foreach(['طلبات معتمدة' => $summary['orders_count'], 'طلبات وعروض قيد المراجعة' => $openRequests, 'طلبات استلمت منها كميات' => $deliveredOrders] as $label => $value)
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><strong class="fs-3">{{ $value }}</strong></div></div></div>
    @endforeach
</div>
<div class="row g-3 mb-4">
    @foreach(['المبلغ المتبقي عليك' => $summary['outstanding'], 'المتأخر حسب مواعيد السداد' => $summary['overdue'], 'رصيد دائن لصالحك' => $summary['credit']] as $label => $value)
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><strong class="fs-4">{{ number_format($value, 2) }} ر.س</strong></div></div></div>
    @endforeach
</div>
<h2 class="h5 mb-3">آخر الطلبات والعروض</h2>
@include('portal.request-list')
<p class="text-muted mt-3">المسودات والعروض التي لم تؤكدها لا تدخل ضمن المبالغ المتبقية. يمكنك متابعة طلباتك السابقة أيضًا من «متابعة التنفيذ».</p>
@endsection
