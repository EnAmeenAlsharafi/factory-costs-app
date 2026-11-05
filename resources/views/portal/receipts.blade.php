@extends('layouts.portal')
@section('title', 'سجل الاستلام')
@section('content')
<h1 class="h3">سجل الاستلام من المصنع</h1><p class="text-muted">تظهر هنا دفعات التوصيل المسلّمة. قد يُسلّم الطلب الواحد على عدة دفعات؛ صافي المستلم بعد المرتجعات ظاهر في تفاصيل الطلب.</p>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>الطلب</th><th>دفعة التوصيل</th><th>القطع المسلّمة</th><th>تاريخ التسليم</th><th>التركيب</th></tr></thead><tbody>@forelse($receipts as $receipt)<tr><td><a href="{{ route('portal.orders.show', $receipt->customer_order_id) }}">{{ $receipt->customerOrder->order_number }}</a></td><td>{{ $receipt->delivery_number }}</td><td>{{ $receipt->lines->sum('quantity') }}</td><td>{{ $receipt->delivered_at?->format('Y-m-d') }}</td><td>{{ $receipt->installed_at?->format('Y-m-d') ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center p-4">لا توجد دفعات مسلّمة حتى الآن.</td></tr>@endforelse</tbody></table></div></div><div class="mt-3">{{ $receipts->links() }}</div>
@endsection
