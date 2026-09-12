@extends('layouts.app')

@section('title', 'إدارة الأدوار والصلاحيات - مصنع مفروشات سدير')
@section('page-title', 'دليل الأدوار الوظيفية والصلاحيات')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'دليل الأدوار والصلاحيات التشغيلية',
        'subtitle' => 'تحديد مصفوفة الصلاحيات لكل دور وظيفي في المصنع (الإدارة، الإنتاج، المستودع، المبيعات، خدمة العملاء).',
        'icon' => 'fas fa-shield-alt',
        'breadcrumbs' => [
            ['title' => 'إدارة النظام'],
            ['title' => 'الأدوار والصلاحيات']
        ]
    ])

    <!-- Roles Grid / Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الدور الوظيفي</th>
                        <th>المعرف البرمجي (Slug)</th>
                        <th>الوصف والمسؤوليات التشغيلية</th>
                        <th>عدد الموظفين</th>
                        <th>عدد الصلاحيات الممنوحة</th>
                        <th>حالة الحماية</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6">{{ $role->display_name }}</div>
                            </td>
                            <td>
                                <code class="code-badge">{{ $role->name }}</code>
                            </td>
                            <td>
                                <span class="text-secondary fs-7">{{ $role->description }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1">
                                    <i class="fas fa-users me-1 text-muted"></i> {{ $role->users_count }} مستخدم
                                </span>
                            </td>
                            <td>
                                @if ($role->name === 'admin')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1">
                                        <i class="fas fa-check-double me-1"></i> كافة الصلاحيات (شامل)
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1">
                                        {{ $role->permissions_count }} صلاحية
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($role->is_system)
                                    <span class="badge bg-light text-secondary border px-2 py-1 fs-8">
                                        <i class="fas fa-lock me-1"></i> دور نظام أساسي
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1 fs-8">مخصص</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('roles.show', $role) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="عرض الصلاحيات">
                                        <i class="fas fa-eye me-1"></i> استعراض
                                    </a>
                                    @can('roles.manage')
                                        @if ($role->name !== 'admin')
                                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-light border text-primary p-1.5" title="تعديل مصفوفة الصلاحيات">
                                                <i class="fas fa-sliders-h me-1"></i> تخصيص
                                            </a>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
