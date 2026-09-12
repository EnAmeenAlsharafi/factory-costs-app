@extends('layouts.app')

@section('title', 'تفاصيل مسار التصنيع - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0 text-dark">{{ $routing->name_ar }}</h4>
                <span class="badge bg-primary font-monospace fs-7">{{ $routing->routing_code }}</span>
                @if($routing->is_active)
                    <span class="badge bg-success">نشط</span>
                @else
                    <span class="badge bg-danger">معطل</span>
                @endif
            </div>
            <p class="text-muted mb-0 mt-1">{{ $routing->description ?? 'لا يوجد وصف مضاف لهذا المسار' }}</p>
        </div>
        <a href="{{ route('production.routings.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للمسارات
        </a>
    </div>

    <!-- Operations Diagram / Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-sitemap me-1"></i> هيكل العمليات والفرعية لمسار التصنيع</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 80px;">التسلسل</th>
                            <th>كود العملية</th>
                            <th>اسم العملية والورشة</th>
                            <th>الفرع التشغيلي</th>
                            <th>الاعتمادية (يعتمد على)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routing->operations as $op)
                            <tr>
                                <td class="fw-bold text-center text-primary">{{ $op->sequence_number }}</td>
                                <td><span class="font-monospace fw-bold text-secondary">{{ $op->operation_code }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $op->name_ar }}</div>
                                    <small class="text-muted"><i class="fas fa-building me-1"></i>{{ $op->workCenter?->name_ar }} ({{ $op->workCenter?->department?->name_ar }})</small>
                                </td>
                                <td>
                                    @if($op->branch_key === 'BRANCH_A_HEADBOARD' || $op->branch_key === 'BRANCH_A')
                                        <span class="badge bg-info text-dark"><i class="fas fa-code-branch me-1"></i>فرع الظهر والجانبيات</span>
                                    @elseif($op->branch_key === 'BRANCH_B_BOX' || $op->branch_key === 'BRANCH_B')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-box me-1"></i>فرع البوكسات</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-layer-group me-1"></i>الخط الرئيسي</span>
                                    @endif
                                </td>
                                <td>
                                    @if($op->dependencies->isNotEmpty())
                                        @foreach($op->dependencies as $dep)
                                            <span class="badge bg-light text-dark border me-1"><i class="fas fa-long-arrow-alt-right me-1"></i>{{ $dep->name_ar }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">مرحلة بداية (لا توجد اعتمادية)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">لا توجد عمليات في هذا المسار.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
