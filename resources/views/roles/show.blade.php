@extends('layouts.app')

@section('title', 'عرض صلاحيات الدور: ' . $role->display_name . ' - مصنع مفروشات سدير')
@section('page-title', 'صلاحيات الدور الوظيفي: ' . $role->display_name)

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Header -->
    <div class="card bg-white p-4 border-0 shadow-sm rounded-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-warning text-dark font-monospace">{{ $role->name }}</span>
                    @if ($role->is_system)
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border">دور نظام قياسي</span>
                    @endif
                </div>
                <h4 class="fw-bold text-dark mb-1">{{ $role->display_name }}</h4>
                <p class="text-muted mb-0 fs-7">{{ $role->description }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary rounded-3 px-3 fs-7">
                    <i class="fas fa-arrow-right me-1"></i> العودة للأدوار
                </a>
                @can('roles.manage')
                    @if ($role->name !== 'admin')
                        <a href="{{ route('roles.edit', $role) }}" class="btn btn-factory-warning rounded-3 px-4 fs-7">
                            <i class="fas fa-sliders-h me-1"></i> تعديل الصلاحيات
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @php
        $moduleLabels = [
            'users' => 'المستخدمون', 'roles' => 'الأدوار والصلاحيات', 'customers' => 'العملاء',
            'quotations' => 'عروض الأسعار', 'orders' => 'طلبات العملاء', 'production' => 'الإنتاج',
            'inventory' => 'المخزون', 'materials' => 'المواد الخام', 'products' => 'المنتجات',
            'suppliers' => 'الموردون', 'costing' => 'التكاليف', 'delivery' => 'التوصيل',
            'reports' => 'التقارير', 'sales_channels' => 'قنوات البيع',
            'customer_types' => 'أنواع العملاء', 'units' => 'وحدات القياس', 'departments' => 'أقسام المصنع',
        ];
    @endphp

    <!-- Permissions Breakdown Grouped by Module -->
    <div class="row g-3">
        @foreach ($groupedPermissions as $module => $permissions)
            @php
                $moduleRolePerms = $permissions->filter(function ($p) use ($role) {
                    return $role->hasPermission($p->name);
                });
            @endphp
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card bg-white border-0 shadow-sm rounded-4 h-100 p-3">
                    <div class="card-header bg-white border-bottom py-2 px-1 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0 fs-7">
                            <i class="fas fa-folder text-warning me-1"></i> {{ $moduleLabels[$module] ?? $module }}
                            <code class="permission-module-key d-block text-muted mt-1">{{ $module }}</code>
                        </h6>
                        <span class="badge {{ $moduleRolePerms->count() > 0 ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-muted border' }} fs-8">
                            {{ $moduleRolePerms->count() }} من {{ $permissions->count() }}
                        </span>
                    </div>
                    <div class="card-body p-2">
                        <ul class="list-unstyled mb-0">
                            @foreach ($permissions as $permission)
                                @php
                                    $hasPerm = $role->hasPermission($permission->name);
                                @endphp
                                <li class="py-1.5 d-flex align-items-center justify-content-between fs-8 border-bottom border-light">
                                    <span class="{{ $hasPerm ? 'text-dark fw-semibold' : 'text-muted opacity-50' }}">
                                        {{ $permission->display_name }}
                                    </span>
                                    @if ($hasPerm)
                                        <i class="fas fa-check-circle text-success fs-7"></i>
                                    @else
                                        <i class="fas fa-times-circle text-muted opacity-25 fs-7"></i>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection
