@extends('layouts.app')

@section('title', 'تفاصيل سند استلام مواد - ' . $receipt->receipt_number)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.index') }}" class="text-decoration-none">سندات الاستلام</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $receipt->receipt_number }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0 text-dark">سند استلام: <span class="text-primary fw-mono">{{ $receipt->receipt_number }}</span></h1>
                @if($receipt->status === 'POSTED')
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-check-circle me-1"></i> مُرحل ومسجل بالمخزون (Posted)
                    </span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-clock me-1"></i> مسودة (Draft)
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($receipt->status === 'DRAFT')
                @can('inventory.receive')
                    <a href="{{ route('inventory.receipts.edit', $receipt) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> تعديل المسودة
                    </a>
                    <form action="{{ route('inventory.receipts.post', $receipt) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من ترحيل سند الاستلام؟ سيتم زيادة رصيد المخزون فوراً وإنشاء الدفعات.');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i> ترحيل السند للمخزون (Post)
                        </button>
                    </form>
                @endcan
            @endif
            <a href="{{ route('inventory.receipts.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- Alert Banner for Draft -->
    @if($receipt->status === 'DRAFT')
        @php
            $missingFabricColor = $receipt->lines->contains(function ($line) {
                $isFabric = strtoupper($line->material?->category?->code ?? '') === 'FABRIC';
                return $isFabric && empty($line->fabric_color_code) && empty($line->fabric_color_id);
            });
        @endphp

        @if($missingFabricColor)
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-exclamation-triangle fs-4 me-3 text-danger"></i>
                <div>
                    <strong>تحذير:</strong> يوجد بند قماش بدون رقم لون. يلزم تحديد رقم أو كود اللون قبل التمكن من ترحيل السند للمخزون.
                </div>
            </div>
        @endif

        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-circle fs-4 me-3 text-warning"></i>
            <div>
                <strong>تنبيه:</strong> هذا السند ما زال في حالة <strong>مسودة (Draft)</strong> ولم يتم ترحيله إلى أرصدة المخزون بعد. يمكنك مراجعته وتأكيد الترحيل ليتم إنشاء الدفعات وتحديث كميات المواد.
            </div>
        </div>
    @endif

    <!-- Metadata Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-info-circle text-primary me-2"></i>معلومات السند والمورد</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">المورد:</span>
                            <span class="fw-bold text-dark fs-6">{{ $receipt->supplier->name }}</span>
                            <small class="text-muted d-block fw-mono">({{ $receipt->supplier->code }})</small>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">مستودع الاستلام:</span>
                            <span class="badge bg-info bg-opacity-10 text-dark border-0 fs-7 mt-1">{{ $receipt->warehouse->name_ar }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">تاريخ الاستلام:</span>
                            <span class="fw-bold text-dark">{{ $receipt->receipt_date->format('Y-m-d') }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">رقم فاتورة/شحنة المورد:</span>
                            <span class="fw-mono fw-bold text-dark">{{ $receipt->supplier_invoice_number ?? '-' }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">أنشئ بواسطة:</span>
                            <span class="fw-bold text-dark">{{ $receipt->user->name }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">رحل بواسطة:</span>
                            <span class="fw-bold text-dark">{{ $receipt->postedBy?->name ?? '-' }}</span>
                        </div>

                        <div class="col-12">
                            <span class="text-muted d-block fs-7">الملاحظات:</span>
                            <span class="text-dark">{{ $receipt->notes ?? 'لا يوجد' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-primary bg-opacity-10 border-start border-primary border-4">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <span class="text-uppercase text-muted fw-bold fs-7 d-block mb-1">إجمالي قيمة الاستلام</span>
                        <h2 class="display-6 fw-bold text-primary mb-0 fw-mono">
                            {{ number_format($receipt->total_amount, 2) }}
                        </h2>
                        <small class="text-muted">ريال سعودي</small>
                    </div>

                    <div class="pt-3 border-top border-primary border-opacity-25 mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-7">عدد البنود:</span>
                            <span class="fw-bold fw-mono">{{ $receipt->lines->count() }} صنف</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-7">تاريخ التسجيل:</span>
                            <span class="fw-mono fs-7">{{ $receipt->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lines & Lots Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i>تفاصيل البنود والكميات والدفعات</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>المادة الخام</th>
                            <th class="text-center">كمية المستند</th>
                            <th>الوحدة المستلمة</th>
                            <th class="text-center">معامل التحويل للأساسية</th>
                            <th class="text-center">الكمية الأساسية (Base)</th>
                            <th class="text-end">سعر الوحدة</th>
                            <th class="text-end">الإجمالي</th>
                            <th class="text-center pe-3">الدفعة المنشأة (Lot Number)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receipt->lines as $index => $line)
                            <tr>
                                <td class="ps-3 text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $line->material->name_ar }}</div>
                                    <small class="text-muted fw-mono">{{ $line->material->code }}</small>
                                    @if($line->fabric_color_code || $line->fabricColor)
                                        <div class="mt-1">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-8">
                                                <i class="fas fa-palette me-1"></i>رقم / كود اللون: <strong class="fw-mono">{{ $line->fabric_color_code ?? $line->fabricColor?->color_code }}</strong>
                                                @if($line->fabricColor && $line->fabricColor->color_name_ar)
                                                    ({{ $line->fabricColor->color_name_ar }})
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center fw-mono fw-bold">{{ number_format($line->received_quantity, 4) }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $line->purchaseUnit->name_ar }}</span></td>
                                <td class="text-center fw-mono text-muted">1 {{ $line->purchaseUnit->name_ar }} = {{ number_format($line->conversion_factor, 4) }} {{ $line->baseUnit->name_ar }}</td>
                                <td class="text-center fw-mono text-primary fw-bold">{{ number_format($line->base_quantity, 4) }} {{ $line->baseUnit->name_ar }}</td>
                                <td class="text-end fw-mono">@can('costing.view'){{ number_format($line->unit_cost_purchase, 4) }} ر.س@elseسري@endcan</td>
                                <td class="text-end fw-mono fw-bold">@can('costing.view'){{ number_format($line->total_cost, 2) }} ر.س@elseسري@endcan</td>
                                <td class="text-center pe-3 fw-mono">
                                    @if($line->lot)
                                        <a href="{{ route('inventory.lots.show', $line->lot) }}" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none px-2 py-1 fs-7">
                                            {{ $line->lot->lot_code }}
                                        </a>
                                    @else
                                        <span class="text-muted fs-7">سيتم إنشاؤها عند الترحيل</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="7" class="text-end fw-bold">الإجمالي الكلي:</td>
                            <td class="text-end fw-mono fw-bold text-primary fs-6">{{ number_format($receipt->total_amount, 2) }} ر.س</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
