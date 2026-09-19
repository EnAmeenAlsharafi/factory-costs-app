@extends('layouts.app')

@section('title', 'لوحة تحكم التحصيل والدفعات')

@section('content')
<div class="container-fluid px-4 py-3">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-chart-line text-warning me-2"></i> لوحة تحكم التحصيل والأرصدة التشغيلية
            </h1>
            <p class="text-muted mb-0 small">متابعة المستحقات التجاريّة، الدفعات المعلقة، وتجاوزات الائتمان لعملاء المصنع.</p>
        </div>
        <div class="d-flex gap-2">
            @can('receivables.payment.create')
                <a href="{{ route('receivables.payments.create') }}" class="btn btn-warning fw-bold px-3">
                    <i class="fas fa-plus-circle me-1"></i> تسجيل دفعة عميل
                </a>
            @endcan
            @can('receivables.view')
                <a href="{{ route('receivables.payments.index') }}" class="btn btn-outline-secondary px-3">
                    <i class="fas fa-list me-1"></i> سجل الدفعات
                </a>
            @endcan
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-gradient" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 small fw-bold">إجمالي المستحقات القائمة</span>
                        <div class="rounded-circle bg-warning bg-opacity-20 text-warning p-2">
                            <i class="fas fa-coins fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-warning">{{ number_format($summary['total_outstanding'], 2) }} <small class="fs-6 text-white-50">ر.س</small></h3>
                    <div class="small text-white-50">الرصيد المتبقي على جميع الطلبات القائمة</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-danger border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold">المتأخرات المتجاوزة لتاريخ الاستحقاق</span>
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-2">
                            <i class="fas fa-exclamation-circle fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger">{{ number_format($summary['overdue_outstanding'], 2) }} <small class="fs-6 text-muted">ر.س</small></h3>
                    <div class="small text-muted">طلبات متأخرة تتطلب المتابعة والتحصيل</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold">دفعات تنتظر التأكيد الإداري</span>
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-2">
                            <i class="fas fa-clock fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ $summary['unconfirmed_payments_count'] }} <small class="fs-6 text-muted">دفعات</small></h3>
                    <div class="small text-muted">بقيمة إجمالية {{ number_format($summary['unconfirmed_payments_amount'], 2) }} ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold">رصيد عملاء غير مخصص</span>
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-2">
                            <i class="fas fa-wallet fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-success">{{ number_format($summary['unallocated_credit_amount'], 2) }} <small class="fs-6 text-muted">ر.س</small></h3>
                    <div class="small text-muted">دفعات مؤكدة متاحة للتخصيص على الطلبات</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Operational Control Alerts --}}
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-shield-alt text-warning me-2"></i> قيود سداد الإنتاج والتسليم
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold">طلبات ينتظر استكمال عربونها</h6>
                                <small class="text-muted">طلبات بشروط عربون لم تُستوفَ قيمته بعد</small>
                            </div>
                            <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill">{{ $summary['deposit_pending_orders_count'] }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold text-danger">طلبات موقوفة عن إطلاق الإنتاج</h6>
                                <small class="text-muted">بسبب عدم سداد الدفعة المقدمة أو تجاوز الائتمان</small>
                            </div>
                            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">{{ $summary['production_blocked_orders_count'] }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold text-danger">طلبات موقوفة عن التوصيل والتسليم</h6>
                                <small class="text-muted">بسبب عدم تصفية المتبقي قبل التسليم</small>
                            </div>
                            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">{{ $summary['delivery_blocked_orders_count'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-credit-card text-danger me-2"></i> حدود الائتمان وآجال السداد
                    </h5>
                    <a href="{{ route('receivables.credit.index') }}" class="btn btn-sm btn-link text-decoration-none">إدارة الائتمان &larr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold text-danger">عملاء تجاوزوا حد الائتمان</h6>
                                <small class="text-muted">التعرض الحالي يتجاوز السقف الائتماني المعتمد</small>
                            </div>
                            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">{{ $summary['exceeded_credit_customers_count'] }} عملاء</span>
                        </div>
                        <div class="list-group-item p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('receivables.customers.index') }}" class="btn btn-outline-dark btn-sm w-100 fw-bold py-2">
                                    <i class="fas fa-users-slash me-1"></i> استعراض كشوفات أرصدة جميع العملاء
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
