@extends('layouts.app')

@section('title', 'دليل موديلات المصنع (Product Models)')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">موديلات المصنع</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">دليل موديلات المصنع الداخلي (Product Models)</h1>
        </div>

        @can('products.manage')
            <div>
                <a href="{{ route('products.models.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> إضافة موديل مصنع جديد
                </a>
            </div>
        @endcan
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('products.models.index') }}" class="row g-3 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="بحث بكود الموديل أو الاسم بالعربية/الإنجليزية..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <select name="status" class="form-select bg-light">
                        <option value="">كل الحالات</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>نشط (Active)</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>معطل (Inactive)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    @if(request()->anyFilled(['search', 'status']))
                        <a href="{{ route('products.models.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Models Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">الصورة</th>
                            <th>كود الموديل</th>
                            <th>اسم الموديل المصنعي</th>
                            <th class="text-center">تكوينات التصنيع</th>
                            <th class="text-center">مسميات العملاء (Aliases)</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center pe-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($models as $model)
                            <tr>
                                <td class="ps-3">
                                    @if($model->image_url)
                                        <img src="{{ $model->image_url }}" alt="{{ $model->name_ar }}" class="rounded border object-fit-cover" width="48" height="48">
                                    @else
                                        <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="width:48px; height:48px;">
                                            <i class="fas fa-bed fs-5"></i>
                                        </div>
                                    @endif
                                </td>
                                <td class="fw-mono fw-bold text-primary">
                                    <a href="{{ route('products.models.show', $model) }}" class="text-decoration-none">
                                        {{ $model->model_code }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $model->name_ar }}</div>
                                    @if($model->name_en)
                                        <small class="text-muted dir-ltr d-block">{{ $model->name_en }}</small>
                                    @endif
                                    @if($model->is_custom_template)
                                        <span class="badge bg-purple-subtle text-purple fw-semibold fs-8">قالب تصميم خاص</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-10 text-dark fw-mono px-3 py-1 fs-7">
                                        {{ $model->configurations_count }} تكوين
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-mono px-3 py-1 fs-7">
                                        {{ $model->aliases_count }} مسمى
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($model->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                <td class="text-center pe-3">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('products.models.show', $model) }}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @can('products.manage')
                                            <a href="{{ route('products.models.edit', $model) }}" class="btn btn-sm btn-outline-secondary" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('products.models.toggle-status', $model) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $model->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $model->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                    <i class="fas {{ $model->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-bed fs-1 d-block mb-3 text-secondary"></i>
                                    لا توجد موديلات منتجات مسجلة حتى الآن
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($models->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $models->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
