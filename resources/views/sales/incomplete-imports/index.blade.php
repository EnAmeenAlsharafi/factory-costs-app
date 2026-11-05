@extends('layouts.app')

@section('title', 'طلبات مستوردة بانتظار الاستكمال')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">طلبات بانتظار الاستكمال</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">طلبات مستوردة بانتظار الاستكمال اليدوي</h1>
            <p class="text-muted mb-0 fs-7">أضف المنتجات ذات الخيارات والأسرّة يدويًا، وراجع الأسعار والدفعات ثم أكد اكتمال الطلب وأرسله للمراجعة.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم الطلب الخارجي</th>
                            <th>العميل</th>
                            <th>قناة البيع</th>
                            <th>تاريخ الطلب</th>
                            <th class="text-center">بنود ناقصة</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="fw-mono fw-bold text-primary">{{ $order->external_order_reference ?? $order->order_number }}</td>
                                <td class="fw-bold text-dark">{{ $order->customer->name ?? '-' }}</td>
                                <td>{{ $order->salesChannel->name_ar ?? '-' }}</td>
                                <td>{{ optional($order->order_date)->format('Y-m-d') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-warning bg-opacity-25 text-warning-emphasis fw-bold px-3 py-1">{{ $order->incomplete_lines_count + count($order->manual_import_lines ?? []) }}</span>
                                </td>
                                <td class="text-center">
                                    @can('orders.update')
                                        <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit me-1"></i> استكمال البيانات
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">لا توجد طلبات مستوردة بانتظار الاستكمال حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
