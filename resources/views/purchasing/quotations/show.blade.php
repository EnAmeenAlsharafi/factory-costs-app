@extends('layouts.app')

@section('title', 'عرض سعر المورد - ' . $supplierQuotation->supplier_quotation_number)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-scale-balanced me-2 text-primary"></i>عرض سعر المورد: {{ $supplierQuotation->supplier_quotation_number }}
            </h1>
            <p class="text-muted small mb-0">المورد: {{ $supplierQuotation->supplier?->name }} | التاريخ: {{ $supplierQuotation->quotation_date->format('Y-m-d') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('purchasing.quotations.index') }}" class="btn btn-outline-secondary rounded-pill px-3">العودة للعروض</a>
            @if($supplierQuotation->status !== 'SELECTED')
                @can('purchasing.manage_quotes')
                <form action="{{ route('purchasing.quotations.select', $supplierQuotation) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                        <i class="fas fa-check-circle me-1"></i>اختيار كمرجعية تجارية لشراء الخامات
                    </button>
                </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">بنود أسعار الخامات والتوريد</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>اسم الخامة</th>
                                <th>الكمية بالوحدة التجارية</th>
                                <th>سعر وحدة الشراء</th>
                                <th>معامل التحويل للوحدة الأساسية</th>
                                <th>السعر المكافئ للوحدة الأساسية</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($supplierQuotation->lines as $line)
                            <tr>
                                <td class="fw-bold text-dark">{{ $line->material?->name_ar }}</td>
                                <td>{{ number_format($line->quoted_quantity, 2) }} {{ $line->purchaseUnit?->name_ar }}</td>
                                <td class="fw-bold">{{ number_format($line->unit_price, 2) }} SAR</td>
                                <td>1 {{ $line->purchaseUnit?->name_ar }} = {{ number_format($line->conversion_factor, 2) }} {{ $line->material?->unitOfMeasure?->name_ar }}</td>
                                <td class="fw-bold text-primary">{{ number_format($line->base_unit_equivalent_price, 4) }} SAR / {{ $line->material?->unitOfMeasure?->name_ar }}</td>
                                <td class="fw-bold text-dark">{{ number_format($line->line_total, 2) }} SAR</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">الملخص المالي والتجاري</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">المجموع الفرعي:</span>
                        <span class="fw-bold text-dark">{{ number_format($supplierQuotation->subtotal, 2) }} SAR</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">الخصم التجاري:</span>
                        <span class="fw-bold text-danger">-{{ number_format($supplierQuotation->discount_amount, 2) }} SAR</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">أجور الشحن والنقل:</span>
                        <span class="fw-bold text-dark">+{{ number_format($supplierQuotation->shipping_amount, 2) }} SAR</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">الإجمالي الصافي:</span>
                        <span class="fw-bold fs-5 text-primary">{{ number_format($supplierQuotation->total_amount, 2) }} SAR</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">الحالة:</small>
                        @if($supplierQuotation->status === 'SELECTED')
                        <span class="badge bg-success fs-6 px-3 py-2 mt-1">تم الاختيار كمرجعية</span>
                        @else
                        <span class="badge bg-secondary fs-6 px-3 py-2 mt-1">{{ $supplierQuotation->status }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
