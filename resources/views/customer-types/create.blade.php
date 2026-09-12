@extends('layouts.app')

@section('title', 'إضافة تصنيف عميل جديد - مصنع مفروشات سدير')
@section('page-title', 'إضافة تصنيف عميل جديد')

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 700px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('customer-types.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لتصنيفات العملاء
        </a>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4">
            <h5 class="fw-bold text-dark mb-1">
                <i class="fas fa-id-badge text-warning me-2"></i> بيانات تصنيف العميل
            </h5>
            <p class="text-muted fs-7 mb-0">قم بإدخال الرمز الفريد ومسمى التصنيف باللغتين العربية والإنجليزية.</p>
        </div>

        <form action="{{ route('customer-types.store') }}" method="POST">
            @csrf

            <div class="row g-3">
                
                <!-- Code -->
                <div class="col-12">
                    <label for="code" class="form-label">الرمز الكودي <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="code" 
                           id="code" 
                           dir="ltr"
                           class="form-control text-uppercase font-monospace @error('code') is-invalid @enderror" 
                           value="{{ old('code') }}" 
                           placeholder="مثال: WHOLESALE_CUSTOMER أو DIRECT_CUSTOMER" 
                           required>
                    <small class="text-muted fs-8">رمز ثابت فريد بأحرف كبيرة وشرطة سفلية.</small>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name AR -->
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">مسمى التصنيف (بالعربية) <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name_ar" 
                           id="name_ar" 
                           class="form-control @error('name_ar') is-invalid @enderror" 
                           value="{{ old('name_ar') }}" 
                           placeholder="مثال: عميل جملة" 
                           required>
                    @error('name_ar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name EN -->
                <div class="col-md-6">
                    <label for="name_en" class="form-label">مسمى التصنيف (بالإنجليزية) <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="name_en" 
                           id="name_en" 
                           class="form-control @error('name_en') is-invalid @enderror" 
                           value="{{ old('name_en') }}" 
                           placeholder="e.g. Wholesale Customer">
                    @error('name_en')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            تفعيل التصنيف وإتاحته عند إنشاء عملاء جدد
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('customer-types.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ التصنيف
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
