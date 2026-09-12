@extends('layouts.app')

@section('title', 'إضافة تصنيف مواد جديد - مصنع مفروشات سدير')
@section('page-title', 'إضافة تصنيف مواد جديد')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => 'إضافة تصنيف مواد جديد',
        'subtitle' => 'تعريف تصنيف رئيسي جديد للمواد والخامات في المصنع.',
        'icon' => 'fas fa-plus-circle',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'تصنيفات المواد', 'url' => route('material-categories.index')],
            ['title' => 'إضافة تصنيف']
        ]
    ])

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('material-categories.store') }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="code" class="form-label fw-semibold text-dark">رمز التصنيف (Code) <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="مثال: WOOD, FOAM, FABRIC" required dir="ltr">
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_ar" class="form-label fw-semibold text-dark">اسم التصنيف (عربي) <span class="text-danger">*</span></label>
                        <input type="text" name="name_ar" id="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" placeholder="مثال: أخشاب وألواح" required>
                        @error('name_ar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_en" class="form-label fw-semibold text-dark">اسم التصنيف (إنجليزية)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en') }}" placeholder="e.g. Wood & Panels" dir="ltr">
                        @error('name_en')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold text-dark me-2" for="is_active">تفعيل التصنيف</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label fw-semibold text-dark">الوصف / ملاحظات</label>
                        <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="وصف مقتضب لنوعية الخامات التابعة لهذا التصنيف">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('material-categories.index') }}" class="btn btn-light border">إلغاء</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ التصنيف
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
