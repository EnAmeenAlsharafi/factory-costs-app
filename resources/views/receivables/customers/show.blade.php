@extends('layouts.app')

@section('title', 'كشف حساب تشغيلي - ' . $customer->name)

@section('content')
<div class="container-fluid px-4 py-3">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('receivables.customers.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i>
                </a>
                <h1 class="h3 fw-bold text-dark mb-0">
                    المرجع المالي للعميل: <span class="text-warning">{{ $customer->name }}</span>
                </h1>
            </div>
            <p class="text-muted mb-0 small">كود العميل: {{ $customer->customer_code }} | الجوال: {{ $customer->mobile ?: '-' }} | الجملة/التجزئة: {{ $customer->customerType->name_ar ?? '-' }}</p>
        </div>

        <div class="d-flex gap-2">
            @can('receivables.payment.create')
                <a href="{{ route('receivables.payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-warning fw-bold px-3">
                    <i class="fas fa-plus-circle me-1"></i> تسجيل دفعة للعميل
                </a>
            @endcan
        </div>
    </div>

    {{-- Financial KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-dark border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">إجمالي طلبات العميل</div>
                    <h4 class="fw-bold mb-0 text-dark">{{ number_format($customer->orders->whereNotIn('status', ['CANCELLED', 'REJECTED'])->sum('total_amount'), 2) }} <small class="fs-6 text-muted">ر.س</small></h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">المسدد المؤكد</div>
                    <h4 class="fw-bold mb-0 text-success">{{ number_format($customer->orders->whereNotIn('status', ['CANCELLED', 'REJECTED'])->sum('confirmed_paid_amount'), 2) }} <small class="fs-6 text-muted">ر.س</small></h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">رصيد متاح غير مخصص</div>
                    <h4 class="fw-bold mb-0 text-primary">{{ number_format($customer->unallocated_credit, 2) }} <small class="fs-6 text-muted">ر.س</small></h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-danger border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">إجمالي المستحق القائم</div>
                    <h4 class="fw-bold mb-0 text-danger">{{ number_format($customer->total_outstanding_balance, 2) }} <small class="fs-6 text-muted">ر.س</small></h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Statement Card --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-file-invoice text-warning me-2"></i> كشف حساب تشغيلي للعميل (Operational Account Statement)
            </h5>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print me-1"></i> طباعة الكشف
            </button>
        </div>

        <div class="card-body p-0">
            {{-- Disclaimer Banner --}}
            <div class="p-3 bg-light border-bottom text-muted small d-flex align-items-center gap-2">
                <i class="fas fa-info-circle text-warning fs-5"></i>
                <div>
                    <strong>تنويه تشغيلي:</strong> هذا كشف حساب تشغيلي متابع لطلبات ودفعات المصنع فقط، ولا يعد كشف حساب محاسبي رسمي أو فاتورة ضريبية.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3">التاريخ</th>
                            <th>المرجع / الرقم</th>
                            <th>نوع الحركة</th>
                            <th>البيان والتفاصيل</th>
                            <th class="text-end text-danger">طلب / مدين (ر.س)</th>
                            <th class="text-end text-success">دفعة / دائن (ر.س)</th>
                            <th class="text-end pe-3">الرصيد التراكمي (ر.س)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statement as $row)
                            <tr>
                                <td class="ps-3 py-3 font-monospace small">{{ $row['date'] }}</td>
                                <td class="font-monospace fw-bold">
                                    @if ($row['type'] === 'CUSTOMER_ORDER' && isset($row['order']))
                                        <a href="{{ route('sales.orders.show', $row['order']) }}" class="text-decoration-none text-dark">
                                            {{ $row['reference'] }}
                                        </a>
                                    @elseif ($row['type'] === 'PAYMENT_RECEIVED' && isset($row['payment']))
                                        <a href="{{ route('receivables.payments.show', $row['payment']) }}" class="text-decoration-none text-dark">
                                            {{ $row['reference'] }}
                                        </a>
                                    @else
                                        {{ $row['reference'] }}
                                    @endif
                                </td>
                                <td>
                                    @if ($row['type'] === 'CUSTOMER_ORDER')
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 px-2 py-1">طلب عميل</span>
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-2 py-1">دفعة مستلمة</span>
                                    @endif
                                </td>
                                <td class="small">{{ $row['description'] }}</td>
                                <td class="text-end fw-bold text-danger">
                                    {{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' }}
                                </td>
                                <td class="text-end fw-bold text-success">
                                    {{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' }}
                                </td>
                                <td class="text-end pe-3 fw-bold font-monospace fs-6 text-dark">
                                    {{ number_format($row['running_balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">لا توجد حركات مالية تشغيلية لهذا العميل.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
