@extends('layouts.app')

@section('title', 'تقرير أعمار الديون والمتأخرات')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-history text-warning me-2"></i> تقرير أعمار الديون والمتأخرات التشغيلية (Aging Report)
            </h1>
            <p class="text-muted mb-0 small">تحليل الذمم التجارية القائمة حسب فترات استحقاق السداد.</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-dark fw-bold px-3">
            <i class="fas fa-print me-1"></i> طباعة التقرير
        </button>
    </div>

    {{-- Bucket Cards --}}
    <div class="row g-3 mb-4">
        @foreach ($agingBuckets as $key => $bucket)
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 {{ $key === 'current' ? 'border-success' : ($key === '1_30' ? 'border-warning' : 'border-danger') }}">
                    <div class="card-body p-3">
                        <div class="text-muted small fw-bold mb-1">{{ $bucket['label'] }}</div>
                        <h4 class="fw-bold mb-1 {{ $key === 'current' ? 'text-success' : ($key === '1_30' ? 'text-dark' : 'text-danger') }}">
                            {{ number_format($bucket['amount'], 2) }} <small class="fs-6 text-muted">ر.س</small>
                        </h4>
                        <div class="small text-muted">{{ $bucket['count'] }} طلبات مستحقة</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Detailed Tables Per Bucket --}}
    @foreach ($agingBuckets as $key => $bucket)
        @if ($bucket['count'] > 0)
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <span class="badge {{ $key === 'current' ? 'bg-success' : ($key === '1_30' ? 'bg-warning text-dark' : 'bg-danger') }} me-2">
                            {{ $bucket['label'] }}
                        </span>
                        طلبات مستحقة: {{ number_format($bucket['amount'], 2) }} ر.س
                    </h5>
                    <span class="text-muted small">{{ $bucket['count'] }} طلبات</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-3">رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>تاريخ الاستحقاق</th>
                                    <th class="text-end">قيمة الطلب</th>
                                    <th class="text-end">المسدد</th>
                                    <th class="text-end">المتبقي القائم</th>
                                    <th class="text-end pe-3">معاينة الطلب</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bucket['orders'] as $ord)
                                    <tr>
                                        <td class="ps-3 py-3 font-monospace fw-bold">
                                            <a href="{{ route('sales.orders.show', $ord) }}" class="text-decoration-none text-dark">
                                                {{ $ord->order_number }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $ord->customer->name ?? '-' }}</div>
                                            <small class="text-muted">{{ $ord->customer->customer_code ?? '' }}</small>
                                        </td>
                                        <td class="font-monospace small">
                                            {{ $ord->payment_due_date ? $ord->payment_due_date->format('Y-m-d') : 'غير محدد' }}
                                        </td>
                                        <td class="text-end fw-bold text-dark">{{ number_format($ord->total_amount, 2) }} ر.س</td>
                                        <td class="text-end text-success fw-bold">{{ number_format($ord->confirmed_paid_amount, 2) }} ر.س</td>
                                        <td class="text-end text-danger fw-bold fs-6">{{ number_format($ord->outstanding_balance, 2) }} ر.س</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('sales.orders.show', $ord) }}" class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection
