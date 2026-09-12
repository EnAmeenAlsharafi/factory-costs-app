@extends('layouts.app')

@section('title', 'وصفات التصنيع (BOM)')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">وصفات التصنيع (Bill of Materials)</h1>
            <p class="text-muted mb-0 fs-7">إدارة قائمة المواد المكونة وتوصيفات التصنيع وإصداراتها للموديلات والمكونات نصف المصنعة</p>
        </div>
        <div>
            @can('recipes.manage')
                <a href="{{ route('recipes.create') }}" class="btn btn-warning px-3 fw-semibold">
                    <i class="fas fa-plus me-1"></i> إضافة وصفة جديدة
                </a>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('recipes.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="بحث بكود الوصفة، الاسم، الموديل..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="target_type" class="form-select">
                        <option value="">-- جميع الأنواع --</option>
                        <option value="PRODUCT_CONFIGURATION" {{ request('target_type') === 'PRODUCT_CONFIGURATION' ? 'selected' : '' }}>تكوين منتج نهائي</option>
                        <option value="SEMI_FINISHED_COMPONENT" {{ request('target_type') === 'SEMI_FINISHED_COMPONENT' ? 'selected' : '' }}>مكون نصف مصنع</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="model_id" class="form-select">
                        <option value="">-- جميع الموديلات --</option>
                        @foreach($productModels as $model)
                            <option value="{{ $model->id }}" {{ request('model_id') == $model->id ? 'selected' : '' }}>{{ $model->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Recipes Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">كود الوصفة</th>
                            <th>الاسم / الهدف</th>
                            <th>النوع Target</th>
                            <th>الإصدار المعتمد الحالي</th>
                            <th>إجمالي الإصدارات</th>
                            <th>الحالة</th>
                            <th>تاريخ التحديث</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipes as $recipe)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $recipe->recipe_code }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $recipe->name }}</div>
                                    @if($recipe->target_type === 'PRODUCT_CONFIGURATION' && $recipe->productConfiguration)
                                        <small class="text-muted">
                                            موديل: {{ $recipe->productConfiguration->productModel?->name_ar }}
                                            ({{ $recipe->productConfiguration->width_cm }} × {{ $recipe->productConfiguration->length_cm }} سم)
                                            @if($recipe->productConfiguration->has_storage)
                                                <span class="badge bg-warning text-dark me-1">سحارة</span>
                                            @endif
                                        </small>
                                    @elseif($recipe->target_type === 'SEMI_FINISHED_COMPONENT' && $recipe->semiFinishedComponent)
                                        <small class="text-muted">مكون: {{ $recipe->semiFinishedComponent->name_ar }} ({{ $recipe->semiFinishedComponent->component_code }})</small>
                                    @endif
                                </td>
                                <td>
                                    @if($recipe->target_type === 'PRODUCT_CONFIGURATION')
                                        <span class="badge bg-info text-dark">منتج نهائي</span>
                                    @else
                                        <span class="badge bg-purple text-white" style="background-color: #6f42c1;">مكون نصف مصنع</span>
                                    @endif
                                </td>
                                <td>
                                    @if($recipe->currentApprovedVersion)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> V{{ $recipe->currentApprovedVersion->version_number }} معتمد
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">لا يوجد إصدار معتمد</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $recipe->versions->count() }} إصدار</span>
                                </td>
                                <td>
                                    @if($recipe->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">معطل</span>
                                    @endif
                                </td>
                                <td class="fs-7 text-muted">{{ $recipe->updated_at->format('Y-m-d H:i') }}</td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل والإصدارات">
                                        <i class="fas fa-eye me-1"></i> عرض
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-scroll fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد وصفات تصنيع معرفة حالياً matching الفلترة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($recipes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
