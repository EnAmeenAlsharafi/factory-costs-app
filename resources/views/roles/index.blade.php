@extends('layouts.app')

@section('title', 'إدارة الأدوار والصلاحيات - مصنع مفروشات سدير')
@section('page-title', 'دليل الأدوار الوظيفية والصلاحيات')

@section('content')
@php
    $activeDepartment = $departments->firstWhere('id', (int) $activeTab);
@endphp
<div class="d-flex flex-column gap-4">

    @include('partials.page-header', [
        'title' => 'دليل الأدوار والصلاحيات التشغيلية',
        'subtitle' => 'أدوار عامة للإدارة والمبيعات والمستودع، ولكل قسم إنتاجي أدوار وصلاحيات منفصلة لا تتجاوز حدود القسم.',
        'icon' => 'fas fa-shield-alt',
        'breadcrumbs' => [
            ['title' => 'إدارة النظام'],
            ['title' => 'الأدوار والصلاحيات']
        ]
    ])

    <nav class="segment-tabs flex-wrap" aria-label="نطاق الأدوار">
        <a href="{{ route('roles.index') }}"
           class="segment-tab {{ ! $activeDepartment ? 'active' : '' }}"
           @if (! $activeDepartment) aria-current="page" @endif>
            <i class="fas fa-globe" aria-hidden="true"></i> أدوار عامة
            <span class="count">{{ $roles->count() }}</span>
        </a>
        @foreach ($departments as $department)
            <a href="{{ route('roles.index', ['department' => $department->id]) }}"
               class="segment-tab {{ $activeDepartment?->id === $department->id ? 'active' : '' }}"
               @if ($activeDepartment?->id === $department->id) aria-current="page" @endif>
                {{ $department->name_ar }}
                <span class="count">{{ $department->roles->count() }}</span>
            </a>
        @endforeach
    </nav>

    @if ($activeDepartment)
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <p class="text-muted mb-0 fs-7">
                <i class="fas fa-lock text-warning me-1" aria-hidden="true"></i>
                موظفو قسم <strong>{{ $activeDepartment->name_ar }}</strong> يرون وينفذون مهام قسمهم فقط، مهما كانت صلاحيات الدور.
            </p>
            @can('roles.manage')
                <a href="{{ route('roles.create', ['department_id' => $activeDepartment->id]) }}" class="btn btn-factory-warning">
                    <i class="fas fa-plus" aria-hidden="true"></i> إضافة دور لقسم {{ $activeDepartment->name_ar }}
                </a>
            @endcan
        </div>
    @endif

    @php
        $visibleRoles = $activeDepartment ? $activeDepartment->roles : $roles;
    @endphp

    @if ($visibleRoles->isEmpty())
        <div class="empty-state card text-center text-muted p-4">
            <i class="fas fa-user-tag fs-2 mb-3 d-block" aria-hidden="true"></i>
            <p class="mb-3 fw-semibold">لا توجد أدوار لهذا القسم بعد، ولن يستطيع موظفوه العمل حتى تُسند لهم أدوار.</p>
            @if ($activeDepartment)
                @can('roles.manage')
                    <div>
                        <a href="{{ route('roles.create', ['department_id' => $activeDepartment->id]) }}" class="btn btn-factory-warning">
                            <i class="fas fa-plus" aria-hidden="true"></i> إضافة أول دور للقسم
                        </a>
                    </div>
                @endcan
            @endif
        </div>
    @else
        {{-- Phone and tablet: one card per role --}}
        <div class="task-list mobile-cards-only-lg">
            @foreach ($visibleRoles as $role)
                <article class="task-card" aria-labelledby="role-title-{{ $role->id }}">
                    <div class="task-card-head">
                        <div class="min-w-0">
                            <h2 id="role-title-{{ $role->id }}" class="task-card-title">{{ $role->display_name }}</h2>
                            <div class="task-card-ref"><code class="text-muted" dir="ltr">{{ $role->name }}</code></div>
                        </div>
                    </div>
                    @if ($role->description)
                        <p class="text-secondary fs-7 mb-0">{{ $role->description }}</p>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        @include('roles.partials.role-badges', ['role' => $role])
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @include('roles.partials.role-actions', ['role' => $role])
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Desktop: dense table --}}
        <div class="table-factory-wrapper has-mobile-cards-lg">
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الدور الوظيفي</th>
                            <th>الوصف والمسؤوليات التشغيلية</th>
                            <th>الموظفون والصلاحيات</th>
                            <th class="d-none d-xl-table-cell">حالة الحماية</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visibleRoles as $role)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark fs-6">{{ $role->display_name }}</div>
                                    <code class="text-muted fs-8 text-nowrap" dir="ltr">{{ $role->name }}</code>
                                </td>
                                <td>
                                    <span class="text-secondary fs-7">{{ $role->description }}</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @include('roles.partials.role-badges', ['role' => $role])
                                    </div>
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    @if ($role->is_system)
                                        <span class="badge bg-light text-secondary border px-2 py-1 fs-8">
                                            <i class="fas fa-lock me-1" aria-hidden="true"></i> دور نظام أساسي
                                        </span>
                                    @elseif ($role->department_id)
                                        <span class="badge bg-light text-secondary border px-2 py-1 fs-8">
                                            <i class="fas fa-building me-1" aria-hidden="true"></i> دور قسم
                                        </span>
                                    @else
                                        <span class="badge bg-light text-secondary border px-2 py-1 fs-8">مخصص</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex flex-wrap justify-content-center gap-1">
                                        @include('roles.partials.role-actions', ['role' => $role])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
