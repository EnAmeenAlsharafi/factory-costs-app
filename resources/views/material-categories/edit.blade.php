@extends('layouts.app')

@section('title', 'تعديل تصنيف مواد - مصنع مفروشات سدير')
@section('page-title', 'تعديل تصنيف مواد')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => 'تعديل تصنيف مواد: ' . $category->name_ar,
        'subtitle' => 'تعديل بيانات الرمز والاسم والوصف لتصنيف المواد الخام.',
        'icon' => 'fas fa-edit',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'تصنيفات المواد', 'url' => route('material-categories.index')],
            ['title' => 'تعديل تصنيف']
        ]
    ])

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('material-categories.update', $category) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="code" class="form-label fw-semibold text-dark">رمز التصنيف (Code) <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $category->code) }}" required dir="ltr">
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_ar" class="form-label fw-semibold text-dark">اسم التصنيف (عربي) <span class="text-danger">*</span></label>
                        <input type="text" name="name_ar" id="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $category->name_ar) }}" required>
                        @error('name_ar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_en" class="form-label fw-semibold text-dark">اسم التصنيف (إنجليزية)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en', $category->name_en) }}" dir="ltr">
                        @error('name_en')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold text-dark me-2" for="is_active">تفعيل التصنيف</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label fw-semibold text-dark">الوصف / ملاحظات</label>
                        <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('material-categories.index') }}" class="btn btn-light border">إلغاء</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> تحديث التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
