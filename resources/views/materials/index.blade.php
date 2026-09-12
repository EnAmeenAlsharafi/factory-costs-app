@extends('layouts.app')

@section('title', 'كتالوج المواد الخام - مصنع مفروشات سدير')
@section('page-title', 'دليل وكتالوج المواد والخامات')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => 'كتالوج المواد الخام (Materials Catalog)',
        'subtitle' => 'السجل المركزي للمواد الخام، الأخشاب، الإسفنج، الأقمشة ومستلزمات الإنتاج.',
        'icon' => 'fas fa-layer-group',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'كتالوج المواد الخام']
        ],
        'actionUrl' => route('materials.create'),
        'actionText' => 'إضافة مادة جديدة',
        'actionIcon' => 'fas fa-plus',
        'actionPermission' => 'materials.manage'
    ])

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
            <form action="{{ route('materials.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="بحث بالرمز أو اسم المادة..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">جميع التصنيفات</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name_ar }} ({{ $cat->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط فقط</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل فقط</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">تصفية</button>
                    @if (request()->hasAny(['search', 'category_id', 'status']))
                        <a href="{{ route('materials.index') }}" class="btn btn-sm btn-outline-secondary" title="إعادة ضبط">
                            <i class="fas fa-undo"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Materials Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>كود المادة</th>
                        <th>اسم المادة (عربي)</th>
                        <th>التصنيف</th>
                        <th>وحدات الصرف والشراء</th>
                        <th>حد إعادة الطلب</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materials as $material)
                        <tr>
                            <td>
                                <a href="{{ route('materials.show', $material) }}" class="code-badge fw-bold text-decoration-none">
                                    {{ $material->code }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <a href="{{ route('materials.show', $material) }}" class="text-dark text-decoration-none hover-primary">
                                        {{ $material->name_ar }}
                                    </a>
                                </div>
                                @if ($material->name_en)
                                    <span dir="ltr" class="text-muted fs-8 d-block">{{ $material->name_en }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    {{ $material->category->name_ar ?? '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                    الأساسية: {{ $material->baseUnit->name_ar ?? '-' }}
                                </span>
                                @if ($material->purchaseUnit && $material->purchase_unit_id !== $material->base_unit_id)
                                    <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 ms-1">
                                        شراء: {{ $material->purchaseUnit->name_ar }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="fs-7 text-muted">
                                    <span>الحد الأدنى: {{ number_format($material->min_stock_level, 2) }}</span>
                                    <br>
                                    <span class="text-primary fs-8">إعادة الطلب: {{ number_format($material->reorder_point, 2) }}</span>
                                </div>
                            </td>
                            <td>
                                @if ($material->is_active)
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
                                    <a href="{{ route('materials.show', $material) }}" class="btn btn-sm btn-light border text-primary p-1.5" title="عرض التفاصيل والمواصفات">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @can('materials.manage')
                                        <a href="{{ route('materials.edit', $material) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل المادة">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('materials.toggle-status', $material) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $material->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $material->is_active ? 'تعطيل المادة' : 'تفعيل المادة' }}"
                                                    onclick="return confirm('{{ $material->is_active ? 'هل أنت متأكد من تعطيل هذه المادة الخام؟' : 'هل ترغب في إعادة تفعيل هذه المادة الخام؟' }}');">
                                                <i class="fas {{ $material->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-layer-group fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد مواد خام مسجلة تطابق محددات البحث.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($materials->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $materials->firstItem() }} إلى {{ $materials->lastItem() }} من إجمالي {{ $materials->total() }} مادة
                </div>
                <div>
                    {{ $materials->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
