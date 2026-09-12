@extends('layouts.app')

@section('title', 'إضافة قسم جديد - مصنع مفروشات سدير')
@section('page-title', 'إضافة قسم أو ورشة عمل جديدة')

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 750px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لقائمة الأقسام
        </a>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4">
            <h5 class="fw-bold text-dark mb-1">
                <i class="fas fa-building text-warning me-2"></i> بيانات القسم الجديد
            </h5>
            <p class="text-muted fs-7 mb-0">قم بإدخال رمز القسم ومسماه وترتيبه في مسار وتدفق خط الإنتاج.</p>
        </div>

        <form action="{{ route('departments.store') }}" method="POST">
            @csrf

            <div class="row g-3">
                
                <!-- Code -->
                <div class="col-md-6">
                    <label for="code" class="form-label">الرمز الكودي <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="code" 
                           id="code" 
                           dir="ltr"
                           class="form-control text-uppercase font-monospace @error('code') is-invalid @enderror" 
                           value="{{ old('code') }}" 
                           placeholder="مثال: CARPENTRY أو UPHOLSTERY" 
                           required>
                    <small class="text-muted fs-8">رمز ثابت فريد بأحرف إنجليزية كبيرة.</small>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Sort Order -->
                <div class="col-md-6">
                    <label for="sort_order" class="form-label">ترتيب التسلسل في خط الإنتاج</label>
                    <input type="number" 
                           name="sort_order" 
                           id="sort_order" 
                           class="form-control @error('sort_order') is-invalid @enderror" 
                           value="{{ old('sort_order', 0) }}">
                    <small class="text-muted fs-8">رقم تسلسلي لتنظيم تدفق العمليات (1، 2، 3...).</small>
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name AR -->
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">اسم القسم (بالعربية) <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name_ar" 
                           id="name_ar" 
                           class="form-control @error('name_ar') is-invalid @enderror" 
                           value="{{ old('name_ar') }}" 
                           placeholder="مثال: ورشة النجارة وتفصيل الهياكل" 
                           required>
                    @error('name_ar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name EN -->
                <div class="col-md-6">
                    <label for="name_en" class="form-label">اسم القسم (بالإنجليزية) <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="name_en" 
                           id="name_en" 
                           class="form-control @error('name_en') is-invalid @enderror" 
                           value="{{ old('name_en') }}" 
                           placeholder="e.g. Carpentry Workshop">
                    @error('name_en')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label for="description" class="form-label">الوصف والمهام التشغيلية</label>
                    <textarea name="description" 
                              id="description" 
                              rows="3" 
                              class="form-control @error('description') is-invalid @enderror" 
                              placeholder="شرح مهام القسم، العمليات التي ينفذها والمخرجات المتوقعة...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Production Department Switch -->
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="is_production_department" 
                               id="is_production_department" 
                               value="1" 
                               {{ old('is_production_department', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_production_department">
                            هذا القسم هو مرحلة إنتاجية / ورشة تصنيع فعلية ضمن خط الإنتاج (يدخل في احتساب تقدم أوامر التصنيع)
                        </label>
                    </div>
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            تفعيل القسم وإتاحته لربط الموظفين والعمليات
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('departments.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ القسم
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
