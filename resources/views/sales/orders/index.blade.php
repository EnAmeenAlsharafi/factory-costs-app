@extends('layouts.app')

@section('title', 'طلبات العملاء')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">طلبات العملاء (Customer Orders)</h1>
            <p class="text-muted mb-0 fs-7">إدارة طلبات البيع التجارية، المراجعة الفنية، والاعتماد النهائي للإنتاج</p>
        </div>
        <div>
            @can('orders.create')
                <a href="{{ route('sales.orders.create') }}" class="btn btn-warning px-3 fw-semibold">
                    <i class="fas fa-plus me-1"></i> طلب عميل جديد
                </a>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.orders.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="رقم الطلب، أمر الشراء PO..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select">
                        <option value="">-- جميع العملاء --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sales_channel_id" class="form-select">
                        <option value="">-- قناة البيع --</option>
                        @foreach($salesChannels as $channel)
                            <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">-- الحالة --</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة</option>
                        <option value="PENDING_PRODUCTION_REVIEW" {{ request('status') === 'PENDING_PRODUCTION_REVIEW' ? 'selected' : '' }}>بانتظار مراجعة الإنتاج</option>
                        <option value="APPROVED_FOR_PRODUCTION" {{ request('status') === 'APPROVED_FOR_PRODUCTION' ? 'selected' : '' }}>معتمد للإنتاج</option>
                        <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>ملغى</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">رقم الطلب</th>
                            <th>العميل / أمر شراء العميل (PO)</th>
                            <th>قناة البيع</th>
                            <th>تاريخ الطلب / التسليم</th>
                            <th>إجمالي المبلغ</th>
                            <th>حالة الطلب</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">
                                    <a href="{{ route('sales.orders.show', $order) }}" class="text-decoration-none fw-bold">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $order->customer?->name }}</div>
                                    @if($order->customer_po_number)
                                        <small class="text-muted"><i class="fas fa-hashtag me-1"></i> PO: {{ $order->customer_po_number }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $order->salesChannel?->name_ar }}</span>
                                </td>
                                <td>
                                    <div class="fs-7 text-dark">{{ $order->order_date ? $order->order_date->format('Y-m-d') : '-' }}</div>
                                    @if($order->promised_delivery_date)
                                        <small class="text-muted">التسليم: {{ $order->promised_delivery_date->format('Y-m-d') }}</small>
                                    @endif
                                </td>
                                <td class="fw-bold text-dark">
                                    {{ number_format($order->total_amount, 2) }} ر.س
                                </td>
                                <td>
                                    @if($order->status === 'DRAFT')
                                        <span class="badge bg-secondary">مسودة</span>
                                    @elseif($order->status === 'PENDING_PRODUCTION_REVIEW')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> بانتظار مراجعة الإنتاج</span>
                                    @elseif($order->status === 'APPROVED_FOR_PRODUCTION')
                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> معتمد للإنتاج</span>
                                    @elseif($order->status === 'CANCELLED')
                                        <span class="badge bg-danger"><i class="fas fa-ban me-1"></i> ملغى</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($order->status === 'PENDING_PRODUCTION_REVIEW')
                                            @can('orders.review_production')
                                                <a href="{{ route('sales.orders.review', $order) }}" class="btn btn-sm btn-warning fw-semibold" title="مراجعة فنية واتماد">
                                                    <i class="fas fa-clipboard-check me-1"></i> مراجعة
                                                </a>
                                            @endcan
                                        @endif

                                        @if(in_array($order->status, ['DRAFT', 'PENDING_PRODUCTION_REVIEW', 'APPROVED_FOR_PRODUCTION']))
                                            @can('orders.edit')
                                                <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-outline-secondary" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-shopping-bag fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد طلبات عملاء مسجلة حالياً matching الفلترة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
