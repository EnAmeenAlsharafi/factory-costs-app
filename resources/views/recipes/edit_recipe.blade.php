@extends('layouts.app')

@section('title', 'تعديل بيانات وصفة التصنيع - ' . $recipe->recipe_code)

@section('content')
<div class="container-fluid px-4 py-4" x-data="recipeEditHeaderForm({{ json_encode([
    'targetType' => old('target_type', $recipe->target_type),
    'productConfigId' => old('product_configuration_id', $recipe->product_configuration_id),
    'componentId' => old('semi_finished_component_id', $recipe->semi_finished_component_id),
    'hasProductionHistory' => (bool) $hasProductionHistory,
]) }})">

    {{-- Breadcrumb Navigation --}}
    <div class="mb-3">
        <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted fs-7">
            <i class="fas fa-arrow-right me-1"></i> قائمة وصفات التصنيع
        </a>
        <span class="text-muted mx-2">/</span>
        <a href="{{ route('recipes.show', $recipe) }}" class="text-decoration-none text-muted fs-7">
            {{ $recipe->recipe_code }} ({{ $recipe->name }})
        </a>
        <span class="text-muted mx-2">/</span>
        <span class="text-dark fs-7 fw-semibold">تعديل البيانات الأساسية</span>
    </div>

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-dark font-monospace fs-6 px-3 py-1">{{ $recipe->recipe_code }}</span>
                @if($recipe->target_type === 'PRODUCT_CONFIGURATION')
                    <span class="badge bg-info text-dark">منتج نهائي</span>
                @else
                    <span class="badge bg-purple text-white" style="background-color: #6f42c1;">مكون نصف مصنع</span>
                @endif
                @if($recipe->is_active)
                    <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">معطل</span>
                @endif
            </div>
            <h1 class="h3 mb-0 text-dark fw-bold">تعديل بيانات وصفة التصنيع</h1>
            <p class="text-muted mb-0 fs-7">تحديث الاسم والوصف والهدف التشغيلي وحالة تنشيط الوصفة في النظام</p>
        </div>
        <div>
            <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i> إلغاء والعودة
            </a>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i> يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($hasProductionHistory)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="fas fa-lock me-2"></i>
            <strong>تنبيه ربط إنتاجي:</strong> هذه الوصفة مرتبطة بأوامر تصنيع أو متطلبات صرف أو طلبات عملاء سابقة. لحماية نزاهة وتاريخ التتبع الإنتاجي، تم قفل إمكانية تغيير الهدف المصنعي (الموديل/المكون)، ويمكنك تعديل الاسم والوصف وحالة التنشيط فقط.
        </div>
    @endif

    <form action="{{ route('recipes.update', $recipe) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="card-title mb-0 text-dark fw-bold">
                    <i class="fas fa-edit text-warning me-2"></i> بيانات الوصفة الأساسية
                </h5>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">

                    {{-- Target Type --}}
                    <div class="col-md-4">
                        <label class="form-label fw-bold fs-7">نوع الهدف المصنع (Target Type) <span class="text-danger">*</span></label>
                        <select name="target_type" class="form-select @error('target_type') is-invalid @enderror" x-model="targetType" :disabled="hasProductionHistory">
                            <option value="PRODUCT_CONFIGURATION">تكوين منتج نهائي (Product Configuration)</option>
                            <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع (Semi-Finished Component)</option>
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
                        <label class="form-label fw-bold fs-7">تكوين المنتج النهائي المستهدف <span class="text-danger">*</span></label>
                        <select name="product_configuration_id" class="form-select @error('product_configuration_id') is-invalid @enderror" x-model="productConfigId" :disabled="hasProductionHistory">
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
                        <label class="form-label fw-bold fs-7">المكون نصف المصنع المستهدف <span class="text-danger">*</span></label>
                        <select name="semi_finished_component_id" class="form-select @error('semi_finished_component_id') is-invalid @enderror" x-model="componentId" :disabled="hasProductionHistory">
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
                        <label class="form-label fw-bold fs-7">اسم الوصفة <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold @error('name') is-invalid @enderror" value="{{ old('name', $recipe->name) }}" placeholder="اسم الوصفة" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Active Status --}}
                    <div class="col-md-4">
                        <label class="form-label fw-bold fs-7">حالة التنشيط</label>
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActiveSwitch" name="is_active" value="1" {{ old('is_active', $recipe->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">الوصفة نشطة ومتاحة للاستخدام في الإنتاج</label>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="col-md-12">
                        <label class="form-label fw-bold fs-7">وصف وملاحظات فنية إضافية</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="ملاحظات فنية اختيارية حول استخدام هذه الوصفة...">{{ old('description', $recipe->description) }}</textarea>
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
