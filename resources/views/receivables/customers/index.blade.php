@extends('layouts.app')

@section('title', 'أرصدة العملاء التشغيلية')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-users-slash text-warning me-2"></i> أرصدة العملاء والائتمان التشغيلي
            </h1>
            <p class="text-muted mb-0 small">استعراض إجمالي طلبات العملاء، المستحقات القائمة، الرصيد المتاح، وحدود الائتمان.</p>
        </div>
        <a href="{{ route('receivables.credit.index') }}" class="btn btn-outline-dark fw-bold px-3">
            <i class="fas fa-credit-card me-1"></i> إدارة الائتمان للعملاء
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('receivables.customers.index') }}" class="row g-3">
                <div class="col-12 col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث برقم العميل، الاسم، الجوال..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4">
                    <select name="is_credit_customer" class="form-select form-select-sm">
                        <option value="">-- تصنيف العملاء --</option>
                        <option value="1" {{ ($filters['is_credit_customer'] ?? '') === '1' ? 'selected' : '' }}>عملاء الائتمان فقط</option>
                        <option value="0" {{ ($filters['is_credit_customer'] ?? '') === '0' ? 'selected' : '' }}>العملاء العاديين (نقدي / عربون)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100 fw-bold">تطبيق الفلترة</button>
                    <a href="{{ route('receivables.customers.index') }}" class="btn btn-sm btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Customer Balances Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3">العميل</th>
                            <th class="text-center">عدد الطلبات القائمة</th>
                            <th class="text-end">إجمالي القيمة التجارية</th>
                            <th class="text-end">المسدد المؤكد</th>
                            <th class="text-end">رصيد غير مخصص</th>
                            <th class="text-end">المتبقي (المستحق)</th>
                            <th class="text-end">حد الائتمان</th>
                            <th class="text-end pe-3">كشف الحساب</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $row)
                            @php
                                $cust = $row['customer'];
                            @endphp
                            <tr>
                                <td class="ps-3 py-3">
                                    <a href="{{ route('receivables.customers.show', $cust) }}" class="fw-bold text-dark text-decoration-none">
                                        {{ $cust->name }}
                                    </a>
                                    <div class="small text-muted font-monospace">{{ $cust->customer_code }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                                        {{ $row['open_orders_count'] }} طلبات
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark">{{ number_format($row['total_commercial_value'], 2) }} ر.س</td>
                                <td class="text-end text-success fw-bold">{{ number_format($row['confirmed_paid'], 2) }} ر.س</td>
                                <td class="text-end text-primary fw-bold">{{ number_format($row['unallocated_credit'], 2) }} ر.س</td>
                                <td class="text-end text-danger fw-bold fs-6">
                                    {{ number_format($row['outstanding_balance'], 2) }} ر.س
                                    @if ($row['overdue_balance'] > 0)
                                        <div class="small text-danger fw-normal" title="مبالغ متأخرة">(متأخر: {{ number_format($row['overdue_balance'], 2) }})</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($row['is_credit_enabled'])
                                        <span class="fw-bold">{{ number_format($row['credit_limit'], 2) }} ر.س</span>
                                        @if ($row['outstanding_balance'] > $row['credit_limit'] && $row['credit_limit'] > 0)
                                            <div class="badge bg-danger p-1 mt-1">متجاوز للحد</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">غير مفعّل</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('receivables.customers.show', $cust) }}" class="btn btn-sm btn-outline-dark fw-bold">
                                        <i class="fas fa-file-alt me-1"></i> كشف تشغيلي
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا يوجد عملاء يطابقون محددات البحث.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($balances->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $balances->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
