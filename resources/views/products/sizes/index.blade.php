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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSizeModal">
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
                            <th class="ps-3">رمز المقاس</th>
                            <th>الاسم بالعربية</th>
                            <th>العرض (سم)</th>
                            <th>الطول (سم)</th>
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
                                <td class="ps-3 fw-mono fw-bold text-primary">{{ $size->code }}</td>
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
                                        <form action="{{ route('products.sizes.toggle-status', $size) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $size->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $size->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="fas {{ $size->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
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
                        <label for="sort_order" class="form-label fw-semibold">ترتيب العرض</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
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
@endsection
