@extends('layouts.app')

@section('title', 'تسليم منتجات جاهزة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-hand-holding-box text-success me-2"></i>تسليم منتجات جاهزة من الورشة للمستودع</h4>
            <p class="text-muted mb-0">إيداع الكميات المكتملة تصنيعياً في مستودع المنتجات الجاهزة لتصبح متاح للتوصيل</p>
        </div>
        <a href="{{ route('finished-goods.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة للكتالوج
        </a>
    </div>

    <div class="row g-4">
        <!-- Production Snapshot Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-info-circle me-1"></i> بيانات أمر الإنتاج</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">رقم أمر الإنتاج:</label>
                        <div class="fw-bold text-primary fs-6">{{ $productionOrder->production_order_number }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">رقم طلب العميل:</label>
                        <div class="fw-semibold">{{ $productionOrder->customerOrder->order_number }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">العميل:</label>
                        <div class="fw-bold">{{ $productionOrder->customerOrder->customer->name ?? $productionOrder->customerOrder->customer->name_ar }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">المنتج / الموديل:</label>
                        <div>{{ $productionOrder->customerOrderLine->productModel?->name_ar ?? 'تخصيص حر' }}</div>
                        <small class="text-muted">{{ $productionOrder->customerOrderLine->requested_width_cm }} × {{ $productionOrder->customerOrderLine->requested_length_cm }} سم</small>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fs-8">الكمية المطلوبة بأمر الإنتاج:</span>
                        <span class="fw-bold">{{ number_format($productionOrder->ordered_quantity, 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fs-8">إنجاز التغليف والإنتاج المكتمل:</span>
                        <span class="fw-bold text-success">{{ number_format($productionOrder->completed_quantity, 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fs-8">المستلم مسبقاً بالمستودع:</span>
                        <span class="fw-bold text-secondary">{{ number_format($alreadyReceived, 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between p-2 bg-light rounded border">
                        <span class="fw-bold text-dark fs-8">الحد الأقصى المتاح للتسليم الآن:</span>
                        <span class="fw-bold text-success fs-6">{{ number_format($maxEligible, 0) }} قطعة</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Form Card -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-success"><i class="fas fa-file-signature me-1"></i> نموذج سند تسليم المنتجات الجاهزة</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('finished-goods.receipts.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label required">مستودع الإيداع (Finished Goods Warehouse)</label>
                                <select name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ (old('warehouse_id', $fgWarehouse->id) == $wh->id) ? 'selected' : '' }}>
                                            {{ $wh->name_ar }} ({{ $wh->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">تاريخ التسليم والإيداع</label>
                                <input type="date" name="receipt_date" class="form-control @error('receipt_date') is-invalid @enderror" value="{{ old('receipt_date', now()->toDateString()) }}" required>
                                @error('receipt_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label class="form-label required">الكمية المسلمة للمستودع الآن (قطع)</label>
                                <input type="number" name="quantity" class="form-control form-control-lg fw-bold text-success @error('quantity') is-invalid @enderror" step="1" min="1" max="{{ $maxEligible }}" value="{{ old('quantity', min($maxEligible, 1)) }}" required>
                                <small class="text-muted fs-8">لا يمكن تسليم كمية تتجاوز الإنجاز المكتمل بأمر التصنيع (الحد الأقصى: {{ $maxEligible }}).</small>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">ملاحظات الفحص التسليمي</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="أي ملاحظات حول التغليف الظاهري أو التخزين بالمستودع...">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-save me-1"></i> حفظ كـ مسودة
                            </button>
                            <button type="submit" name="action" value="post" class="btn btn-success px-5 fw-bold">
                                <i class="fas fa-check-circle me-1"></i> ترحيل وإيداع بالمستودع فوراً
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
