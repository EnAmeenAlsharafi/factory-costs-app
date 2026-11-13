{{-- Shared create/edit form for a manufacturing template. $template is null when creating. --}}
<form action="{{ $template ? route('recipes.templates.update', $template) : route('recipes.templates.store') }}" method="POST" novalidate>
    @csrf
    @if ($template)
        @method('PUT')
    @endif

    <section class="card border-0 shadow-sm rounded-3 mb-4" aria-labelledby="template-basics-heading">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 id="template-basics-heading" class="h6 fw-bold text-dark mb-0">
                <i class="fas fa-circle-info text-warning me-2" aria-hidden="true"></i>بيانات القالب
            </h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="{{ $template ? 'col-md-5' : 'col-md-6' }}">
                    <label for="template-name-ar" class="form-label fw-bold fs-7">اسم القالب بالعربية <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="template-name-ar" name="name_ar" class="form-control fw-bold @error('name_ar') is-invalid @enderror"
                           value="{{ old('name_ar', $template?->name_ar) }}" placeholder="مثل: قالب سرير قياسي" required aria-required="true">
                    @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="{{ $template ? 'col-md-4' : 'col-md-6' }}">
                    <label for="template-name-en" class="form-label fw-bold fs-7">الاسم بالإنجليزية <span class="text-muted fw-normal">(اختياري)</span></label>
                    <input type="text" id="template-name-en" name="name_en" dir="ltr" class="form-control @error('name_en') is-invalid @enderror"
                           value="{{ old('name_en', $template?->name_en) }}" placeholder="Standard bed template">
                    @error('name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if ($template)
                    <div class="col-md-3">
                        <label for="template-status" class="form-label fw-bold fs-7">الحالة</label>
                        <select id="template-status" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $template->is_active))>نشط — يظهر عند إنشاء الوصفات</option>
                            <option value="0" @selected(! old('is_active', $template->is_active))>معطل — مخفي من الاختيار</option>
                        </select>
                    </div>
                @endif
                <div class="col-12">
                    <label for="template-description" class="form-label fw-bold fs-7">متى يُستخدم هذا القالب؟ <span class="text-muted fw-normal">(اختياري)</span></label>
                    <input type="text" id="template-description" name="description" class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description', $template?->description) }}" placeholder="مثل: لكل أسرّة الخشب الزان بمقاس 160 فأكثر">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>

    <x-bom-editor :materials="$materials" :components="$components" :units="$units" :items="old('items', $initialItems)"
                  title="بنود القالب" hint="هذه البنود تُنسخ كما هي إلى الوصفة الجديدة، ويمكن تعديلها هناك قبل الاعتماد.">
        <x-slot:actions>
            <x-mobile-action-bar>
                <a href="{{ $template ? route('recipes.templates.show', $template) : route('recipes.templates.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
                <button type="submit" class="btn btn-warning px-4 fw-bold" :disabled="items.length === 0">
                    <i class="fas fa-save" aria-hidden="true"></i> {{ $template ? 'حفظ' : 'حفظ القالب' }}
                </button>
            </x-mobile-action-bar>
        </x-slot:actions>
    </x-bom-editor>
</form>
