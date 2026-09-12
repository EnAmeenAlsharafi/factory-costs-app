@extends('layouts.app')

@section('title', 'تفاصيل سند إرجاع مواد - ' . $return->return_number)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.returns.index') }}" class="text-decoration-none">سندات الإرجاع</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $return->return_number }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0 text-dark">سند إرجاع: <span class="text-primary fw-mono">{{ $return->return_number }}</span></h1>
                @if($return->status === 'POSTED')
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-check-circle me-1"></i> مُرحل ومسترد للمخزون (Posted)
                    </span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-2 fs-7">
                        <i class="fas fa-clock me-1"></i> مسودة (Draft)
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($return->status === 'DRAFT')
                @can('inventory.return')
                    <form action="{{ route('inventory.returns.post', $return) }}" method="POST" onsubmit="return confirm('هل أنت تأكد من ترحيل سند الإرجاع؟ سيتم إرجاع وزيادة كمية المواد بالدفعات المخزنية فوراً.');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i> ترحيل السند وزيادة المخزون (Post)
                        </button>
                    </form>
                @endcan
            @endif
            <a href="{{ route('inventory.returns.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- Alert Banner for Draft -->
    @if($return->status === 'DRAFT')
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-circle fs-4 me-3 text-warning"></i>
            <div>
                <strong>تنبيه:</strong> هذا السند ما زال في حالة <strong>مسودة (Draft)</strong> ولم يتم ترحيله لزيادة رصيد المخزون بعد.
            </div>
        </div>
    @endif

    <!-- Metadata Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-info-circle text-primary me-2"></i>معلومات سند الإرجاع</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">المستودع المستقبل:</span>
                            <span class="badge bg-info bg-opacity-10 text-dark border-0 fs-7 mt-1">{{ $return->warehouse->name_ar }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">القسم المرجع:</span>
                            <span class="fw-bold text-dark fs-6">{{ $return->department?->name_ar ?? 'غير محدد' }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">تاريخ الإرجاع:</span>
                            <span class="fw-bold text-dark">{{ $return->return_date->format('Y-m-d') }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">مرجع سند الصرف:</span>
                            @if($return->issue)
                                <a href="{{ route('inventory.issues.show', $return->issue) }}" class="fw-bold fw-mono text-decoration-none">
                                    {{ $return->issue->issue_number }}
                                </a>
                            @else
                                <span class="text-muted">مباشر</span>
                            @endif
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">أنشئ بواسطة:</span>
                            <span class="fw-bold text-dark">{{ $return->user->name }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">رحل بواسطة:</span>
                            <span class="fw-bold text-dark">{{ $return->postedBy?->name ?? '-' }}</span>
                        </div>

                        <div class="col-12">
                            <span class="text-muted d-block fs-7">الملاحظات:</span>
                            <span class="text-dark">{{ $return->notes ?? 'لا يوجد' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-primary bg-opacity-10 border-start border-primary border-4">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <span class="text-uppercase text-muted fw-bold fs-7 d-block mb-1">إجمالي التكلفة المرجعة</span>
                        <h2 class="display-6 fw-bold text-primary mb-0 fw-mono">
                            @can('costing.view'){{ number_format($return->total_cost, 2) }}@else<span class="fs-6 text-muted">سري</span>@endcan
                        </h2>
                        <small class="text-muted">ريال سعودي</small>
                    </div>

                    <div class="pt-3 border-top border-primary border-opacity-25 mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-7">عدد البنود:</span>
                            <span class="fw-bold fw-mono">{{ $return->lines->count() }} صنف</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-7">تاريخ التسجيل:</span>
                            <span class="fw-mono fs-7">{{ $return->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lines Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i>تفاصيل المواد والدفعات المستردة</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>المادة الخام</th>
                            <th>الدفعة الأصيلة (Lot Number)</th>
                            <th class="text-center">الكمية المرجعة</th>
                            <th>الوحدة</th>
                            <th class="text-center">الكمية الأساسية (Base)</th>
                            <th class="text-end">تكلفة الوحدة بالدفعة</th>
                            <th class="text-end pe-3">إجمالي التكلفة المرجعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($return->lines as $index => $line)
                            <tr>
                                <td class="ps-3 text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $line->material->name_ar }}</div>
                                    <small class="text-muted fw-mono">{{ $line->material->code }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('inventory.lots.show', $line->lot) }}" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none px-2 py-1 fs-7">
                                        {{ $line->lot->lot_code }}
                                    </a>
                                </td>
                                <td class="text-center fw-mono fw-bold text-success">+{{ number_format($line->returned_quantity, 4) }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $line->baseUnit->name_ar }}</span></td>
                                <td class="text-center fw-mono text-success fw-bold">+{{ number_format($line->returned_quantity, 4) }} {{ $line->material->baseUnit->name_ar }}</td>
                                <td class="text-end fw-mono">@can('costing.view'){{ number_format($line->unit_cost, 4) }} ر.س@elseسري@endcan</td>
                                <td class="text-end pe-3 fw-mono fw-bold">@can('costing.view'){{ number_format($line->total_cost, 2) }} ر.س@elseسري@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="7" class="text-end fw-bold">الإجمالي الكلي:</td>
                            <td class="text-end pe-3 fw-mono fw-bold text-primary fs-6">@can('costing.view'){{ number_format($return->total_cost, 2) }} ر.س@elseسري@endcan</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
