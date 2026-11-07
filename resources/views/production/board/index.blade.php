@extends('layouts.app')

@section('title', 'لوحة متابعة الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="page-header-card d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-chart-kanban text-warning fs-5" aria-hidden="true"></i>لوحة متابعة الإنتاج والورش</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">متابعة شاملة لتقدم العمليات التشغيلية بالأقسام وتحليل اختناقات الورش</p>
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
                        <small class="text-muted d-block">أوامر الإنتاج المعلقة</small>
                        <span class="fs-3 fw-bold text-dark">{{ $heldOrdersCount }}</span>
                    </div>
                    <div class="bg-dark text-white rounded-circle p-3 fs-5">
                        <i class="fas fa-pause"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attention Counts (spec §129): the headline numbers the board exists to surface -->
    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-triangle-exclamation me-1 text-warning"></i> يحتاج اهتماماً الآن</h6>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2 col-md-4">
            <a href="{{ route('production.assignment.index', ['unassigned_only' => 1]) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $attention['ready_unassigned'] > 0 ? 'border-start border-info border-4' : '' }}">
                <div class="card-body p-3">
                    <small class="text-muted d-block">جاهز وغير مُسند</small>
                    <span class="fs-4 fw-bold text-info">{{ $attention['ready_unassigned'] }}</span>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-2 col-md-4">
            <a href="{{ route('production.queue.index', ['tab' => 'in_progress']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body p-3">
                    <small class="text-muted d-block">قيد التنفيذ</small>
                    <span class="fs-4 fw-bold text-warning">{{ $attention['in_progress'] }}</span>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card border-0 shadow-sm h-100 {{ $attention['blocked'] > 0 ? 'border-start border-danger border-4' : '' }}">
                <div class="card-body p-3">
                    <small class="text-muted d-block">متوقف بعائق</small>
                    <span class="fs-4 fw-bold text-danger">{{ $attention['blocked'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card border-0 shadow-sm h-100 {{ $attention['delayed'] > 0 ? 'border-start border-warning border-4' : '' }}">
                <div class="card-body p-3">
                    <small class="text-muted d-block">متأخر عن الموعد المخطط</small>
                    <span class="fs-4 fw-bold text-warning">{{ $attention['delayed'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4">
            <a href="{{ route('production.rework.index') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body p-3">
                    <small class="text-muted d-block">إعادة عمل مفتوحة</small>
                    <span class="fs-4 fw-bold text-dark">{{ $attention['open_rework'] }}</span>
                </div>
            </a>
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
                        <div class="d-flex flex-wrap justify-content-between text-muted fs-8 mb-2 gap-1">
                            <span>جاهز: <strong class="text-info">{{ $stat['ready_count'] }}</strong></span>
                            <span>قيد العمل: <strong class="text-warning">{{ $stat['in_progress_count'] }}</strong></span>
                            <span>مكتمل: <strong class="text-success">{{ $stat['completed_count'] }}</strong></span>
                            @if ($stat['blocked_count'] > 0)
                                <span>متوقف: <strong class="text-danger">{{ $stat['blocked_count'] }}</strong></span>
                            @endif
                            @if ($stat['delayed_count'] > 0)
                                <span>متأخر: <strong class="text-warning">{{ $stat['delayed_count'] }}</strong></span>
                            @endif
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

    <!-- Production Control Board Matrix: active orders × department stages (spec §39-40, §71, §129) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-table-cells me-1"></i> مصفوفة متابعة أوامر الإنتاج النشطة</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">أمر الإنتاج / العميل</th>
                            @foreach ($departments as $dept)
                                <th class="text-center">{{ $dept->name_ar }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrixOrders as $mPo)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('production.orders.show', $mPo) }}" class="fw-bold text-decoration-none d-block">{{ $mPo->production_order_number }}</a>
                                    <small class="text-muted">{{ $mPo->customerOrder?->customer?->name }}</small>
                                </td>
                                @foreach ($departments as $dept)
                                    @php
                                        $deptOp = $mPo->operations->first(fn ($op) => $op->workCenter?->department_id === $dept->id);
                                    @endphp
                                    <td class="text-center">
                                        @if ($deptOp)
                                            <x-status-badge domain="operation" :status="$deptOp->status" />
                                            @if ($deptOp->status === 'BLOCKED' && $deptOp->activeBlock)
                                                <div class="fs-8 text-danger mt-1">{{ $deptOp->activeBlock->reason_arabic }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $departments->count() + 1 }}" class="text-center py-3 text-muted">لا توجد أوامر إنتاج نشطة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($matrixOrders->hasPages())
            <div class="card-footer bg-white d-flex justify-content-center">{{ $matrixOrders->links() }}</div>
        @endif
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
                                <td class="fw-semibold">{{ $lPo->customerOrder?->customer?->name }}</td>
                                <td>{{ $lPo->is_custom_design ? $lPo->custom_design_name : $lPo->productModel?->name_ar }} ({{ (int)$lPo->requested_width_cm }}×{{ (int)$lPo->requested_length_cm }})</td>
                                <td class="text-center fw-bold">
                                    <span class="text-success fs-6">{{ $lPo->completed_quantity }}</span> / {{ $lPo->released_quantity }}
                                </td>
                                <td>
                                    <span class="badge {{ $lPo->status_badge_class }}">{{ $lPo->status_arabic }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.orders.show', $lPo) }}" class="btn btn-sm btn-outline-primary" aria-label="عرض أمر الإنتاج" title="عرض أمر الإنتاج"><i class="fas fa-eye" aria-hidden="true"></i></a>
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
