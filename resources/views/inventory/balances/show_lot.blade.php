@extends('layouts.app')

@section('title', 'تفاصيل الدفعة المخزنية - ' . $lot->lot_code)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.balances.index') }}" class="text-decoration-none">أرصدة المخزون والدفعات</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $lot->lot_code }}</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">دفعة مخزنية: <span class="text-primary fw-mono">{{ $lot->lot_code }}</span></h1>
        </div>

        <div>
            <a href="{{ route('inventory.balances.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للأرصدة
            </a>
        </div>
    </div>

    <!-- Lot Details Summary Card -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-info-circle text-primary me-2"></i>بيانات الدفعة الأساسية</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">المادة الخام:</span>
                            <span class="fw-bold text-dark fs-6">{{ $lot->material->name_ar }}</span>
                            <small class="text-muted fw-mono d-block">({{ $lot->material->code }})</small>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">المستودع:</span>
                            <span class="badge bg-info bg-opacity-10 text-dark fs-7 mt-1">{{ $lot->warehouse->name_ar }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">تاريخ الورود:</span>
                            <span class="fw-bold text-dark">{{ $lot->received_date->format('Y-m-d') }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">المورد:</span>
                            <span class="fw-bold text-dark">{{ $lot->supplier?->name ?? 'غير محدد' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">سند الاستلام:</span>
                            @if($lot->receipt)
                                <a href="{{ route('inventory.receipts.show', $lot->receipt) }}" class="fw-bold fw-mono text-decoration-none">
                                    {{ $lot->receipt->receipt_number }}
                                </a>
                            @else
                                <span class="text-muted">افتتاحي / مباشر</span>
                            @endif
                        </div>
                        @if($lot->fabric_color_code || $lot->fabricColor || (strtoupper($lot->material?->category?->code ?? '') === 'FABRIC' || str_contains($lot->material?->name_ar ?? '', 'قماش')))
                            <div class="col-sm-6">
                                <span class="text-muted d-block fs-7">رقم / كود اللون:</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-7 mt-1 fw-mono">
                                    <i class="fas fa-palette me-1"></i>{{ $lot->fabric_color_code ?? $lot->fabricColor?->color_code ?? 'غير محدد' }}
                                    @if($lot->fabricColor && $lot->fabricColor->color_name_ar)
                                        ({{ $lot->fabricColor->color_name_ar }})
                                    @endif
                                </span>
                            </div>
                        @endif
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-7">الملاحظات:</span>
                            <span class="text-dark">{{ $lot->notes ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-primary bg-opacity-10 border-start border-primary border-4">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <span class="text-uppercase text-muted fw-bold fs-7 d-block mb-1">الرصيد المتاح للدفعة</span>
                        <h2 class="display-6 fw-bold text-primary mb-2 fw-mono">
                            {{ number_format($lot->remaining_quantity, 2) }}
                        </h2>
                        <span class="badge bg-primary text-white fs-7">{{ $lot->material->baseUnit->name_ar }}</span>
                    </div>

                    <div class="pt-3 border-top border-primary border-opacity-25 mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-7">الكمية الأولية المستلمة:</span>
                            <span class="fw-mono fw-bold">{{ number_format($lot->original_quantity, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-7">تكلفة الوحدة عند الورود:</span>
                            <span class="fw-mono fw-bold">@can('costing.view'){{ number_format($lot->unit_cost, 2) }} ر.س@elseسري@endcan</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-7">القيمة المتبقية الحالية:</span>
                            <span class="fw-mono fw-bold text-success">@can('costing.view'){{ number_format($lot->remaining_quantity * $lot->unit_cost, 2) }} ر.س@elseسري@endcan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Movements Ledger for this Lot -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="card-title fw-bold mb-0 text-dark">
                <i class="fas fa-history text-secondary me-2"></i>سجل الحركات التفصيلي لهذه الدفعة (Ledger)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">رقم الحركة (Movement)</th>
                            <th>التاريخ والوقت</th>
                            <th>نوع الحركة</th>
                            <th>الاتجاه</th>
                            <th class="text-center">الكمية (بالوحدة الأساسية)</th>
                            <th class="text-end">تكلفة الوحدة</th>
                            <th class="text-end pe-3">إجمالي التكلفة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lot->movements as $movement)
                            <tr>
                                <td class="ps-3 fw-mono text-muted">{{ $movement->movement_number }}</td>
                                <td class="fw-mono fs-7">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold">
                                        {{ $movement->movement_type }}
                                    </span>
                                </td>
                                <td>
                                    @if($movement->direction === 'IN')
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold"><i class="fas fa-arrow-down me-1"></i> وارد (IN)</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold"><i class="fas fa-arrow-up me-1"></i> منصرف (OUT)</span>
                                    @endif
                                </td>
                                <td class="text-center fw-mono fw-bold {{ $movement->direction === 'IN' ? 'text-success' : 'text-danger' }}">
                                    {{ $movement->direction === 'IN' ? '+' : '-' }}{{ number_format($movement->quantity, 2) }} {{ $lot->material->baseUnit->name_ar }}
                                </td>
                                <td class="text-end fw-mono">@can('costing.view'){{ number_format($movement->unit_cost, 2) }} ر.س@elseسري@endcan</td>
                                <td class="text-end pe-3 fw-mono fw-bold">@can('costing.view'){{ number_format($movement->total_cost, 2) }} ر.س@elseسري@endcan</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">لا توجد حركات مسجلة بهذه الدفعة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
