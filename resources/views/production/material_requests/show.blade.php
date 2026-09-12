@extends('layouts.app')

@section('title', 'تفاصيل طلب خامات الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0 text-dark">طلب خامات: {{ $materialRequest->request_number }}</h4>
                @php
                    $statusClass = match($materialRequest->status) {
                        'DRAFT' => 'bg-secondary',
                        'SUBMITTED' => 'bg-warning text-dark',
                        'PARTIALLY_FULFILLED' => 'bg-info text-dark',
                        'FULFILLED' => 'bg-success',
                        'REJECTED' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    $statusAr = match($materialRequest->status) {
                        'DRAFT' => 'مسودة',
                        'SUBMITTED' => 'مقدم للمستودع',
                        'PARTIALLY_FULFILLED' => 'صرف جزئي',
                        'FULFILLED' => 'صرف مكتمل',
                        'REJECTED' => 'مرفوض',
                        default => $materialRequest->status
                    };
                @endphp
                <span class="badge {{ $statusClass }} fs-6">{{ $statusAr }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">
                أمر إنتاج: <a href="{{ route('production.orders.show', $materialRequest->productionOrder) }}" class="fw-bold font-monospace text-primary text-decoration-none">{{ $materialRequest->productionOrder->production_order_number }}</a>
                &bull; المنتج: {{ $materialRequest->productionOrder->productModel?->name_ar ?? $materialRequest->productionOrder->custom_design_name }}
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($materialRequest->status === 'DRAFT' && auth()->user()->can('production.material_requests'))
                <form action="{{ route('production.material-requests.submit', $materialRequest) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="fas fa-paper-plane me-1"></i> تقديم للمستودع
                    </button>
                </form>
            @endif

            @if(in_array($materialRequest->status, ['SUBMITTED', 'PARTIALLY_FULFILLED']) && auth()->user()->can('inventory.issue'))
                <a href="{{ route('production.material-requests.fulfill.form', $materialRequest) }}" class="btn btn-success">
                    <i class="fas fa-dolly me-1"></i> صرف الخامات من المستودع
                </a>
            @endif

            <a href="{{ route('production.material-requests.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> عودة للطلبات
            </a>
        </div>
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
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>المادة الخام</th>
                                    <th>اللون</th>
                                    <th>الكمية المطلوبة</th>
                                    <th>الكمية المصروفة</th>
                                    <th>سبب الطلب</th>
                                    <th>حالة البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($materialRequest->lines as $line)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $line->material?->name_ar }}</div>
                                            <small class="text-muted font-monospace">{{ $line->material?->item_code }}</small>
                                        </td>
                                        <td>
                                            @if($line->fabricColor)
                                                <span class="badge bg-light text-dark border"><i class="fas fa-circle me-1" style="color: {{ $line->fabricColor->hex_code ?? '#ccc' }}"></i>{{ $line->fabricColor->color_name_ar }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-bold text-primary">{{ (float)$line->requested_quantity }} {{ $line->baseUnit?->name_ar }}</td>
                                        <td class="fw-bold {{ $line->issued_quantity >= $line->requested_quantity ? 'text-success' : 'text-warning' }}">
                                            {{ (float)$line->issued_quantity }} {{ $line->baseUnit?->name_ar }}
                                        </td>
                                        <td>
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
                                        <td>
                                            @if($line->issued_quantity >= $line->requested_quantity)
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
</div>
@endsection
