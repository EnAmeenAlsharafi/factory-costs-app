@extends('layouts.app')

@section('title', 'تسجيل دفعة عميل جديدة')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fas fa-plus-circle text-warning me-2"></i> تسجيل دفعة عميل تشغيلية جديدة
        </h1>
        <p class="text-muted mb-0 small">إدخال بيانات استلام دفعة مالية لحساب العميل أو لطلب مخصص.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('receivables.payments.store') }}">
                        @csrf

                        @if ($selectedOrder)
                            <input type="hidden" name="auto_allocate_order_id" value="{{ $selectedOrder->id }}">
                            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
                                <i class="fas fa-info-circle fs-4 text-info"></i>
                                <div>
                                    <h6 class="fw-bold mb-0">تم بدء الدفعة مخصصة للطلب رقم {{ $selectedOrder->order_number }}</h6>
                                    <small class="text-muted">العميل: {{ $selectedOrder->customer->name }} | المتبقي على الطلب: {{ number_format($selectedOrder->outstanding_balance, 2) }} ر.س</small>
                                </div>
                            </div>
                        @endif

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">اختيار العميل <span class="text-danger">*</span></label>
                                <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required {{ $selectedCustomer ? 'readonly' : '' }}>
                                    <option value="">-- اختر العميل --</option>
                                    @foreach ($customers as $cust)
                                        <option value="{{ $cust->id }}" {{ (old('customer_id', $selectedCustomer?->id) == $cust->id) ? 'selected' : '' }}>
                                            {{ $cust->name }} ({{ $cust->customer_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">تاريخ استلام الدفعة <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">المبلغ المقبوض (ر.س) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-lg fw-bold text-success @error('amount') is-invalid @enderror" placeholder="0.00" value="{{ old('amount', $selectedOrder ? $selectedOrder->outstanding_balance : '') }}" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">طريقة الدفع <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-select form-select-lg @error('payment_method') is-invalid @enderror" required>
                                    <option value="BANK_TRANSFER" {{ old('payment_method') === 'BANK_TRANSFER' ? 'selected' : '' }}>تحويل بنكي (Bank Transfer)</option>
                                    <option value="CASH" {{ old('payment_method') === 'CASH' ? 'selected' : '' }}>نقداً (Cash)</option>
                                    <option value="CARD" {{ old('payment_method') === 'CARD' ? 'selected' : '' }}>بطاقة شبكة / مدى (POS Card)</option>
                                    <option value="SADAD_OR_EXTERNAL" {{ old('payment_method') === 'SADAD_OR_EXTERNAL' ? 'selected' : '' }}>سداد / دفع خارجي</option>
                                    <option value="CHEQUE" {{ old('payment_method') === 'CHEQUE' ? 'selected' : '' }}>شيك مصرفي</option>
                                    <option value="OTHER" {{ old('payment_method') === 'OTHER' ? 'selected' : '' }}>طريقة أخرى</option>
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">رقم الإيصال / المرجع البنكي / رقم العملية</label>
                                <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" placeholder="مثال: TR-982342 أو رقم الحوالة" value="{{ old('reference_number') }}">
                                @error('reference_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">اسم البنك / اسم الحساب المعني</label>
                                <input type="text" name="bank_reference" class="form-control @error('bank_reference') is-invalid @enderror" placeholder="مثال: مصرف الراجحي" value="{{ old('bank_reference') }}">
                                @error('bank_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @can('receivables.payment.confirm')
                                <div class="col-12">
                                    <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                        <input class="form-check-input ms-2" type="checkbox" role="switch" id="autoConfirm" name="auto_confirm" value="1" checked>
                                        <label class="form-check-input-label fw-bold text-dark" for="autoConfirm">
                                            تأكيد واعتماد الدفعة فوراً لتصبح نافذة في حساب العميل
                                        </label>
                                        <div class="small text-muted me-4">عند تفعيلها، ستتغير حالة الدفعة إلى (مؤكدة) مباشرة وستتاح للتخصيص على الطلبات.</div>
                                    </div>
                                </div>
                            @endcan

                            <div class="col-12">
                                <label class="form-label fw-bold small text-dark">ملاحظات تشغيلية إضافية</label>
                                <textarea name="notes" rows="2" class="form-control" placeholder="أي تفاصيل تشغيلية متعلقة بطلب استلام الدفعة...">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="{{ route('receivables.payments.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
                            <button type="submit" class="btn btn-warning px-4 fw-bold">
                                <i class="fas fa-save me-1"></i> حفظ وتأكيد الدفعة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
