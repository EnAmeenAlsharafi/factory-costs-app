@extends('layouts.app')

@section('title', 'تقارير التكلفة والجودة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="mb-4">
        <h4 class="fw-bold mb-0 text-dark">تقارير التكلفة، الاستهلاك، والهدر والجودة (Production Reports)</h4>
        <p class="text-muted mb-0 mt-1">منظومة تقارير الاستهلاك الفعلي للمواد، الانحراف عن BOM، وتحليل الهدر وحالات الجودة</p>
    </div>

    <div class="row g-4">
        <!-- Report 1: Actual vs Planned Cost -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">تكلفة الاستهلاك الفعلي مقابل المخطط (Actual vs BOM)</h5>
                    <p class="text-muted fs-7 mb-4">مقارنة التكلفة الفعلية الصافية (المصروف - المرتجع) مع تكلفة الوصفة المخططة وحساب انحرافات التكلفة لكل امر إنتاج.</p>
                    <a href="{{ route('production.reports.cost') }}" class="btn btn-primary w-100">
                        <i class="fas fa-chart-line me-1"></i> استعراض تقرير التكلفة
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 2: Waste Analysis -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-dumpster fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">تحليل الهدر والتلف التحليلي (Waste Analysis)</h5>
                    <p class="text-muted fs-7 mb-4">تحليل تفصيلي لكميات وتكاليف الهدر والتلف موزعة حسب الأسباب التحليلية والأقسام المتسببة بدون مزدوجات.</p>
                    <a href="{{ route('production.reports.waste') }}" class="btn btn-danger w-100">
                        <i class="fas fa-chart-pie me-1"></i> استعراض تقرير الهدر
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 3: Quality Incidents Summary -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">ملخص وحالات الجودة (Quality Summary)</h5>
                    <p class="text-muted fs-7 mb-4">ملخص احصائي لبلاغات حالات الجودة وحجم تأثيرها وقرارات المعالجة والأقسام الأكثر تسبباً بالعيوب.</p>
                    <a href="{{ route('production.reports.quality') }}" class="btn btn-warning text-dark w-100">
                        <i class="fas fa-list-check me-1"></i> استعراض ملخص الجودة
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
