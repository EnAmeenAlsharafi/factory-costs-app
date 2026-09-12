@extends('layouts.app')

@section('title', 'أقسام المصنع - مصنع مفروشات سدير')
@section('page-title', 'أقسام وورش المصنع')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'أقسام المصنع التشغيلية (Departments)',
        'subtitle' => 'مراحل وورش خط الإنتاج والمستودعات (النجارة، القص والإسفنج، التنجيد، تفصيل البوكسات، التجميع، التغليف، المستودع، التسليم).',
        'icon' => 'fas fa-building',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'أقسام المصنع']
        ],
        'actionUrl' => route('departments.create'),
        'actionText' => 'إضافة قسم جديد',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'departments.manage'
    ])

    <!-- Departments Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الرمز الكودي</th>
                        <th>اسم القسم (عربي)</th>
                        <th>الاسم الإنجليزي</th>
                        <th>تصنيف القسم</th>
                        <th>ترتيب خط الإنتاج</th>
                        <th>الموظفون المسندون</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>
                                <code class="code-badge">{{ $department->code }}</code>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $department->name_ar }}</div>
                                @if ($department->description)
                                    <small class="text-muted fs-8 d-block">{{ $department->description }}</small>
                                @endif
                            </td>
                            <td>
                                <span dir="ltr" class="text-muted fs-7">{{ $department->name_en ?? '-' }}</span>
                            </td>
                            <td>
                                @if ($department->is_production_department)
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25">
                                        <i class="fas fa-industry me-1"></i> قسم إنتاجي / ورشة
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">
                                        <i class="fas fa-warehouse me-1"></i> خدمات / لوجستي
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $department->sort_order }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                    <i class="fas fa-users me-1 text-muted"></i> {{ $department->users_count }} موظف
                                </span>
                            </td>
                            <td>
                                @if ($department->is_active)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> نشط
                                    </span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fas fa-ban me-1"></i> معطل
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    @can('departments.manage')
                                        <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل القسم">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('departments.toggle-status', $department) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $department->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $department->is_active ? 'تعطيل القسم' : 'تفعيل القسم' }}"
                                                    onclick="return confirm('{{ $department->is_active ? 'هل أنت متأكد من تعطيل هذا القسم؟' : 'هل ترغب في إعادة تفعيل هذا القسم؟' }}');">
                                                <i class="fas {{ $department->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-building-slash fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد أقسام مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($departments->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $departments->firstItem() }} إلى {{ $departments->lastItem() }} من إجمالي {{ $departments->total() }} قسم
                </div>
                <div>
                    {{ $departments->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
