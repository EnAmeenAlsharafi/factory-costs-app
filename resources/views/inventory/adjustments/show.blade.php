@extends('layouts.app')

@section('title', 'تفاصيل تسوية مخزنية - ' . $adjustment->adjustment_number)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.adjustments.index') }}" class="text-decoration-none">تسويات المخزون</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $adjustment->adjustment_number }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0 text-dark">تسوية مخزنية: <span class="text-primary fw-mono">{{ $adjustment->adjustment_number }}</span></h1>
                @if($adjustment->status === 'POSTED')
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-check-circle me-1"></i> مُرحل ومطبق على المخزون (Posted)
                    </span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-clock me-1"></i> مسودة (Draft)
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($adjustment->status === 'DRAFT')
                @can('inventory.adjust')
                    <form action="{{ route('inventory.adjustments.post', $adjustment) }}" method="POST" onsubmit="return confirm('هل أنت تأكد من ترحيل هذه التسوية؟ سيتم تعديل كميات أرصدة المخزون فوراً.');">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check-circle me-1"></i> ترحيل وتطبيق التسوية (Post)
                        </button>
                    </form>
                @endcan
            @endif
            <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- Alert Banner for Draft -->
    @if($adjustment->status === 'DRAFT')
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-circle fs-4 me-3 text-warning"></i>
            <div>
                <strong>تنبيه:</strong> هذه التسوية ما زالت في حالة <strong>مسودة (Draft)</strong> ولم يتم تطبيقا على أرصدة المخزون بعد.
            </div>
        </div>
    @endif

    <!-- Metadata Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-info-circle text-primary me-2"></i>تفاصيل مستند التسوية والجرد</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted d-block fs-7">المستودع:</span>
                    <span class="badge bg-info bg-opacity-10 text-dark border-0 fs-7 mt-1">{{ $adjustment->warehouse->name_ar }}</span>
                </div>

                <div class="col-sm-6 col-md-3">
                    <span class="text-muted d-block fs-7">سبب التسوية / الجرد:</span>
                    <span class="fw-bold text-dark fs-6">{{ $adjustment->reason->name_ar }}</span>
                </div>

                <div class="col-sm-6 col-md-3">
                    <span class="text-muted d-block fs-7">تاريخ التسوية:</span>
                    <span class="fw-bold text-dark">{{ $adjustment->adjustment_date->format('Y-m-d') }}</span>
                </div>

                <div class="col-sm-6 col-md-3">
                    <span class="text-muted d-block fs-7">أنشئ بواسطة:</span>
                    <span class="fw-bold text-dark">{{ $adjustment->user->name }}</span>
                </div>

                <div class="col-sm-6 col-md-3">
                    <span class="text-muted d-block fs-7">رحل بواسطة:</span>
                    <span class="fw-bold text-dark">{{ $adjustment->postedBy?->name ?? '-' }}</span>
                </div>

                <div class="col-12 col-md-9">
                    <span class="text-muted d-block fs-7">الملاحظات والتفاصيل:</span>
                    <span class="text-dark">{{ $adjustment->notes ?? 'لا يوجد' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Lines Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-list-check text-primary me-2"></i>بنود حركة التسوية</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>نوع الحركة</th>
                            <th>المادة الخام</th>
                            <th>الدفعة المستهدفة (Lot)</th>
                            <th class="text-center">الكمية المسوية</th>
                            <th class="text-end">تكلفة الوحدة</th>
                            <th class="text-end pe-3">إجمالي القيمة المعدلة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adjustment->lines as $index => $line)
                            <tr>
                                <td class="ps-3 text-muted">{{ $index + 1 }}</td>
                                <td>
                                    @if($line->adjustment_type === 'ADJUSTMENT_IN')
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1"><i class="fas fa-plus me-1"></i> زيادة / فائض (ADJUSTMENT_IN)</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-2 py-1"><i class="fas fa-minus me-1"></i> تخفيض / عجز (ADJUSTMENT_OUT)</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $line->material->name_ar }}</div>
                                    <small class="text-muted fw-mono">{{ $line->material->code }}</small>
                                </td>
                                <td>
                                    @if($line->lot)
                                        <a href="{{ route('inventory.lots.show', $line->lot) }}" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none px-2 py-1 fs-7">
                                            {{ $line->lot->lot_code }}
                                        </a>
                                    @else
                                        <span class="text-muted fs-7">إنشاء تلقائي عند الترحيل</span>
                                    @endif
                                </td>
                                <td class="text-center fw-mono fw-bold {{ $line->adjustment_type === 'ADJUSTMENT_IN' ? 'text-success' : 'text-danger' }}">
                                    {{ $line->adjustment_type === 'ADJUSTMENT_IN' ? '+' : '-' }}{{ number_format($line->quantity, 4) }} {{ $line->material->baseUnit->name_ar }}
                                </td>
                                <td class="text-end fw-mono">@can('costing.view'){{ number_format($line->unit_cost, 4) }} ر.س@elseسري@endcan</td>
                                <td class="text-end pe-3 fw-mono fw-bold">@can('costing.view'){{ number_format($line->total_cost, 2) }} ر.س@elseسري@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
