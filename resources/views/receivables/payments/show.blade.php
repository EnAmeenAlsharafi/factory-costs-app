@extends('layouts.app')

@section('title', 'تفاصيل الدفعة ' . $payment->payment_number)

@section('content')
<div class="container-fluid px-4 py-3">
    {{-- Header Actions --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('receivables.payments.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i>
                </a>
                <h1 class="h3 fw-bold text-dark mb-0">
                    دفعة عميل: <span class="font-monospace text-warning">{{ $payment->payment_number }}</span>
                </h1>
                @switch($payment->status)
                    @case('CONFIRMED')
                        <span class="badge bg-success fs-6 px-3 py-2 ms-2">مؤكدة (CONFIRMED)</span>
                        @break
                    @case('PENDING_CONFIRMATION')
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2 ms-2">تنتظر التأكيد (PENDING)</span>
                        @break
                    @case('REVERSED')
                        <span class="badge bg-danger fs-6 px-3 py-2 ms-2">معكوسة (REVERSED)</span>
                        @break
                    @case('CANCELLED')
                        <span class="badge bg-secondary fs-6 px-3 py-2 ms-2">ملغاة (CANCELLED)</span>
                        @break
                @endswitch
            </div>
            <p class="text-muted mb-0 small">تاريخ الاستلام: {{ $payment->payment_date->format('Y-m-d') }} | العميل: {{ $payment->customer->name ?? '-' }}</p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('receivables.payments.receipt', $payment) }}" target="_blank" class="btn btn-outline-dark fw-bold px-3">
                <i class="fas fa-print me-1"></i> إيصال الاستلام
            </a>

            @if ($payment->status === 'PENDING_CONFIRMATION')
                @can('receivables.payment.confirm')
                    <form method="POST" action="{{ route('receivables.payments.confirm', $payment) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success fw-bold px-3">
                            <i class="fas fa-check-circle me-1"></i> تأكيد واعتماد الدفعة
                        </button>
                    </form>
                @endcan
                @can('receivables.payment.create')
                    <form method="POST" action="{{ route('receivables.payments.cancel', $payment) }}" class="d-inline" onsubmit="return confirm('هل أنت تأكد من إلغاء طلب الدفعة؟');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger px-3">إلغاء المسودة</button>
                    </form>
                @endcan
            @endif

            @if ($payment->status === 'CONFIRMED')
                @can('receivables.payment.reverse')
                    <button type="button" class="btn btn-outline-danger fw-bold px-3" data-bs-toggle="modal" data-bs-target="#reversePaymentModal">
                        <i class="fas fa-undo me-1"></i> عكس الدفعة
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Financial Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">المبلغ الإجمالي المقبوض</div>
                    <h3 class="fw-bold mb-0 text-dark">{{ number_format($payment->amount, 2) }} <small class="fs-6 text-muted">ر.س</small></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">المبلغ المخصص للطلبات</div>
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($payment->allocated_amount, 2) }} <small class="fs-6 text-muted">ر.س</small></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold mb-1">المبلغ المتاح وغير المخصص</div>
                    <h3 class="fw-bold mb-0 text-primary">{{ number_format($payment->unallocated_amount, 2) }} <small class="fs-6 text-muted">ر.س</small></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Payment Header Info --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-info-circle text-warning me-2"></i> بيانات الدفعة
                    </h5>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted w-35">العميل:</td>
                            <td class="fw-bold text-dark">
                                <a href="{{ route('receivables.customers.show', $payment->customer) }}" class="text-decoration-none text-dark">
                                    {{ $payment->customer->name }} ({{ $payment->customer->customer_code }})
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">طريقة الدفع:</td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                                    @switch($payment->payment_method)
                                        @case('CASH') نقداً @break
                                        @case('BANK_TRANSFER') تحويل بنكي @break
                                        @case('CARD') بطاقة شبكة / مدى @break
                                        @case('CHEQUE') شيك مصرفي @break
                                        @default {{ $payment->payment_method }}
                                    @endswitch
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">مرجع العمليّة:</td>
                            <td class="font-monospace fw-bold">{{ $payment->reference_number ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">اسم البنك:</td>
                            <td>{{ $payment->bank_reference ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">مُدخل الدفعة:</td>
                            <td>{{ $payment->createdBy->name ?? '-' }}</td>
                        </tr>
                        @if ($payment->confirmedBy)
                            <tr>
                                <td class="text-muted">معتمد الدفعة:</td>
                                <td class="text-success fw-bold">{{ $payment->confirmedBy->name }} ({{ $payment->confirmed_at?->format('Y-m-d H:i') }})</td>
                            </tr>
                        @endif
                        @if ($payment->reversedBy)
                            <tr>
                                <td class="text-muted text-danger">معكوس بواسطة:</td>
                                <td class="text-danger fw-bold">{{ $payment->reversedBy->name }} ({{ $payment->reversed_at?->format('Y-m-d H:i') }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-danger">سبب العكس:</td>
                                <td class="text-danger small">{{ $payment->reversal_reason }}</td>
                            </tr>
                        @endif
                        @if ($payment->notes)
                            <tr>
                                <td class="text-muted">ملاحظات:</td>
                                <td class="small">{{ $payment->notes }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Allocations Section --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-link text-warning me-2"></i> تخصيص الدفعة على الطلبات (Payment Allocations)
                    </h5>
                </div>
                <div class="card-body p-3">
                    {{-- Allocations List Table --}}
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>نوع التخصيص</th>
                                    <th class="text-end">المبلغ المخصص</th>
                                    <th class="text-end">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($payment->allocations as $alloc)
                                    <tr>
                                        <td class="font-monospace fw-bold">
                                            @if ($alloc->order)
                                                <a href="{{ route('sales.orders.show', $alloc->order) }}" class="text-decoration-none">
                                                    {{ $alloc->order->order_number }}
                                                </a>
                                            @else
                                                #{{ $alloc->customer_order_id }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info border px-2 py-1">
                                                @switch($alloc->allocation_type)
                                                    @case('DEPOSIT') عربون @break
                                                    @case('PARTIAL_PAYMENT') سداد جزئي @break
                                                    @case('FINAL_PAYMENT') سداد نهائي @break
                                                    @default تخصيص عام
                                                @endswitch
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-success">{{ number_format($alloc->allocated_amount, 2) }} ر.س</td>
                                        <td class="text-end">
                                            @if ($payment->status === 'CONFIRMED')
                                                @can('receivables.allocate')
                                                    <form method="POST" action="{{ route('receivables.allocations.destroy', $alloc) }}" class="d-inline" onsubmit="return confirm('هل تأكد من إزالة هذا التخصيص عن الطلب؟');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 title='إلغاء التخصيص'">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">لم يتم تخصيص أي مبلغ من هذه الدفعة على طلبات بعد.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Allocation Form --}}
                    @if ($payment->status === 'CONFIRMED' && $payment->unallocated_amount > 0)
                        @can('receivables.allocate')
                            <div class="border rounded-3 p-3 bg-light">
                                <h6 class="fw-bold mb-2 text-dark small"><i class="fas fa-plus-circle text-success me-1"></i> إضافة تخصيص جديد من المبلغ المتاح</h6>
                                <form method="POST" action="{{ route('receivables.allocations.store') }}" class="row g-2">
                                    @csrf
                                    <input type="hidden" name="customer_payment_id" value="{{ $payment->id }}">
                                    <div class="col-12 col-md-5">
                                        <select name="customer_order_id" class="form-select form-select-sm" required>
                                            <option value="">-- اختر الطلب القائم --</option>
                                            @foreach ($openOrders as $ord)
                                                <option value="{{ $ord->id }}">
                                                    {{ $ord->order_number }} (المتبقي: {{ number_format($ord->outstanding_balance, 2) }} ر.س)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <input type="number" step="0.01" min="0.01" max="{{ $payment->unallocated_amount }}" name="allocated_amount" class="form-control form-control-sm font-monospace fw-bold" placeholder="المبلغ" value="{{ $payment->unallocated_amount }}" required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">تخصيص</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Audit Log --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-history text-muted me-2"></i> سجل العمليات الميدانية للدفعة (Payment Events Audit Log)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">التاريخ والوقت</th>
                            <th>الحدث</th>
                            <th>المستخدم</th>
                            <th>تفاصيل وملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payment->events as $ev)
                            <tr>
                                <td class="ps-3 font-monospace small text-muted">{{ $ev->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                                        {{ $ev->event_type }}
                                    </span>
                                </td>
                                <td>{{ $ev->user->name ?? '-' }}</td>
                                <td class="small">{{ $ev->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Reversal Modal --}}
@if ($payment->status === 'CONFIRMED')
    <div class="modal fade" id="reversePaymentModal" tabindex="-1" aria-labelledby="reversePaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('receivables.payments.reverse', $payment) }}">
                    @csrf
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-header-title mb-0 fw-bold" id="reversePaymentModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i> عكس الدفعة المؤكدة
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning border-0 small mb-3">
                            <i class="fas fa-exclamation-circle me-1"></i> تحذير: عملية عكس الدفعة ستلغي تأثير هذه الدفعة وتخصيصاتها وتُعيد المستحقات على الطلبات المرتبطة بها. لا يمكن التراجع عن هذه العملية.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">سبب العكس والإلغاء <span class="text-danger">*</span></label>
                            <textarea name="reversal_reason" rows="3" class="form-control" placeholder="يرجى كتابة المبرر أو سبب الخطأ في إدخال الدفعة..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger fw-bold">تأكيد عكس الدفعة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
