@extends('layouts.app')

@section('title', 'تفاصيل سند استلام مواد - ' . $receipt->receipt_number)

@section('page-title', 'سند استلام')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="record-header record-header-sticky mb-3">
        <a href="{{ route('inventory.receipts.index') }}" class="btn btn-light border record-header-back" aria-label="العودة لسندات الاستلام">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">سند استلام <span class="ltr-isolate">{{ $receipt->receipt_number }}</span></h1>
            <div class="fs-8 text-muted">{{ $receipt->supplier?->name }} &bull; <span class="ltr-isolate">{{ $receipt->receipt_date?->format('Y-m-d') }}</span></div>
        </div>
        <x-status-badge domain="document" :status="$receipt->status" size="lg" />
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
                        @can('costing.view')
                            <h2 class="display-6 fw-bold text-primary mb-0 fw-mono">
                                {{ number_format($receipt->total_amount, 2) }}
                            </h2>
                            <small class="text-muted">ريال سعودي</small>
                        @else
                            <h2 class="h4 fw-bold text-muted mb-0">سري</h2>
                            <small class="text-muted">القيمة متاحة لمن لديه صلاحية عرض التكاليف</small>
                        @endcan
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
                <table class="table table-hover align-middle mb-0 table-stack-sm">
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
                                <td class="ps-3 text-muted stack-hide-sm">{{ $index + 1 }}</td>
                                <td class="stack-head">
                                    <div class="min-w-0">
                                    <div class="fw-bold text-dark">{{ $line->material->name_ar }}</div>
                                    <small class="text-muted fw-mono">{{ $line->material->code }}</small>
                                    @if($line->fabric_color_code || $line->fabric_supplier_color_code || $line->fabricColor)
                                        <div class="mt-1 d-flex flex-wrap align-items-center gap-1">
                                            <span class="badge bg-light text-dark border fs-8">
                                                <i class="fas fa-palette me-1"></i>كود داخلي: <strong class="fw-mono">{{ $line->fabric_color_code ?? $line->fabricColor?->color_code ?? 'غير محدد' }}</strong>
                                                @if($line->fabricColor && $line->fabricColor->color_name_ar)
                                                    ({{ $line->fabricColor->color_name_ar }})
                                                @endif
                                            </span>
                                            @if($line->fabric_supplier_color_code)
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-8">
                                                    كود المورد: <strong class="fw-mono">{{ $line->fabric_supplier_color_code }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    </div>
                                </td>
                                <td data-label="كمية المستند" class="text-center fw-mono fw-bold stack-half">{{ number_format($line->received_quantity, 4) }}</td>
                                <td data-label="الوحدة" class="stack-half"><span class="badge bg-light text-dark border">{{ $line->purchaseUnit->name_ar }}</span></td>
                                <td data-label="معامل التحويل" class="text-center fw-mono text-muted stack-half">1 {{ $line->purchaseUnit->name_ar }} = {{ number_format($line->conversion_factor, 4) }} {{ $line->baseUnit->name_ar }}</td>
                                <td data-label="الكمية الأساسية" class="text-center fw-mono text-primary fw-bold stack-half">{{ number_format($line->base_quantity, 4) }} {{ $line->baseUnit->name_ar }}</td>
                                <td data-label="سعر الوحدة" class="text-end fw-mono stack-half">@can('costing.view'){{ number_format($line->unit_cost_purchase, 4) }} ر.س@elseسري@endcan</td>
                                <td data-label="الإجمالي" class="text-end fw-mono fw-bold stack-half">@can('costing.view'){{ number_format($line->total_cost, 2) }} ر.س@elseسري@endcan</td>
                                <td data-label="الدفعة" class="text-center pe-3 fw-mono">
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
                            <td class="text-end fw-mono fw-bold text-primary fs-6">@can('costing.view'){{ number_format($receipt->total_amount, 2) }} ر.س@elseسري@endcan</td>
                            <td class="stack-hide-sm"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($receipt->status === 'DRAFT')
        @can('inventory.receive')
            <x-mobile-action-bar class="mt-3">
                <a href="{{ route('inventory.receipts.edit', $receipt) }}" class="btn btn-outline-primary">
                    <i class="fas fa-pen" aria-hidden="true"></i> تعديل
                </a>
                <form action="{{ route('inventory.receipts.post', $receipt) }}" method="POST"
                      data-confirm="ترحيل سند الاستلام الآن؟ ستُضاف الكميات للمخزون وتُنشأ الدفعات ولا يمكن تعديل السند بعد الترحيل.">
                    @csrf
                    <button type="submit" class="btn btn-success fw-bold" @disabled($missingFabricColor ?? false)>
                        <i class="fas fa-check-circle" aria-hidden="true"></i> ترحيل سند الاستلام
                    </button>
                </form>
            </x-mobile-action-bar>
        @endcan
    @endif

</div>
@endsection
