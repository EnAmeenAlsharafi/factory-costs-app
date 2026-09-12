@extends('layouts.app')

@section('title', 'ملخص احصائيات الجودة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">ملخص وإحصائيات حالات الجودة (Quality Summary Report)</h4>
            <p class="text-muted mb-0 mt-1">متابعة الأقسام والقرارات وخطورة عيوب التصنيع</p>
        </div>
        <a href="{{ route('production.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للتقارير
        </a>
    </div>

    <!-- Cards Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <small class="text-muted d-block mb-1">إجمالي البلاغات المسجلة</small>
                    <span class="fs-2 fw-bold text-dark">{{ $incidents->count() }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <small class="text-muted d-block mb-1">البلاغات المعالجة بنجاح</small>
                    <span class="fs-2 fw-bold text-success">{{ $byStatus['RESOLVED'] ?? 0 }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <small class="text-muted d-block mb-1">البلاغات التي تتطلب إجراء</small>
                    <span class="fs-2 fw-bold text-warning">{{ $byStatus['ACTION_REQUIRED'] ?? 0 }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <small class="text-muted d-block mb-1">البلاغات الحرجة (Critical)</small>
                    <span class="fs-2 fw-bold text-danger">{{ $bySeverity['CRITICAL'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-gavel me-1"></i> إحصائية حسب قرارات المعالجة</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($byDisposition as $disp => $count)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">{{ $disp }}</span>
                                <span class="badge bg-primary rounded-pill fs-7">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-building me-1"></i> الأقسام المتسببة بالعيوب</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($byDepartment as $deptName => $count)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">{{ $deptName }}</span>
                                <span class="badge bg-danger rounded-pill fs-7">{{ $count }} عيوب</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
