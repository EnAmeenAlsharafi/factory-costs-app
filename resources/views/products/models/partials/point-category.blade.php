{{-- Points category: how much this model earns the workers who make it (worker points and monthly rewards). --}}
@php $pointCategories = \App\Models\ProductionPointCategory::active()->ordered()->get(); @endphp
<div class="mb-3">
    <label class="form-label fw-semibold" for="point_category_id">تصنيف النقاط</label>
    <select class="form-select @error('point_category_id') is-invalid @enderror" id="point_category_id" name="point_category_id" aria-describedby="point_category_help">
        <option value="">— بلا تصنيف —</option>
        @foreach ($pointCategories as $pointCategory)
            <option value="{{ $pointCategory->id }}" @selected((string) old('point_category_id', $selected ?? '') === (string) $pointCategory->id)>{{ $pointCategory->label }}</option>
        @endforeach
    </select>
    <small id="point_category_help" class="text-muted d-block mt-1">يحدد نقاط العامل عن كل قطعة ينجزها من هذا الموديل في أقسام النقاط (مثل التنجيد).</small>
    @error('point_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
