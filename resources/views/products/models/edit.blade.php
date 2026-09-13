@extends('layouts.app')

@section('title', 'تعديل موديل المصنع - ' . $model->model_code)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.models.index') }}" class="text-decoration-none">موديلات المصنع</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.models.show', $model) }}" class="text-decoration-none">{{ $model->model_code }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">تعديل الموديل</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">تعديل بيانات الموديل: <span class="text-primary fw-mono">{{ $model->model_code }}</span></h1>
        </div>
        <div>
            <a href="{{ route('products.models.show', $model) }}" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i> إلغاء
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <form action="{{ route('products.models.update', $model) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-edit text-primary me-2"></i>البيانات الأساسية للموديل</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="name_ar" class="form-label fw-semibold">اسم الموديل بالعربية <span class="text-danger">*</span></label>
                                <input type="text" name="name_ar" id="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $model->name_ar) }}" required>
                                @error('name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="name_en" class="form-label fw-semibold">اسم الموديل بالإنجليزية</label>
                                <input type="text" name="name_en" id="name_en" class="form-control dir-ltr @error('name_en') is-invalid @enderror" value="{{ old('name_en', $model->name_en) }}">
                                @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label fw-semibold">الوصف والشرح الفني للموديل</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $model->description) }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="design_notes" class="form-label fw-semibold">ملاحظات وقواعد التصنيع الهندسية</label>
                                <textarea name="design_notes" id="design_notes" rows="3" class="form-control @error('design_notes') is-invalid @enderror">{{ old('design_notes', $model->design_notes) }}</textarea>
                                @error('design_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-image text-primary me-2"></i>الصورة الإيضاحية والإعدادات</h5>
                    </div>
                    <div class="card-body">
                        @if($model->image_url)
                            <div class="mb-3 text-center">
                                <img src="{{ $model->image_url }}" alt="{{ $model->name_ar }}" class="img-fluid rounded border shadow-sm max-h-48 object-fit-cover mb-2" style="max-height: 180px;">
                                <small class="text-muted d-block">الصورة الحالية للموديل</small>
                            </div>
                        @endif

                        @php
                            $isUrl = $model->reference_image_path && (str_starts_with($model->reference_image_path, 'http://') || str_starts_with($model->reference_image_path, 'https://'));
                            $defaultSource = old('image_source', $isUrl ? 'url' : 'file');
                            $currentUrl = $isUrl ? $model->reference_image_path : '';
                        @endphp

                        <div class="mb-3" x-data="{ imageSource: '{{ $defaultSource }}' }">
                            <label class="form-label fw-semibold">تحديث الصورة المرجعية</label>
                            <div class="d-flex gap-3 mb-2 p-2 bg-light rounded border">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="image_source" id="img_source_file_edit" value="file" x-model="imageSource">
                                    <label class="form-check-label fw-semibold fs-7" for="img_source_file_edit">
                                        <i class="fas fa-upload me-1 text-primary"></i> رفع صورة جديدة
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="image_source" id="img_source_url_edit" value="url" x-model="imageSource">
                                    <label class="form-check-label fw-semibold fs-7" for="img_source_url_edit">
                                        <i class="fas fa-link me-1 text-primary"></i> رابط صورة (URL)
                                    </label>
                                </div>
                            </div>

                            <div x-show="imageSource === 'file'" class="mt-2">
                                <input type="file" name="reference_image" id="reference_image" class="form-control @error('reference_image') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted d-block mt-1">اختيار ملف جديد من الجهاز</small>
                                @error('reference_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div x-show="imageSource === 'url'" class="mt-2" x-cloak>
                                <input type="url" name="reference_image_url" id="reference_image_url" class="form-control dir-ltr @error('reference_image_url') is-invalid @enderror" value="{{ old('reference_image_url', $currentUrl) }}" placeholder="https://example.com/image.jpg">
                                <small class="text-muted d-block mt-1">أدخل رابط الصورة الصريح والمباشر (مثل: https://...)</small>
                                @error('reference_image_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_custom_template" id="is_custom_template" value="1" {{ old('is_custom_template', $model->is_custom_template) ? 'checked' : '' }}>
                            <label class="form-check-input-label fw-semibold ms-2" for="is_custom_template">قالب تصميم خاص</label>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $model->is_active) ? 'checked' : '' }}>
                            <label class="form-check-input-label fw-semibold ms-2" for="is_active">حالة الموديل نشط (Active)</label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary py-2 fw-bold">
                        <i class="fas fa-save me-1"></i> حفظ التحديثات
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
@endsection
