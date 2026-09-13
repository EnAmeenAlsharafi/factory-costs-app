@extends('layouts.app')

@section('title', 'تفاصيل سند استلام المنتجات الجاهزة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fas fa-receipt text-primary me-2"></i>سند استلام منتجات جاهزة: <span class="text-primary font-monospace">{{ $receipt->receipt_number }}</span>
            </h4>
            <p class="text-muted mb-0">سند تسليم الإنتاج المكتمل وإيداعه بمستودع المنتجات الجاهزة</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('finished-goods.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للمخزون
            </a>
            @if($receipt->isDraft() && auth()->user()->can('finished_goods.receive'))
                <form action="{{ route('finished-goods.receipts.post', $receipt) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success fw-bold" onclick="return confirm('هل أنت تأكد من ترحيل سند الاستلام هذا وإيداع الرصيد بالمستودع؟ لا يمكن الإلغاء بعد الترحيل.')">
                        <i class="fas fa-check-circle me-1"></i> ترحيل وإيداع بالمستودع
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-info-circle me-1"></i> معلومات السند والترحيل</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">حالة السند:</label>
                        <div>
                            @if($receipt->isPosted())
                                <span class="badge bg-success fs-7 px-3 py-1"><i class="fas fa-check me-1"></i> مرحل ومودع بالمستودع</span>
                            @else
                                <span class="badge bg-warning text-dark fs-7 px-3 py-1"><i class="fas fa-clock me-1"></i> مسودة DRAFT</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">تاريخ التسليم والإيداع:</label>
                        <div class="fw-bold">{{ $receipt->receipt_date->format('Y-m-d') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">المستودع المودع به:</label>
                        <div class="fw-bold text-dark">{{ $receipt->warehouse->name_ar }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fs-8">مُنشئ السند:</label>
                        <div>{{ $receipt->createdByUser->name }}</div>
                    </div>
                    @if($receipt->posted_at)
                        <div class="mb-3">
                            <label class="form-label text-muted fs-8">تاريخ ووقت الترحيل:</label>
                            <div class="text-secondary fs-8">{{ $receipt->posted_at->format('Y-m-d H:i') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-box me-1"></i> تفاصيل الكمية المسلمة</h6>
                    <span class="fs-5 fw-bold text-success">{{ number_format($receipt->quantity, 0) }} قطعة</span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <tbody class="bg-white">
                                <tr>
                                    <th class="bg-light w-25">أمر الإنتاج</th>
                                    <td>
                                        <a href="{{ route('production.orders.show', $receipt->productionOrder) }}" class="fw-bold text-primary text-decoration-none">
                                            {{ $receipt->productionOrder->production_order_number }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">طلب العميل</th>
                                    <td>
                                        <a href="{{ route('sales.orders.show', $receipt->customerOrder) }}" class="fw-bold text-dark text-decoration-none">
                                            {{ $receipt->customerOrder->order_number }}
                                        </a> - {{ $receipt->customerOrder->customer->name ?? $receipt->customerOrder->customer->name_ar }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">المنتج والمقاس</th>
                                    <td>
                                        {{ $receipt->productionOrder->customerOrderLine->productModel?->name_ar ?? 'تخصيص حر' }}
                                        ({{ $receipt->productionOrder->customerOrderLine->requested_width_cm }} × {{ $receipt->productionOrder->customerOrderLine->requested_length_cm }} سم)
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">الملاحظات والتوصيات</th>
                                    <td>{{ $receipt->notes ?? 'لا توجد ملاحظات مسجلة' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Ledger Movement Record -->
            @if($receipt->movements->isNotEmpty())
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-success"><i class="fas fa-exchange-alt me-1"></i> سجل حركة المنتجات الجاهزة بالمستودع (Ledger IN)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>رقم الحركة</th>
                                        <th>نوع الحركة</th>
                                        <th>الاتجاه</th>
                                        <th>الكمية</th>
                                        <th>تاريخ الحركة</th>
                                        <th>المسؤول</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($receipt->movements as $mov)
                                        <tr>
                                            <td class="font-monospace fw-bold">{{ $mov->movement_number }}</td>
                                            <td><span class="badge bg-primary">{{ $mov->movement_type }}</span></td>
                                            <td><span class="badge bg-success">إيداع IN</span></td>
                                            <td class="fw-bold text-success">+{{ number_format($mov->quantity, 0) }}</td>
                                            <td class="fs-8 text-muted">{{ $mov->occurred_at->format('Y-m-d H:i') }}</td>
                                            <td>{{ $mov->performedByUser->name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
