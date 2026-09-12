@extends('layouts.app')

@section('title', 'إضافة وحدة قياس جديدة - مصنع مفروشات سدير')
@section('page-title', 'إضافة وحدة قياس جديدة')

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 750px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('units.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لوحدات القياس
        </a>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4">
            <h5 class="fw-bold text-dark mb-1">
                <i class="fas fa-ruler-combined text-warning me-2"></i> بيانات وحدة القياس
            </h5>
            <p class="text-muted fs-7 mb-0">قم بإدخال الرمز، المسمى، نوع القياس وقواعد دقة الكسور العشرية.</p>
        </div>

        <form action="{{ route('units.store') }}" method="POST" x-data="{ allowsDecimal: {{ old('allows_decimal') ? 'true' : 'false' }} }">
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
                           placeholder="مثال: METER, ROLL, BOARD" 
                           required>
                    <small class="text-muted fs-8">رمز ثابت فريد بأحرف كبيرة.</small>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Symbol -->
                <div class="col-md-6">
                    <label for="symbol" class="form-label">الرمز المختصر (Symbol)</label>
                    <input type="text" 
                           name="symbol" 
                           id="symbol" 
                           class="form-control @error('symbol') is-invalid @enderror" 
                           value="{{ old('symbol') }}" 
                           placeholder="مثال: م، كجم، حبة">
                    @error('symbol')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name AR -->
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">اسم الوحدة (بالعربية) <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name_ar" 
                           id="name_ar" 
                           class="form-control @error('name_ar') is-invalid @enderror" 
                           value="{{ old('name_ar') }}" 
                           placeholder="مثال: متر طولي" 
                           required>
                    @error('name_ar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Name EN -->
                <div class="col-md-6">
                    <label for="name_en" class="form-label">اسم الوحدة (بالإنجليزية) <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="name_en" 
                           id="name_en" 
                           class="form-control @error('name_en') is-invalid @enderror" 
                           value="{{ old('name_en') }}" 
                           placeholder="e.g. Linear Meter">
                    @error('name_en')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Unit Type -->
                <div class="col-md-6">
                    <label for="unit_type" class="form-label">طبيعة القياس / النوع</label>
                    <select name="unit_type" id="unit_type" class="form-select @error('unit_type') is-invalid @enderror">
                        <option value="">-- اختياري --</option>
                        <option value="LENGTH" {{ old('unit_type') === 'LENGTH' ? 'selected' : '' }}>أطوال (Length)</option>
                        <option value="AREA" {{ old('unit_type') === 'AREA' ? 'selected' : '' }}>مساحة (Area)</option>
                        <option value="VOLUME" {{ old('unit_type') === 'VOLUME' ? 'selected' : '' }}>حجم (Volume)</option>
                        <option value="WEIGHT" {{ old('unit_type') === 'WEIGHT' ? 'selected' : '' }}>وزن (Weight)</option>
                        <option value="COUNT" {{ old('unit_type') === 'COUNT' ? 'selected' : '' }}>عدد / حبات (Count/Piece)</option>
                        <option value="PACKAGING" {{ old('unit_type') === 'PACKAGING' ? 'selected' : '' }}>تغليف وتعبئة (Packaging)</option>
                    </select>
                    @error('unit_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Decimal Precision (Visible if allows decimal) -->
                <div class="col-md-6" x-show="allowsDecimal" x-cloak>
                    <label for="decimal_precision" class="form-label">عدد الخانات العشرية</label>
                    <input type="number" 
                           min="1" 
                           max="4" 
                           name="decimal_precision" 
                           id="decimal_precision" 
                           class="form-control @error('decimal_precision') is-invalid @enderror" 
                           value="{{ old('decimal_precision', 2) }}">
                    @error('decimal_precision')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Allows Decimal Switch -->
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="allows_decimal" 
                               id="allows_decimal" 
                               value="1" 
                               x-model="allowsDecimal"
                               {{ old('allows_decimal') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="allows_decimal">
                            تسمح هذه الوحدة بالكسور العشرية (مثل: 2.5 متر أو 1.75 كجم)
                        </label>
                    </div>
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            تفعيل الوحدة وإتاحتها في المواد الخام ومواصفات الإنتاج
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('units.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ وحدة القياس
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
