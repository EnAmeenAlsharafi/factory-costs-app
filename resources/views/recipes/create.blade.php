@extends('layouts.app')

@section('title', 'إنشاء وصفة تصنيع')

@section('content')
@php
    $initialTarget = [
        'targetType' => old('target_type', request('target_type') === 'SEMI_FINISHED_COMPONENT' ? 'SEMI_FINISHED_COMPONENT' : 'PRODUCT_CONFIGURATION'),
        'componentId' => (string) old('semi_finished_component_id', request('semi_finished_component_id', '')),
        'recipeName' => old('name', ''),
    ];
    $templatesWithItems = $templates->loadCount('items')->where('items_count', '>', 0);
@endphp
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'إنشاء وصفة تصنيع',
        'subtitle' => 'حدد لمن الوصفة ثم أدخل بنودها. تُحفظ كمسودة V1 ولا يستخدمها الإنتاج قبل الاعتماد.',
        'icon' => 'fas fa-scroll',
        'breadcrumbs' => [
            ['title' => 'وصفات التصنيع', 'url' => route('recipes.index')],
            ['title' => 'وصفة جديدة'],
        ],
    ])

    @if ($templatesWithItems->isNotEmpty())
        <aside class="alert alert-light border shadow-sm d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between mt-4 mb-0" aria-label="البدء من قالب">
            <div><i class="fas fa-layer-group text-warning" aria-hidden="true"></i> <strong>توفير وقت:</strong> لديك {{ $templatesWithItems->count() }} قالب جاهز ببنوده. ابدأ منه بدلاً من إدخال البنود يدوياً.</div>
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">اختيار قالب</button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    @foreach ($templatesWithItems as $tpl)
                        <li><a class="dropdown-item" href="{{ route('recipes.templates.show', [$tpl, 'open_modal' => 1]) }}">{{ $tpl->name_ar }} <span class="text-muted fs-8">({{ $tpl->items_count }} بند)</span></a></li>
                    @endforeach
                </ul>
            </div>
        </aside>
    @endif

    <form action="{{ route('recipes.store') }}" method="POST" class="mt-4" novalidate>
        @csrf

        <section class="card border-0 shadow-sm rounded-3 mb-4" aria-labelledby="recipe-basics-heading" x-data="recipeTargetForm(@js($initialTarget))">
            <div class="card-header bg-white py-3 border-bottom">
                <h2 id="recipe-basics-heading" class="h6 fw-bold text-dark mb-0"><i class="fas fa-circle-info text-warning me-2" aria-hidden="true"></i>لمن هذه الوصفة؟</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="form-label fw-bold fs-7 d-block" id="recipe-target-label">النوع <span class="text-danger" aria-hidden="true">*</span></span>
                        <div class="btn-group w-100" role="radiogroup" aria-labelledby="recipe-target-label">
                            <input type="radio" class="btn-check" name="target_type" id="recipe-target-product" value="PRODUCT_CONFIGURATION" x-model="targetType">
                            <label class="btn btn-outline-primary" for="recipe-target-product"><i class="fas fa-couch" aria-hidden="true"></i> منتج نهائي</label>
                            <input type="radio" class="btn-check" name="target_type" id="recipe-target-component" value="SEMI_FINISHED_COMPONENT" x-model="targetType">
                            <label class="btn btn-outline-component" for="recipe-target-component"><i class="fas fa-cubes" aria-hidden="true"></i> مكون</label>
                        </div>
                    </div>

                    <div class="col-md-8" x-show="targetType === 'PRODUCT_CONFIGURATION'" @configuration-selected="applySuggestedName($event.detail.suggestedName)">
                        <x-configuration-picker :configurations="$configurations" :selected="old('product_configuration_id')" id-prefix="recipe-cfg" />
                    </div>

                    <div class="col-md-8" x-show="targetType === 'SEMI_FINISHED_COMPONENT'" x-cloak>
                        <label for="recipe-component" class="form-label fw-bold fs-7">المكون نصف المصنع <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="recipe-component" name="semi_finished_component_id" class="form-select @error('semi_finished_component_id') is-invalid @enderror" x-model="componentId" @change="suggestName($event)">
                            <option value="">اختر المكون</option>
                            @foreach($components as $comp)
                                <option value="{{ $comp->id }}" data-name="وصفة مكون {{ $comp->name_ar }}">{{ $comp->name_ar }} ({{ $comp->component_code }})</option>
                            @endforeach
                        </select>
                        @error('semi_finished_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label for="recipe-name" class="form-label fw-bold fs-7">اسم الوصفة <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" id="recipe-name" name="name" class="form-control fw-bold @error('name') is-invalid @enderror" x-model="recipeName" placeholder="يُقترح تلقائياً عند اختيار المنتج أو المكون" required aria-describedby="recipe-name-hint">
                        <div id="recipe-name-hint" class="form-text">يمكنك تعديل الاسم المقترح.</div>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="recipe-version-notes" class="form-label fw-bold fs-7">ملاحظات V1 <span class="text-muted fw-normal">(اختياري)</span></label>
                        <input type="text" id="recipe-version-notes" name="version_notes" class="form-control" value="{{ old('version_notes', 'إصدار مسودة أولي') }}">
                    </div>

                    <div class="col-12">
                        <label for="recipe-description" class="form-label fw-bold fs-7">ملاحظات فنية <span class="text-muted fw-normal">(اختياري)</span></label>
                        <input type="text" id="recipe-description" name="description" class="form-control" value="{{ old('description') }}" placeholder="مثل: يُستخدم خشب الزان فقط لهذا الموديل">
                    </div>
                </div>
            </div>
        </section>

        <x-bom-editor :materials="$materials" :components="$components" :units="$units" :items="old('items', [])"
                      title="بنود الوصفة" hint="الكمية الصافية لوحدة واحدة من المنتج، ونسبة الهدر إن وجدت. اختيار المادة يملأ وحدتها الأساسية تلقائياً.">
            <x-slot:actions>
                <x-mobile-action-bar>
                    <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
                    <button type="submit" class="btn btn-warning px-4 fw-bold text-nowrap" :disabled="items.length === 0">
                        <i class="fas fa-save" aria-hidden="true"></i> حفظ المسودة
                    </button>
                </x-mobile-action-bar>
            </x-slot:actions>
        </x-bom-editor>
    </form>
</div>

<script>
function recipeTargetForm(initial) {
    return {
        ...initial,
        lastSuggestedName: '',
        suggestName(event) {
            this.applySuggestedName(event.target.selectedOptions[0]?.dataset.name);
        },
        applySuggestedName(suggested) {
            // Never overwrite a name the user typed themselves.
            if (suggested && (! this.recipeName || this.recipeName === this.lastSuggestedName)) {
                this.recipeName = suggested;
                this.lastSuggestedName = suggested;
            }
        },
    };
}
</script>
@endsection
