@extends('layouts.app')

@section('title', 'سجل دفعات العملاء')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-money-bill-wave text-warning me-2"></i> سجل دفعات العملاء (Customer Payments)
            </h1>
            <p class="text-muted mb-0 small">استعراض وتأكيد وعكس دفعات العملاء المسجلة في النظام.</p>
        </div>
        @can('receivables.payment.create')
            <a href="{{ route('receivables.payments.create') }}" class="btn btn-warning fw-bold px-3">
                <i class="fas fa-plus-circle me-1"></i> تسجيل دفعة جديدة
            </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('receivables.payments.index') }}" class="row g-3">
                <div class="col-12 col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث برقم الدفعة، المرجع أو العميل..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">-- كل العملاء --</option>
                        @foreach ($customers as $cust)
                            <option value="{{ $cust->id }}" {{ ($filters['customer_id'] ?? '') == $cust->id ? 'selected' : '' }}>{{ $cust->name }} ({{ $cust->customer_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- كل الحالات --</option>
                        <option value="PENDING_CONFIRMATION" {{ ($filters['status'] ?? '') === 'PENDING_CONFIRMATION' ? 'selected' : '' }}>ينتظر التأكيد</option>
                        <option value="CONFIRMED" {{ ($filters['status'] ?? '') === 'CONFIRMED' ? 'selected' : '' }}>مؤكدة</option>
                        <option value="REVERSED" {{ ($filters['status'] ?? '') === 'REVERSED' ? 'selected' : '' }}>معكوسة</option>
                        <option value="CANCELLED" {{ ($filters['status'] ?? '') === 'CANCELLED' ? 'selected' : '' }}>ملغاة</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">-- طريقة الدفع --</option>
                        <option value="CASH" {{ ($filters['payment_method'] ?? '') === 'CASH' ? 'selected' : '' }}>نقداً</option>
                        <option value="BANK_TRANSFER" {{ ($filters['payment_method'] ?? '') === 'BANK_TRANSFER' ? 'selected' : '' }}>تحويل بنكي</option>
                        <option value="CARD" {{ ($filters['payment_method'] ?? '') === 'CARD' ? 'selected' : '' }}>بطاقة شبكة</option>
                        <option value="CHEQUE" {{ ($filters['payment_method'] ?? '') === 'CHEQUE' ? 'selected' : '' }}>شيك</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100 fw-bold">فلترة</button>
                    <a href="{{ route('receivables.payments.index') }}" class="btn btn-sm btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Payments Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3">رقم الدفعة</th>
                            <th>التاريخ</th>
                            <th>العميل</th>
                            <th>طريقة الدفع والمرجع</th>
                            <th class="text-end">المبلغ الإجمالي</th>
                            <th class="text-end">المخصص</th>
                            <th class="text-end">غير المخصص</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-end pe-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-3 py-3 font-monospace fw-bold text-dark">
                                    <a href="{{ route('receivables.payments.show', $payment) }}" class="text-decoration-none text-dark">
                                        {{ $payment->payment_number }}
                                    </a>
                                </td>
                                <td class="small">{{ $payment->payment_date->format('Y-m-d') }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $payment->customer->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $payment->customer->customer_code ?? '' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-20 px-2 py-1">
                                        @switch($payment->payment_method)
                                            @case('CASH') نقداً @break
                                            @case('BANK_TRANSFER') تحويل بنكي @break
                                            @case('CARD') بطاقة مسبقة/شبكة @break
                                            @case('CHEQUE') شيك @break
                                            @default {{ $payment->payment_method }}
                                        @endswitch
                                    </span>
                                    @if ($payment->reference_number)
                                        <div class="small text-muted font-monospace mt-1">مرجع: {{ $payment->reference_number }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark fs-6">{{ number_format($payment->amount, 2) }} ر.س</td>
                                <td class="text-end text-success fw-bold">{{ number_format($payment->allocated_amount, 2) }} ر.س</td>
                                <td class="text-end text-primary fw-bold">{{ number_format($payment->unallocated_amount, 2) }} ر.س</td>
                                <td class="text-center">
                                    @switch($payment->status)
                                        @case('CONFIRMED')
                                            <span class="badge bg-success px-2 py-1">مؤكدة</span>
                                            @break
                                        @case('PENDING_CONFIRMATION')
                                            <span class="badge bg-warning text-dark px-2 py-1">تنتظر التأكيد</span>
                                            @break
                                        @case('REVERSED')
                                            <span class="badge bg-danger px-2 py-1">معكوسة</span>
                                            @break
                                        @case('CANCELLED')
                                            <span class="badge bg-secondary px-2 py-1">ملغاة</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('receivables.payments.show', $payment) }}" class="btn btn-sm btn-outline-dark me-1" title="التفاصيل والتخصيص">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('receivables.payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="إيصال استلام">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">لا توجد دفعات مسجلة تطابق محددات البحث.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
