@extends('layouts.app')

@section('title', 'حالات الجودة والعيوب - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">سجل حالات الجودة والعيوب (Quality Incidents Log)</h4>
            <p class="text-muted mb-0 mt-1">تسجيل وتوثيق عيوب الإنتاج، وتحديد الأقسام المسؤولة، وقرارات الإصلاح وإعادة التصنيع</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.orders.index') }}" class="btn btn-danger">
                <i class="fas fa-exclamation-circle me-1"></i> تسجيل بلاغ جودة جديد
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('production.quality-incidents.index') }}" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="بحث برقم البلاغ، السبب، أو أمر الإنتاج..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="OPEN" {{ request('status') === 'OPEN' ? 'selected' : '' }}>مفتوح (OPEN)</option>
                        <option value="ACTION_REQUIRED" {{ request('status') === 'ACTION_REQUIRED' ? 'selected' : '' }}>يتطلب إجراء (ACTION REQUIRED)</option>
                        <option value="RESOLVED" {{ request('status') === 'RESOLVED' ? 'selected' : '' }}>تمت المعالجة (RESOLVED)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="severity" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع درجات الأهمية</option>
                        <option value="LOW" {{ request('severity') === 'LOW' ? 'selected' : '' }}>منخفضة (LOW)</option>
                        <option value="MEDIUM" {{ request('severity') === 'MEDIUM' ? 'selected' : '' }}>متوسطة (MEDIUM)</option>
                        <option value="HIGH" {{ request('severity') === 'HIGH' ? 'selected' : '' }}>عالية (HIGH)</option>
                        <option value="CRITICAL" {{ request('severity') === 'CRITICAL' ? 'selected' : '' }}>حرجة (CRITICAL)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    @if(request()->anyFilled(['search', 'status', 'severity']))
                        <a href="{{ route('production.quality-incidents.index') }}" class="btn btn-outline-secondary" title="إلغاء التصفية"><i class="fas fa-times"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Incident Log Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم البلاغ</th>
                            <th>أمر الإنتاج</th>
                            <th>نوع العيب</th>
                            <th>الأهمية</th>
                            <th>قسم الاكتشاف</th>
                            <th>القسم المسؤول</th>
                            <th>قرار المعالجة</th>
                            <th>الحالة</th>
                            <th class="text-end">التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incidents as $inc)
                            <tr>
                                <td>
                                    <a href="{{ route('production.quality-incidents.show', $inc) }}" class="fw-bold font-monospace text-danger text-decoration-none">
                                        {{ $inc->incident_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('production.orders.show', $inc->productionOrder) }}" class="font-monospace text-dark text-decoration-none">
                                        {{ $inc->productionOrder?->production_order_number }}
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $inc->incident_type }}</span></td>
                                <td>
                                    @php
                                        $sevBadge = match($inc->severity) {
                                            'LOW' => 'bg-info text-dark',
                                            'MEDIUM' => 'bg-warning text-dark',
                                            'HIGH' => 'bg-danger',
                                            'CRITICAL' => 'bg-dark text-white',
                                            default => 'bg-secondary'
                                        };
                                    @endphp
                                    <span class="badge {{ $sevBadge }}">{{ $inc->severity }}</span>
                                </td>
                                <td>{{ $inc->detectedDepartment?->name_ar }}</td>
                                <td><strong class="text-dark">{{ $inc->responsibleDepartment?->name_ar ?? 'غير محدد بعد' }}</strong></td>
                                <td>
                                    @if($inc->disposition)
                                        <span class="badge bg-primary fs-7">{{ $inc->disposition }}</span>
                                    @else
                                        <span class="text-muted small">قيد المعاينة</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusBadge = match($inc->status) {
                                            'OPEN' => 'bg-secondary',
                                            'ACTION_REQUIRED' => 'bg-warning text-dark',
                                            'RESOLVED' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                        $statusAr = match($inc->status) {
                                            'OPEN' => 'مفتوح',
                                            'ACTION_REQUIRED' => 'يتطلب إجراء',
                                            'RESOLVED' => 'تمت المعالجة',
                                            default => $inc->status
                                        };
                                    @endphp
                                    <span class="badge {{ $statusBadge }}">{{ $statusAr }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.quality-incidents.show', $inc) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> التفاصيل والقرار
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                                    لا توجد بلاغات حالات جودة أو عيوب مسجلة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($incidents->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
