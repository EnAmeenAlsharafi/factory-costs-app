@extends('layouts.app')

@section('title', 'تفاصيل طلب خامات الإنتاج - مصنع مفروشات سدير')

@section('page-title', 'طلب مواد')

@section('content')
@php
    $canSubmitRequest = $materialRequest->status === 'DRAFT' && auth()->user()->can('production.material_requests');
    $canIssue = in_array($materialRequest->status, ['SUBMITTED', 'PARTIALLY_FULFILLED'], true) && auth()->user()->can('inventory.issue');
@endphp
<div class="container-fluid px-4 py-3">
    <div class="record-header record-header-sticky mb-3">
        <a href="{{ route('production.material-requests.index') }}" class="btn btn-light border record-header-back" aria-label="عودة للطلبات">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">
                {{ $materialRequest->productionOrder->productModel?->name_ar ?? $materialRequest->productionOrder->custom_design_name ?? 'طلب مواد' }}
            </h1>
            <div class="fs-8 text-muted">
                طلب <span class="ltr-isolate">{{ $materialRequest->request_number }}</span> &bull; أمر إنتاج
                @can('production.view')
                    <a href="{{ route('production.orders.show', $materialRequest->productionOrder) }}" class="ltr-isolate">{{ $materialRequest->productionOrder->production_order_number }}</a>
                @else
                    <span class="ltr-isolate">{{ $materialRequest->productionOrder->production_order_number }}</span>
                @endcan
            </div>
        </div>
        <x-status-badge domain="material_request" :status="$materialRequest->status" size="lg" />
    </div>

    <!-- Info Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-1"></i> بيانات الطلب الأساسية</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">مُنشئ الطلب:</td>
                            <td class="fw-bold">{{ $materialRequest->requestedByUser?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">القسم الطالب:</td>
                            <td>{{ $materialRequest->requestedFromDepartment?->name_ar ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المستودع المستهدف:</td>
                            <td><span class="badge bg-light text-dark border">{{ $materialRequest->warehouse?->name_ar }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">تاريخ الطلب:</td>
                            <td>{{ $materialRequest->request_date?->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">ملاحظات:</td>
                            <td class="small text-muted">{{ $materialRequest->notes ?? 'لا توجد ملاحظات' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-boxes-stacked me-1"></i> بنود الخامات المطلوبة والمصروفة</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-stack-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>المادة الخام</th>
                                    <th>اللون</th>
                                    <th>المطلوب</th>
                                    <th>المعتمد</th>
                                    <th>المصروف</th>
                                    <th>المتبقي</th>
                                    <th>سبب الطلب</th>
                                    <th>حالة البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($materialRequest->lines as $line)
                                    @php
                                        $lineTarget = (float) ($line->approved_quantity ?? $line->requested_quantity);
                                        $lineRemaining = max(0, $lineTarget - (float) $line->issued_quantity);
                                        $lineColorCode = $line->fabric_color_code ?? $line->fabricColor?->color_code;
                                    @endphp
                                    <tr>
                                        <td class="stack-head">
                                            <div class="min-w-0">
                                                <div class="fw-bold">{{ $line->material?->name_ar }}</div>
                                                <small class="text-muted font-monospace">{{ $line->material?->code }}</small>
                                            </div>
                                        </td>
                                        <td data-label="اللون" class="stack-half">
                                            @if($lineColorCode)
                                                <span class="badge bg-light text-dark border"><span class="color-dot me-1" style="background: {{ $line->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span><span class="ltr-isolate">{{ $lineColorCode }}</span> {{ $line->fabricColor?->color_name_ar }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-label="المطلوب" class="fw-bold text-primary stack-half">{{ (float)$line->requested_quantity }} {{ $line->baseUnit?->name_ar }}</td>
                                        <td data-label="المعتمد" class="stack-half">{{ $line->approved_quantity !== null ? (float) $line->approved_quantity : '—' }}</td>
                                        <td data-label="المصروف" class="fw-bold stack-half {{ $lineRemaining <= 0 ? 'text-success' : 'text-warning-emphasis' }}">
                                            {{ (float)$line->issued_quantity }} {{ $line->baseUnit?->name_ar }}
                                        </td>
                                        <td data-label="المتبقي" class="fw-bold stack-half">{{ $lineRemaining }} {{ $line->baseUnit?->name_ar }}</td>
                                        <td data-label="سبب الطلب" class="stack-half">
                                            <span class="badge bg-light text-dark border fs-8">
                                                {{ match($line->request_reason) {
                                                    'PLANNED_PRODUCTION' => 'إنتاج مخطط',
                                                    'ADDITIONAL_REQUIREMENT' => 'احتياج إضافي',
                                                    'REWORK' => 'إعادة تصنيع',
                                                    'REMANUFACTURE' => 'إعادة إنتاج',
                                                    'CORRECTION' => 'تصحيح',
                                                    default => $line->request_reason
                                                } }}
                                            </span>
                                        </td>
                                        <td data-label="حالة البند" class="stack-half">
                                            @if($lineRemaining <= 0)
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>مكتمل</span>
                                            @elseif($line->issued_quantity > 0)
                                                <span class="badge bg-info text-dark">جزئي</span>
                                            @else
                                                <span class="badge bg-secondary">معلق</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Issue Receipts Associated -->
    @if($materialRequest->materialIssues->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-file-invoice-dollar me-1"></i> سندات الصرف المخزني المرتبطة بهذا الطلب</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>رقم سند الصرف</th>
                                <th>تاريخ الصرف</th>
                                <th>بواسطة</th>
                                <th>الحالة</th>
                                <th class="text-end">التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materialRequest->materialIssues as $issue)
                                <tr>
                                    <td>
                                        <a href="{{ route('inventory.issues.show', $issue) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                            {{ $issue->issue_number }}
                                        </a>
                                    </td>
                                    <td>{{ $issue->issue_date?->format('Y-m-d') }}</td>
                                    <td>{{ $issue->issuedByUser?->name }}</td>
                                    <td><span class="badge bg-success">معتمد ومرحل (POSTED)</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('inventory.issues.show', $issue) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i> عرض السند</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($canSubmitRequest || $canIssue)
        <x-mobile-action-bar class="mt-3">
            @if ($canSubmitRequest)
                <form action="{{ route('production.material-requests.submit', $materialRequest) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> تقديم للمستودع
                    </button>
                </form>
            @endif
            @if ($canIssue)
                <a href="{{ route('production.material-requests.fulfill.form', $materialRequest) }}" class="btn btn-success">
                    <i class="fas fa-dolly" aria-hidden="true"></i> صرف
                </a>
            @endif
        </x-mobile-action-bar>
    @endif
</div>
@endsection
