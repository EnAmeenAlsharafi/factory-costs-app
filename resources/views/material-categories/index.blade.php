@extends('layouts.app')

@section('title', 'تصنيفات المواد الخام - مصنع مفروشات سدير')
@section('page-title', 'إدارة تصنيفات المواد والخامات')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => 'تصنيفات المواد الخام (Material Categories)',
        'subtitle' => 'التصنيفات الرئيسية والفرعية للمواد والخامات المستخدمة في التصنيع.',
        'icon' => 'fas fa-boxes-stacked',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'تصنيفات المواد']
        ],
        'actionUrl' => route('material-categories.create'),
        'actionText' => 'إضافة تصنيف جديد',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'materials.manage'
    ])

    <!-- Categories Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>اسم التصنيف (عربي)</th>
                        <th>الاسم الإنجليزي</th>
                        <th>الوصف / النطاق</th>
                        <th>عدد المواد الخام</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                <code class="code-badge">{{ $category->code }}</code>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $category->name_ar }}</div>
                            </td>
                            <td>
                                <span dir="ltr" class="text-muted fs-7">{{ $category->name_en ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="text-secondary fs-7">{{ Str::limit($category->description, 60) ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $category->materials_count }} مادة</span>
                            </td>
                            <td>
                                @if ($category->is_active)
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
                                    @can('materials.manage')
                                        <a href="{{ route('material-categories.edit', $category) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل التصنيف">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('material-categories.toggle-status', $category) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $category->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $category->is_active ? 'تعطيل التصنيف' : 'تفعيل التصنيف' }}"
                                                    onclick="return confirm('{{ $category->is_active ? 'هل أنت متأكد من تعطيل هذا التصنيف؟' : 'هل ترغب في إعادة تفعيل هذا التصنيف؟' }}');">
                                                <i class="fas {{ $category->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-boxes-stacked fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد تصنيفات مواد مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
