@extends('layouts.app')

@section('title', 'لوحة متابعة الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-kanban text-primary me-2"></i>لوحة متابعة الإنتاج والورش (Production Board)</h4>
            <p class="text-muted mb-0">متابعة شاملة لتقدم العمليات التشغيلية بالأقسام وتحليل اختناقات الورش</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.orders.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-industry me-1"></i> أوامر الإنتاج
            </a>
            <a href="{{ route('production.queue.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-tasks me-1"></i> أعمال الأقسام
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-primary border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block">أوامر إنتاج قيد التنفيذ (WIP)</small>
                        <span class="fs-3 fw-bold text-primary">{{ $activeOrdersCount }}</span>
                    </div>
                    <div class="bg-primary text-white rounded-circle p-3 fs-5">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-success border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block">أوامر الإنتاج المكتملة بالكامل</small>
                        <span class="fs-3 fw-bold text-success">{{ $completedOrdersCount }}</span>
                    </div>
                    <div class="bg-success text-white rounded-circle p-3 fs-5">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-dark border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block">أوامر الإنتاج المعلقة (On Hold)</small>
                        <span class="fs-3 fw-bold text-dark">{{ $heldOrdersCount }}</span>
                    </div>
                    <div class="bg-dark text-white rounded-circle p-3 fs-5">
                        <i class="fas fa-pause"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Departments Summary Progress Grid -->
    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-building me-1"></i> ملخص خطوط الإنتاج حسب الأقسام</h6>
    <div class="row g-3 mb-4">
        @foreach($departmentStats as $deptId => $stat)
            <div class="col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-microchip text-primary me-1"></i> {{ $stat['department']->name_ar }}</h6>
                        <span class="badge bg-light text-dark border">{{ $stat['total_completed'] }} / {{ $stat['total_required'] }} قطعة</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between text-muted fs-7 mb-2">
                            <span>جاهز: <strong class="text-info">{{ $stat['ready_count'] }}</strong></span>
                            <span>قيد العمل: <strong class="text-warning">{{ $stat['in_progress_count'] }}</strong></span>
                            <span>مكتمل: <strong class="text-success">{{ $stat['completed_count'] }}</strong></span>
                        </div>
                        @php
                            $dPct = $stat['total_required'] > 0 ? round(($stat['total_completed'] / $stat['total_required']) * 100) : 0;
                        @endphp
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: {{ $dPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Latest Active Production Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-stream me-1"></i> أحدث أوامر الإنتاج بالورشة</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-7">
                    <thead class="bg-light">
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>العميل</th>
                            <th>المنتج والمقاس</th>
                            <th class="text-center">الكمية المطلوبة / المنجزة</th>
                            <th>الحالة</th>
                            <th class="text-end">التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestOrders as $lPo)
                            <tr>
                                <td>
                                    <a href="{{ route('production.orders.show', $lPo) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                        {{ $lPo->production_order_number }}
                                    </a>
                                </td>
                                <td class="fw-semibold">{{ $lPo->customerOrder?->customer?->name_ar }}</td>
                                <td>{{ $lPo->is_custom_design ? $lPo->custom_design_name : $lPo->productModel?->name_ar }} ({{ (int)$lPo->requested_width_cm }}×{{ (int)$lPo->requested_length_cm }})</td>
                                <td class="text-center fw-bold">
                                    <span class="text-success fs-6">{{ $lPo->completed_quantity }}</span> / {{ $lPo->released_quantity }}
                                </td>
                                <td>
                                    <span class="badge {{ $lPo->status_badge_class }}">{{ $lPo->status_arabic }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.orders.show', $lPo) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">لا توجد أوامر إنتاج نشطة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
