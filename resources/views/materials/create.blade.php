@extends('layouts.app')

@section('title', 'إضافة مادة خام جديدة - مصنع مفروشات سدير')
@section('page-title', 'إضافة مادة خام جديدة')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => 'إضافة مادة خام جديدة',
        'subtitle' => 'تعريف خامة جديدة في النظام مع توصيفها الفني التابع للتصنيف (أخشاب، إسفنج، أقمشة).',
        'icon' => 'fas fa-plus-circle',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'كتالوج المواد الخام', 'url' => route('materials.index')],
            ['title' => 'إضافة مادة']
        ]
    ])

    <form action="{{ route('materials.store') }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-info-circle text-primary me-2"></i>البيانات الأساسية للمادة</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="code" class="form-label fw-semibold text-dark">كود المادة <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $generatedCode) }}" required dir="ltr">
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="material_category_id" class="form-label fw-semibold text-dark">تصنيف المادة <span class="text-danger">*</span></label>
                        <select name="material_category_id" id="material_category_id" class="form-select @error('material_category_id') is-invalid @enderror" required>
                            <option value="">-- اختر التصنيف --</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" data-code="{{ $cat->code }}" {{ old('material_category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name_ar }} ({{ $cat->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('material_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="base_unit_id" class="form-label fw-semibold text-dark">الوحدة الأساسية للمخزون <span class="text-danger">*</span></label>
                        <select name="base_unit_id" id="base_unit_id" class="form-select @error('base_unit_id') is-invalid @enderror" required>
                            <option value="">-- اختر وحدة الصرف والتصنيع --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" {{ old('base_unit_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('base_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="purchase_unit_id" class="form-label fw-semibold text-dark">وحدة الشراء (افتراضية)</label>
                        <select name="purchase_unit_id" id="purchase_unit_id" class="form-select @error('purchase_unit_id') is-invalid @enderror">
                            <option value="">-- نفس الوحدة الأساسية --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" {{ old('purchase_unit_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('purchase_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_ar" class="form-label fw-semibold text-dark">اسم المادة (عربي) <span class="text-danger">*</span></label>
                        <input type="text" name="name_ar" id="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" placeholder="مثال: خشب زان روماني 25 مم" required>
                        @error('name_ar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="name_en" class="form-label fw-semibold text-dark">اسم المادة (إنجليزية)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en') }}" placeholder="e.g. Romanian Beech Wood 25mm" dir="ltr">
                        @error('name_en')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="min_stock_level" class="form-label fw-semibold text-dark">الحد الأدنى للمخزون (Min Stock)</label>
                        <input type="number" step="0.001" name="min_stock_level" id="min_stock_level" class="form-control @error('min_stock_level') is-invalid @enderror" value="{{ old('min_stock_level', 0) }}" min="0">
                        @error('min_stock_level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="reorder_point" class="form-label fw-semibold text-dark">نقطة إعادة الطلب (Reorder Point)</label>
                        <input type="number" step="0.001" name="reorder_point" id="reorder_point" class="form-control @error('reorder_point') is-invalid @enderror" value="{{ old('reorder_point', 0) }}" min="0">
                        @error('reorder_point')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold text-dark me-2" for="is_active">تفعيل المادة الخام</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label fw-semibold text-dark">ملاحظات إضافية</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror" placeholder="أي ملاحظات فنية أو تعليمات خاصة بالمادة">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Wood Specs Section -->
        <div class="card border-0 shadow-sm mb-4 spec-section d-none" id="spec-wood">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-tree text-success me-2"></i>المواصفات الفنية للأخشاب والألواح</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="wood_type" class="form-label fw-semibold text-dark">نوع الخشب <span class="text-danger">*</span></label>
                        <input type="text" name="wood_type" id="wood_type" class="form-control @error('wood_type') is-invalid @enderror" value="{{ old('wood_type') }}" placeholder="مثال: زان، سويدي، MDF، أبلكاش">
                        @error('wood_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="grade" class="form-label fw-semibold text-dark">الدرجة / الجودة (Grade)</label>
                        <input type="text" name="grade" id="grade" class="form-control @error('grade') is-invalid @enderror" value="{{ old('grade') }}" placeholder="مثال: فرز أول، A, Super">
                        @error('grade')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="wood_thickness_mm" class="form-label fw-semibold text-dark">السمك (مم) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="thickness_mm" id="wood_thickness_mm" class="form-control @error('thickness_mm') is-invalid @enderror" value="{{ old('thickness_mm') }}" placeholder="مثال: 18">
                        @error('thickness_mm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="wood_width_cm" class="form-label fw-semibold text-dark">العرض (سم) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="width_cm" id="wood_width_cm" class="form-control @error('width_cm') is-invalid @enderror" value="{{ old('width_cm') }}" placeholder="مثال: 122">
                        @error('width_cm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="length_cm" class="form-label fw-semibold text-dark">الطول (سم) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="length_cm" id="length_cm" class="form-control @error('length_cm') is-invalid @enderror" value="{{ old('length_cm') }}" placeholder="مثال: 244">
                        @error('length_cm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Foam Specs Section -->
        <div class="card border-0 shadow-sm mb-4 spec-section d-none" id="spec-foam">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-cubes text-warning me-2"></i>المواصفات الفنية للإسفنج والكتل</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="foam_type" class="form-label fw-semibold text-dark">نوع / درجة الإسفنج <span class="text-danger">*</span></label>
                        <input type="text" name="foam_type" id="foam_type" class="form-control @error('foam_type') is-invalid @enderror" value="{{ old('foam_type') }}" placeholder="مثال: ضغط 30 ممتاز، إسفنج ميغافوم">
                        @error('foam_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="density_kg_m3" class="form-label fw-semibold text-dark">الكثافة (كجم/م³)</label>
                        <input type="number" step="0.01" name="density_kg_m3" id="density_kg_m3" class="form-control @error('density_kg_m3') is-invalid @enderror" value="{{ old('density_kg_m3') }}" placeholder="مثال: 30">
                        @error('density_kg_m3')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="foam_thickness_mm" class="form-label fw-semibold text-dark">السمك (مم) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="thickness_mm" id="foam_thickness_mm" class="form-control @error('thickness_mm') is-invalid @enderror" value="{{ old('thickness_mm') }}" placeholder="مثال: 100">
                        @error('thickness_mm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="foam_width_cm" class="form-label fw-semibold text-dark">العرض الرقمي (سم)</label>
                        <input type="number" step="0.01" name="width_cm" id="foam_width_cm" class="form-control @error('width_cm') is-invalid @enderror" value="{{ old('width_cm') }}" placeholder="مثال: 120">
                        @error('width_cm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="foam_length_cm" class="form-label fw-semibold text-dark">الطول الرقمي (سم)</label>
                        <input type="number" step="0.01" name="length_cm" id="foam_length_cm" class="form-control @error('length_cm') is-invalid @enderror" value="{{ old('length_cm') }}" placeholder="مثال: 200">
                        @error('length_cm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="hardness_rating" class="form-label fw-semibold text-dark">درجة القساوة / الصلابة</label>
                        <input type="text" name="hardness_rating" id="hardness_rating" class="form-control @error('hardness_rating') is-invalid @enderror" value="{{ old('hardness_rating') }}" placeholder="مثال: متوسط، قاسي، طري">
                        @error('hardness_rating')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="block_dimensions" class="form-label fw-semibold text-dark">وصف أبعاد البلوك الإضافية</label>
                        <input type="text" name="block_dimensions" id="block_dimensions" class="form-control @error('block_dimensions') is-invalid @enderror" value="{{ old('block_dimensions') }}" placeholder="ملاحظات نصية اختيارية لأبعاد البلوك e.g. 200×120">
                        @error('block_dimensions')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Fabric Specs Section -->
        <div class="card border-0 shadow-sm mb-4 spec-section d-none" id="spec-fabric">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-scroll text-info me-2"></i>المواصفات الفنية للأقمشة والجلد</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="fabric_type" class="form-label fw-semibold text-dark">نوع القماش / الخامة <span class="text-danger">*</span></label>
                        <input type="text" name="fabric_type" id="fabric_type" class="form-control @error('fabric_type') is-invalid @enderror" value="{{ old('fabric_type') }}" placeholder="مثال: مخمل، شانيل، خيش، جلد طبيعي">
                        @error('fabric_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="fabric_width_cm" class="form-label fw-semibold text-dark">عرض الرول (سم) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="width_cm" id="fabric_width_cm" class="form-control @error('width_cm') is-invalid @enderror" value="{{ old('width_cm') }}" placeholder="مثال: 140">
                        @error('width_cm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="pattern_type" class="form-label fw-semibold text-dark">نوع النقش / النمط (اختياري)</label>
                        <input type="text" name="pattern_type" id="pattern_type" class="form-control @error('pattern_type') is-invalid @enderror" value="{{ old('pattern_type') }}" placeholder="مثال: سادة، مشجر، مقلم">
                        @error('pattern_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="weight_gsm" class="form-label fw-semibold text-dark">الوزن النوعي GSM (اختياري)</label>
                        <input type="number" step="0.01" name="weight_gsm" id="weight_gsm" class="form-control @error('weight_gsm') is-invalid @enderror" value="{{ old('weight_gsm') }}" placeholder="مثال: 350">
                        @error('weight_gsm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="composition" class="form-label fw-semibold text-dark">التركيب الخامي (اختياري)</label>
                        <input type="text" name="composition" id="composition" class="form-control @error('composition') is-invalid @enderror" value="{{ old('composition') }}" placeholder="مثال: 100% بوليستر">
                        @error('composition')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3">
            <a href="{{ route('materials.index') }}" class="btn btn-light border">إلغاء</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> حفظ المادة الخام
            </button>
        </div>
    </form>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const categorySelect = document.getElementById('material_category_id');
        const specSections = {
            'WOOD': document.getElementById('spec-wood'),
            'FOAM': document.getElementById('spec-foam'),
            'FABRIC': document.getElementById('spec-fabric')
        };

        function updateSpecVisibility() {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const categoryCode = selectedOption ? selectedOption.getAttribute('data-code') : '';

            Object.keys(specSections).forEach(code => {
                const section = specSections[code];
                if (section) {
                    if (code === categoryCode) {
                        section.classList.remove('d-none');
                        section.querySelectorAll('input, select').forEach(el => el.removeAttribute('disabled'));
                    } else {
                        section.classList.add('d-none');
                        section.querySelectorAll('input, select').forEach(el => el.setAttribute('disabled', 'disabled'));
                    }
                }
            });
        }

        categorySelect.addEventListener('change', updateSpecVisibility);
        updateSpecVisibility();
    });
</script>
@endpush
@endsection
