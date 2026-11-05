@extends('layouts.portal')
@section('title', 'متابعة التنفيذ')
@section('content')
<h1 class="h3 mb-3">متابعة التنفيذ والطلبات السابقة</h1>
<form method="GET" class="d-flex gap-2 mb-3"><label for="search" class="visually-hidden">رقم الطلب أو مرجع المعرض</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="رقم الطلب أو مرجع المعرض"><button class="btn btn-primary">بحث</button></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>رقم الطلب</th><th>التاريخ</th><th>الحالة</th><th>الكمية المطلوبة</th><th>صافي المستلم</th><th>التفاصيل</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><bdi>{{ $order->order_number }}</bdi><small class="d-block text-muted">{{ $order->customer_reference }}</small></td><td>{{ $order->order_date?->format('Y-m-d') }}</td><td>{{ $progress[$order->id]['status'] }}</td><td>{{ $progress[$order->id]['ordered_quantity'] }}</td><td>{{ $progress[$order->id]['delivered_quantity'] }}</td><td><a class="btn btn-outline-primary" href="{{ route('portal.orders.show', $order) }}">عرض التفاصيل</a></td></tr>@empty<tr><td colspan="6" class="p-4 text-center">لا توجد طلبات مسجلة.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $orders->links() }}</div>
@endsection
