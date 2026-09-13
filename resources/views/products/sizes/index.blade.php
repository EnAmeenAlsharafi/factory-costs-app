@extends('layouts.app')

@section('title', 'المقاسات القياسية للأسرة (Standard Bed Sizes)')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">المقاسات القياسية</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">دليل المقاسات القياسية بالمرجع المصنعي (Standard Bed Sizes)</h1>
        </div>

        @can('products.manage')
            <div>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSizeModal">
                    <i class="fas fa-plus me-1"></i> إضافة مقاس قياسي جديد
                </button>
            </div>
        @endcan
    </div>

    <!-- Alert / Messages -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <!-- Sizes Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 text-center" style="width: 80px;">
                                <a href="{{ route('products.sizes.index', ['sort' => 'sort_order', 'direction' => ($sort === 'sort_order' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none">
                                    الترتيب
                                    @if($sort === 'sort_order')
                                        <i class="fas fa-sort-numeric-{{ $direction === 'asc' ? 'down' : 'up' }} text-primary ms-1"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ms-1 fs-8"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('products.sizes.index', ['sort' => 'code', 'direction' => ($sort === 'code' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none">
                                    رمز المقاس
                                    @if($sort === 'code')
                                        <i class="fas fa-sort-alpha-{{ $direction === 'asc' ? 'down' : 'up' }} text-primary ms-1"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ms-1 fs-8"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('products.sizes.index', ['sort' => 'name_ar', 'direction' => ($sort === 'name_ar' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none">
                                    الاسم بالعربية
                                    @if($sort === 'name_ar')
                                        <i class="fas fa-sort-alpha-{{ $direction === 'asc' ? 'down' : 'up' }} text-primary ms-1"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ms-1 fs-8"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('products.sizes.index', ['sort' => 'width_cm', 'direction' => ($sort === 'width_cm' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none">
                                    العرض (سم)
                                    @if($sort === 'width_cm')
                                        <i class="fas fa-sort-numeric-{{ $direction === 'asc' ? 'down' : 'up' }} text-primary ms-1"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ms-1 fs-8"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('products.sizes.index', ['sort' => 'length_cm', 'direction' => ($sort === 'length_cm' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none">
                                    الطول (سم)
                                    @if($sort === 'length_cm')
                                        <i class="fas fa-sort-numeric-{{ $direction === 'asc' ? 'down' : 'up' }} text-primary ms-1"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ms-1 fs-8"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-center">الأبعاد القياسية</th>
                            <th class="text-center">الحالة</th>
                            @can('products.manage')
                                <th class="text-center pe-3">الإجراءات</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sizes as $size)
                            <tr>
                                <td class="ps-3 text-center">
                                    <span class="badge bg-light text-dark border fw-mono px-2 py-1">{{ $size->sort_order }}</span>
                                </td>
                                <td class="fw-mono fw-bold text-primary">{{ $size->code }}</td>
                                <td class="fw-bold text-dark">{{ $size->name_ar ?? '-' }}</td>
                                <td class="fw-mono">{{ number_format($size->width_cm, 0) }} سم</td>
                                <td class="fw-mono">{{ number_format($size->length_cm, 0) }} سم</td>
                                <td class="text-center fw-mono fw-bold text-primary fs-6">{{ $size->formatted_dimensions }}</td>
                                <td class="text-center">
                                    @if($size->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                @can('products.manage')
                                    <td class="text-center pe-3">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSizeModal{{ $size->id }}" title="تعديل المقاس والترتيب">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form action="{{ route('products.sizes.toggle-status', $size) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $size->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $size->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                    <i class="fas {{ $size->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    لا توجد مقاسات قياسية مسجلة حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal: Add Standard Size -->
<div class="modal fade" id="addSizeModal" tabindex="-1" aria-labelledby="addSizeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('products.sizes.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="addSizeModalLabel"><i class="fas fa-ruler-combined text-primary me-2"></i>إضافة مقاس قياسي جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="size_code" class="form-label fw-semibold">رمز المقاس القياسي <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="size_code" class="form-control dir-ltr" required placeholder="SIZE-160X200">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="width_cm" class="form-label fw-semibold">العرض (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="width_cm" id="width_cm" class="form-control" required placeholder="160">
                        </div>
                        <div class="col-6">
                            <label for="length_cm" class="form-label fw-semibold">الطول (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="length_cm" id="length_cm" class="form-control" required placeholder="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name_ar" class="form-label fw-semibold">اسم المقاس بالعربية (إيضاحي)</label>
                        <input type="text" name="name_ar" id="name_ar" class="form-control" placeholder="160 × 200 سم (مزدوج كوين)">
                    </div>

                    <div class="mb-3">
                        <label for="sort_order" class="form-label fw-semibold">ترتيب العرض (Sort Order)</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                        <small class="text-muted fs-8">الأرقام الأقل تظهر أولاً في القوائم المنسدلة بدليل الموديلات والطلبات.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ المقاس</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals: Edit Standard Sizes -->
@foreach($sizes as $size)
<div class="modal fade" id="editSizeModal{{ $size->id }}" tabindex="-1" aria-labelledby="editSizeModalLabel{{ $size->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('products.sizes.update', $size) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="editSizeModalLabel{{ $size->id }}">
                        <i class="fas fa-edit text-primary me-2"></i>تعديل المقاس القياسي ({{ $size->code }})
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label for="code_{{ $size->id }}" class="form-label fw-semibold">رمز المقاس القياسي <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="code_{{ $size->id }}" class="form-control dir-ltr" value="{{ old('code', $size->code) }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="width_cm_{{ $size->id }}" class="form-label fw-semibold">العرض (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="width_cm" id="width_cm_{{ $size->id }}" class="form-control" value="{{ old('width_cm', $size->width_cm) }}" required>
                        </div>
                        <div class="col-6">
                            <label for="length_cm_{{ $size->id }}" class="form-label fw-semibold">الطول (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="length_cm" id="length_cm_{{ $size->id }}" class="form-control" value="{{ old('length_cm', $size->length_cm) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name_ar_{{ $size->id }}" class="form-label fw-semibold">اسم المقاس بالعربية (إيضاحي)</label>
                        <input type="text" name="name_ar" id="name_ar_{{ $size->id }}" class="form-control" value="{{ old('name_ar', $size->name_ar) }}">
                    </div>

                    <div class="mb-3">
                        <label for="sort_order_{{ $size->id }}" class="form-label fw-semibold">ترتيب العرض (Sort Order)</label>
                        <input type="number" name="sort_order" id="sort_order_{{ $size->id }}" class="form-control" value="{{ old('sort_order', $size->sort_order) }}">
                        <small class="text-muted fs-8">حدد رقم الترتيب المصنعي للتحكم بأولوية ظهور هذا المقاس.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
