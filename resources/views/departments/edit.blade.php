@extends('layouts.app')

@section('title', 'تعديل قسم المصنع - مصنع مفروشات سدير')
@section('page-title', 'تعديل القسم: ' . $department->name_ar)

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
        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fas fa-edit text-warning me-2"></i> تعديل بيانات القسم
                </h5>
                <p class="text-muted fs-7 mb-0">تحديث مسمى القسم، الترتيب التسلسلي والمهام التشغيلية.</p>
            </div>
            <code class="bg-light px-3 py-1.5 rounded-3 text-primary fs-6 fw-bold">{{ $department->code }}</code>
        </div>

        <form action="{{ route('departments.update', $department) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                
                <!-- Code -->
                <div class="col-md-6">
                    <label for="code" class="form-label">الرمز الكودي <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="code" 
                           id="code" 
                           dir="ltr"
                           class="form-control text-uppercase font-monospace @error('code') is-invalid @enderror" 
                           value="{{ old('code', $department->code) }}" 
                           required>
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
                           value="{{ old('sort_order', $department->sort_order) }}">
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
                           value="{{ old('name_ar', $department->name_ar) }}" 
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
                           value="{{ old('name_en', $department->name_en) }}">
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
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $department->description) }}</textarea>
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
                               {{ old('is_production_department', $department->is_production_department) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_production_department">
                            هذا القسم هو مرحلة إنتاجية / ورشة تصنيع فعلية ضمن خط الإنتاج
                        </label>
                    </div>
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            القسم نشط ومتاح للاستخدام
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('departments.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ التعديلات
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
