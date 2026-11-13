@extends('layouts.app')

@section('title', 'تعديل بيانات وصفة التصنيع - ' . $recipe->recipe_code)

@section('content')
<div class="container-fluid px-4 py-4" x-data="recipeEditHeaderForm({{ json_encode([
    'targetType' => old('target_type', $recipe->target_type),
    'productConfigId' => old('product_configuration_id', $recipe->product_configuration_id),
    'componentId' => old('semi_finished_component_id', $recipe->semi_finished_component_id),
    'hasProductionHistory' => (bool) $hasProductionHistory,
]) }})">

    @include('partials.page-header', [
        'title' => 'تعديل بيانات الوصفة',
        'subtitle' => $recipe->name . ' — الاسم والوصف والهدف وحالة التنشيط. بنود المواد تُعدّل من داخل إصدار مسودة.',
        'icon' => 'fas fa-pen',
        'breadcrumbs' => [
            ['title' => 'وصفات التصنيع', 'url' => route('recipes.index')],
            ['title' => $recipe->recipe_code, 'url' => route('recipes.show', $recipe)],
            ['title' => 'تعديل البيانات'],
        ],
    ])

    {{-- Flash messages and validation errors are rendered once by the layout (partials.flash). --}}
    <div class="mt-4"></div>

    @if($hasProductionHistory)
        <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex gap-2 align-items-start" role="status">
            <i class="fas fa-lock mt-1" aria-hidden="true"></i>
            <div><strong>الهدف مقفل:</strong> هذه الوصفة استُخدمت في أوامر تصنيع أو طلبات سابقة، لذلك لا يمكن تغيير المنتج أو المكون المرتبط بها حفاظاً على سجل الإنتاج. يمكنك تعديل الاسم والوصف والتنشيط.</div>
        </div>
    @endif

    <form action="{{ route('recipes.update', $recipe) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h2 class="h6 mb-0 text-dark fw-bold">
                    <i class="fas fa-circle-info text-warning me-2" aria-hidden="true"></i>بيانات الوصفة
                </h2>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    {{-- Target Type --}}
                    <div class="col-md-4">
                        <label for="edit-target-type" class="form-label fw-bold fs-7">نوع الهدف <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="edit-target-type" name="target_type" class="form-select @error('target_type') is-invalid @enderror" x-model="targetType" :disabled="hasProductionHistory">
                            <option value="PRODUCT_CONFIGURATION">تكوين منتج نهائي</option>
                            <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع</option>
                        </select>
                        @if($hasProductionHistory)
                            <input type="hidden" name="target_type" :value="targetType">
                            <small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i> مقفل لوجود سجلات إنتاج</small>
                        @endif
                        @error('target_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Product Configuration Selector --}}
                    <div class="col-md-8" x-show="targetType === 'PRODUCT_CONFIGURATION'">
                        <label for="edit-configuration" class="form-label fw-bold fs-7">تكوين المنتج <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="edit-configuration" name="product_configuration_id" class="form-select @error('product_configuration_id') is-invalid @enderror" x-model="productConfigId" :disabled="hasProductionHistory">
                            <option value="">-- اختر التكوين المصنعي --</option>
                            @foreach($configurations as $cfg)
                                <option value="{{ $cfg->id }}">
                                    {{ $cfg->productModel?->name_ar }} | {{ $cfg->width_cm }} × {{ $cfg->length_cm }} سم - {{ $cfg->has_storage ? 'مع تخزين' : 'بدون تخزين' }} ({{ $cfg->configuration_code }})
                                </option>
                            @endforeach
                        </select>
                        @if($hasProductionHistory)
                            <input type="hidden" name="product_configuration_id" :value="productConfigId">
                        @endif
                        @error('product_configuration_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Semi Finished Component Selector --}}
                    <div class="col-md-8" x-show="targetType === 'SEMI_FINISHED_COMPONENT'">
                        <label for="edit-component" class="form-label fw-bold fs-7">المكون نصف المصنع <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="edit-component" name="semi_finished_component_id" class="form-select @error('semi_finished_component_id') is-invalid @enderror" x-model="componentId" :disabled="hasProductionHistory">
                            <option value="">-- اختر المكون نصف المصنع --</option>
                            @foreach($components as $comp)
                                <option value="{{ $comp->id }}">
                                    {{ $comp->name_ar }} ({{ $comp->component_code }})
                                </option>
                            @endforeach
                        </select>
                        @if($hasProductionHistory)
                            <input type="hidden" name="semi_finished_component_id" :value="componentId">
                        @endif
                        @error('semi_finished_component_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Recipe Name --}}
                    <div class="col-md-8">
                        <label for="edit-name" class="form-label fw-bold fs-7">اسم الوصفة <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" id="edit-name" name="name" class="form-control fw-bold @error('name') is-invalid @enderror" value="{{ old('name', $recipe->name) }}" placeholder="اسم الوصفة" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Active Status --}}
                    <div class="col-md-4">
                        <span class="form-label fw-bold fs-7 d-block">حالة التنشيط</span>
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActiveSwitch" name="is_active" value="1" {{ old('is_active', $recipe->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">الوصفة نشطة ومتاحة للاستخدام في الإنتاج</label>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="col-md-12">
                        <label for="edit-description" class="form-label fw-bold fs-7">ملاحظات فنية <span class="text-muted fw-normal">(اختياري)</span></label>
                        <textarea id="edit-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="ملاحظات فنية اختيارية حول استخدام هذه الوصفة...">{{ old('description', $recipe->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i> إلغاء والعودة
                </a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="fas fa-save me-1"></i> حفظ التعديلات
                </button>
            </div>
        </div>

    </form>

</div>

<script>
function recipeEditHeaderForm(initialData) {
    return {
        targetType: initialData.targetType,
        productConfigId: initialData.productConfigId,
        componentId: initialData.componentId,
        hasProductionHistory: initialData.hasProductionHistory,
    };
}
</script>
@endsection
