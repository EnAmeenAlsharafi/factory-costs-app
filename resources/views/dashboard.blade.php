@extends('layouts.app')

@section('title', 'لوحة التحكم - مصنع مفروشات سدير')
@section('page-title', 'لوحة التحكم الرئيسية')

@section('content')
<div class="d-flex flex-column gap-4">
    @include('partials.page-header', [
        'title' => 'لوحة التحكم الرئيسية',
        'subtitle' => 'وصول سريع إلى السجلات المتاحة لك وفق صلاحيات حسابك.',
        'icon' => 'fas fa-gauge-high',
        'breadcrumbs' => [
            ['title' => 'لوحة التحكم']
        ]
    ])

    @php
        $hasCurrentModuleAccess = auth()->user()->can('users.view')
            || auth()->user()->can('roles.view')
            || auth()->user()->can('customers.view')
            || auth()->user()->can('suppliers.view')
            || auth()->user()->can('sales_channels.view')
            || auth()->user()->can('customer_types.view')
            || auth()->user()->can('units.view')
            || auth()->user()->can('departments.view');
    @endphp

    @if ($hasCurrentModuleAccess)
        <section aria-labelledby="available-records-heading">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 id="available-records-heading" class="h5 fw-bold mb-1">السجلات المتاحة</h2>
                    <p class="text-muted fs-7 mb-0">الأرقام أدناه مأخوذة من البيانات الأساسية الحالية.</p>
                </div>
            </div>

            <div class="row g-3">
                @can('users.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('users.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-primary bg-opacity-10 text-primary rounded-3 p-3"><i class="fas fa-users-gear" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">المستخدمون النشطون</small><strong class="fs-4">{{ number_format($masterDataStats['users_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('customers.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('customers.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-info bg-opacity-10 text-info rounded-3 p-3"><i class="fas fa-users" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">العملاء</small><strong class="fs-4">{{ number_format($masterDataStats['customers_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('suppliers.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('suppliers.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-success bg-opacity-10 text-success rounded-3 p-3"><i class="fas fa-truck" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">الموردون</small><strong class="fs-4">{{ number_format($masterDataStats['suppliers_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('departments.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('departments.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-warning bg-opacity-25 text-dark rounded-3 p-3"><i class="fas fa-building" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">الأقسام النشطة</small><strong class="fs-4">{{ number_format($masterDataStats['departments_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('units.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('units.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-3"><i class="fas fa-ruler-combined" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">وحدات القياس النشطة</small><strong class="fs-4">{{ number_format($masterDataStats['units_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('sales_channels.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('sales-channels.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-primary bg-opacity-10 text-primary rounded-3 p-3"><i class="fas fa-store" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">قنوات البيع النشطة</small><strong class="fs-4">{{ number_format($masterDataStats['sales_channels_count']) }}</strong></span>
                        </a>
                    </div>
                @endcan
                @can('customer_types.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('customer-types.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-info bg-opacity-10 text-info rounded-3 p-3"><i class="fas fa-id-badge" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">أنواع العملاء</small><strong>فتح السجل</strong></span>
                        </a>
                    </div>
                @endcan
                @can('roles.view')
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ route('roles.index') }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-danger bg-opacity-10 text-danger rounded-3 p-3"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                            <span><small class="d-block text-muted">الأدوار والصلاحيات</small><strong>فتح الدليل</strong></span>
                        </a>
                    </div>
                @endcan
            </div>
        </section>
    @else
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-circle-info fs-2 mb-3 text-secondary" aria-hidden="true"></i>
            <h2 class="h5 fw-bold">لا توجد سجلات متاحة لحسابك حاليًا</h2>
            <p class="mb-0">ستظهر هنا الوحدات التي تسمح بها صلاحيات دورك عند تفعيلها.</p>
        </div>
    @endif

    @if ($metrics->isNotEmpty())
        <section aria-labelledby="operational-metrics-heading">
            <h2 id="operational-metrics-heading" class="h5 fw-bold mb-3">المؤشرات التشغيلية</h2>
            <div class="row g-3">
                @foreach($metrics as $metric)
                    <div class="col-12 col-sm-6 col-xl-4">
                        <a href="{{ route($metric['route']) }}" class="card-factory d-flex align-items-center gap-3 h-100 p-3 text-decoration-none text-dark">
                            <span class="bg-{{ $metric['class'] }} bg-opacity-10 text-{{ $metric['class'] }} rounded-3 p-3">
                                <i class="fas fa-{{ $metric['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <span>
                                <small class="d-block text-muted">{{ $metric['label'] }}</small>
                                <strong class="fs-4">{{ number_format($metric['value']) }}</strong>
                                <small class="text-muted">{{ $metric['unit'] }}</small>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
