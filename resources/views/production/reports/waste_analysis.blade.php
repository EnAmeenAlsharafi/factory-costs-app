@extends('layouts.app')

@section('title', 'تقرير تحليل الهدر والتلف - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">تقرير تحليل الهدر والتلف التحليلي (Analytical Waste Report)</h4>
            <p class="text-muted mb-0 mt-1">توزيع وتكلفة كميات التلف حسب السبب والأقسام المسؤولة</p>
        </div>
        <a href="{{ route('production.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للتقارير
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-chart-pie me-1"></i> توزيع التكلفة حسب سبب التلف</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($byReason as $reasonName => $data)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark">{{ $reasonName }}</strong>
                                    <small class="text-muted d-block">{{ $data['count'] }} حالات هدر</small>
                                </div>
                                <div>
                                    @if($canViewCost)
                                        <span class="badge bg-danger fs-6">{{ number_format($data['total_cost'], 2) }} ر.س</span>
                                    @else
                                        <span class="badge bg-secondary">سري</span>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-3">لا توجد إحصائيات هدر مسجلة.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-building me-1"></i> توزيع التكلفة حسب القسم المتسبب</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($byDepartment as $deptName => $data)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark">{{ $deptName }}</strong>
                                    <small class="text-muted d-block">{{ $data['count'] }} حوادث تلف</small>
                                </div>
                                <div>
                                    @if($canViewCost)
                                        <span class="badge bg-primary fs-6">{{ number_format($data['total_cost'], 2) }} ر.س</span>
                                    @else
                                        <span class="badge bg-secondary">سري</span>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-3">لا توجد إحصائيات أقسام مسجلة.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
