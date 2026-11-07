@extends('layouts.app')

@section('title', 'معاينة استيراد طلبات سلة')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="page-header-card mb-4">
        <a href="{{ route('sales.imports.index') }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل الاستيراد
        </a>
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">معاينة ملف: {{ $batch->file_name }}</h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">هذه معاينة فقط، لم يتم إنشاء أي طلب أو عميل حتى الآن. يُعاد حساب المطابقة والمخزون حياً عند كل فتح لهذه الصفحة.</p>
    </div>

    @php
        $importCount = collect($preview)->where('decision', 'IMPORT')->count();
        $skipCount = collect($preview)->where('decision', 'SKIP')->count();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted fs-7">طلبات سيتم استيرادها</span>
                    <h3 class="fw-bold text-success mb-0">{{ $importCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <span class="text-muted fs-7">طلبات سيتم تجاهلها</span>
                    <h3 class="fw-bold text-secondary mb-0">{{ $skipCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 d-flex align-items-center justify-content-end">
            @can('sales_imports.manage')
                @if($importCount > 0)
                    <form action="{{ route('sales.imports.confirm', $batch) }}" method="POST" onsubmit="return confirm('سيتم إنشاء {{ $importCount }} طلب عميل فعلياً. هل تريد المتابعة؟');">
                        @csrf
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="fas fa-check-circle me-1"></i> تأكيد الاستيراد
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @foreach($preview as $row)
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold text-dark">{{ $row['external_order_reference'] }}</span>
                    <span class="text-muted fs-8 ms-2">
                        العميل:
                        @if($row['customer']['matched'])
                            <span class="text-success">{{ $row['customer']['name'] }} (عميل موجود)</span>
                        @else
                            <span class="text-primary">{{ $row['customer']['name'] }} (عميل جديد)</span>
                        @endif
                    </span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if(!empty($row['warnings']))
                        <span class="badge bg-warning bg-opacity-25 text-warning-emphasis fw-bold" title="{{ implode(' | ', $row['warnings']) }}">
                            <i class="fas fa-exclamation-triangle me-1"></i> {{ count($row['warnings']) }} تحذير
                        </span>
                    @endif
                    @if($row['decision'] === 'IMPORT')
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">سيتم الاستيراد</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1" title="{{ $row['skip_reason'] }}">تجاهل: {{ $row['skip_reason'] }}</span>
                    @endif
                </div>
            </div>

            @if(!empty($row['warnings']))
                <div class="px-3 pt-2">
                    @foreach($row['warnings'] as $warning)
                        <div class="text-warning-emphasis fs-8"><i class="fas fa-exclamation-triangle me-1"></i>{{ $warning }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>المنتج</th>
                                <th>SKU</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-end">سعر البيع للوحدة</th>
                                <th>النوع المطابَق</th>
                                <th>التوفر / المكونات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($row['lines'] as $line)
                                <tr>
                                    <td>{{ $line['product_name'] }}</td>
                                    <td class="fw-mono">{{ $line['sku'] ?: '-' }}</td>
                                    <td class="text-center">{{ $line['quantity'] }}</td>
                                    <td class="text-end">{{ number_format($line['unit_price'], 2) }} ر.س</td>
                                    <td>
                                        @if($line['target_type'] === 'READY_STOCK')
                                            <span class="badge bg-success bg-opacity-10 text-success">منتج جاهز</span>
                                        @elseif($line['target_type'] === 'MANUAL')
                                            <span class="badge bg-warning text-dark">يُضاف يدويًا بعد الاستيراد</span>
                                        @elseif($line['target_type'] === 'BUNDLE')
                                            <span class="badge bg-info bg-opacity-10 text-info">بكج</span>
                                        @elseif($line['target_type'] === 'MANUFACTURED')
                                            <span class="badge bg-primary bg-opacity-10 text-primary">سرير مصنّع</span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger">غير مطابَق</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($line['ready_stock_availability'])
                                            <span class="{{ $line['ready_stock_availability']['available'] > 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                                متاح: {{ number_format($line['ready_stock_availability']['available'], 2) }}
                                            </span>
                                        @elseif($line['bundle_components'])
                                            <span class="text-muted fs-8">{{ count($line['bundle_components']) }} مكوّن</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

</div>
@endsection
