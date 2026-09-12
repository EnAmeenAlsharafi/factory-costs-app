@extends('layouts.app')

@section('title', 'عرض سعر ' . $quotation->quotation_number)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 mb-1 text-dark fw-bold">عرض سعر: {{ $quotation->quotation_number }}</h1>
                @if($quotation->status === 'DRAFT')
                    <span class="badge bg-secondary">مسودة</span>
                @elseif($quotation->status === 'APPROVED')
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> معتمد</span>
                @elseif($quotation->status === 'REJECTED')
                    <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> مرفوض</span>
                @elseif($quotation->status === 'CONVERTED_TO_ORDER')
                    <span class="badge bg-info text-dark"><i class="fas fa-shopping-bag me-1"></i> محول لطلب</span>
                @endif
            </div>
            <p class="text-muted mb-0 fs-7">تم الإنشاء بتاريخ {{ $quotation->created_at->format('Y-m-d H:i') }} بواسطة {{ $quotation->creator?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> عروض الأسعار
            </a>

            @if($quotation->status === 'DRAFT')
                @can('quotations.edit')
                    <a href="{{ route('sales.quotations.edit', $quotation) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> تعديل
                    </a>
                @endcan

                @can('quotations.approve')
                    <form action="{{ route('sales.quotations.approve', $quotation) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success fw-semibold" onclick="return confirm('هل أنت تأكد من اعتماد عرض السعر هذا؟')">
                            <i class="fas fa-check me-1"></i> اعتماد العرض
                        </button>
                    </form>
                    <form action="{{ route('sales.quotations.reject', $quotation) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger fw-semibold" onclick="return confirm('هل أنت تأكد من رفض عرض السعر هذا؟')">
                            <i class="fas fa-times me-1"></i> رفض
                        </button>
                    </form>
                @endcan
            @endif

            @if($quotation->status === 'APPROVED')
                @can('quotations.convert')
                    <form action="{{ route('sales.quotations.convert', $quotation) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning fw-bold text-dark" onclick="return confirm('هل ترغب في تحويل عرض السعر المعتمد هذا إلى طلب عميل كلي جديد؟')">
                            <i class="fas fa-shopping-bag me-1"></i> تحويل إلى طلب عميل
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Converted Order Banner --}}
    @if($quotation->customer_order_id)
        <div class="alert alert-info border-0 shadow-sm mb-4 d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle me-2"></i>
                تم تحويل هذا العرض بنجاح إلى <strong>طلب عميل رقم {{ $quotation->convertedOrder?->order_number }}</strong>
            </div>
            <a href="{{ route('sales.orders.show', $quotation->customer_order_id) }}" class="btn btn-sm btn-info text-white fw-bold">
                عرض الطلب <i class="fas fa-arrow-left ms-1"></i>
            </a>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">العميل</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $quotation->customer?->name }}</h6>
                    <small class="text-muted">{{ $quotation->customer?->phone }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">قناة البيع</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $quotation->salesChannel?->name_ar }}</h6>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">تاريخ الإصدار / الصلاحية</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $quotation->issue_date ? $quotation->issue_date->format('Y-m-d') : '-' }}</h6>
                    <small class="text-muted">صالح حتى: {{ $quotation->valid_until ? $quotation->valid_until->format('Y-m-d') : 'غير محدد' }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-warning bg-opacity-10 border-start border-4 border-warning">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">إجمالي عرض السعر</span>
                    <h4 class="fw-bold text-dark mb-0">{{ number_format($quotation->total_amount, 2) }} <small class="fs-6">ر.س</small></h4>
                </div>
            </div>
        </div>
    </div>

    @if($quotation->notes)
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-body p-3">
                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-sticky-note text-warning me-1"></i> ملاحظات عرض السعر:</h6>
                <p class="text-muted mb-0 fs-7">{{ $quotation->notes }}</p>
            </div>
        </div>
    @endif

    {{-- Quotation Lines Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-light py-3">
            <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-list text-primary me-2"></i> بنود عرض السعر ({{ $quotation->lines->count() }})</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#</th>
                            <th>الموديل / التصميم</th>
                            <th>المقاس المطلوب (عرض × طول)</th>
                            <th class="text-center">الكمية</th>
                            <th class="text-end">سعر الوحدة</th>
                            <th class="text-end pe-3">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotation->lines as $index => $line)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $index + 1 }}</td>
                                <td>
                                    @if($line->custom_design)
                                        <span class="badge bg-purple text-white mb-1" style="background-color: #6f42c1;">تصميم خاص</span>
                                        <div class="fw-bold text-dark">{{ $line->custom_design_name ?? 'تصميم خاص بدون اسم' }}</div>
                                    @else
                                        <div class="fw-bold text-dark">{{ $line->productModel?->name_ar ?? 'موديل غير حدد' }}</div>
                                        <small class="text-muted">كود الموديل: {{ $line->productModel?->model_code }}</small>
                                    @endif

                                    @if($line->notes)
                                        <div class="fs-8 text-muted mt-1"><i class="fas fa-info-circle me-1"></i> {{ $line->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($line->productConfiguration)
                                        <div class="fw-semibold text-dark">
                                            {{ $line->productConfiguration->width_cm }} × {{ $line->productConfiguration->length_cm }} سم
                                        </div>
                                    @elseif($line->requested_width_cm && $line->requested_length_cm)
                                        <div class="fw-semibold text-dark">
                                            {{ $line->requested_width_cm }} × {{ $line->requested_length_cm }} سم (مخصص)
                                        </div>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-dark fs-6">{{ $line->quantity }}</td>
                                <td class="text-end font-monospace text-dark">{{ number_format($line->unit_price, 2) }} ر.س</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-primary fs-6">{{ number_format($line->total_price, 2) }} ر.س</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end fw-bold fs-6">الإجمالي الكلي:</td>
                            <td class="text-end pe-3 fw-bold text-primary fs-5 font-monospace">
                                {{ number_format($quotation->total_amount, 2) }} ر.س
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
