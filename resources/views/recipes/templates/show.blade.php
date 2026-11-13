@extends('layouts.app')

@section('title', 'القالب ' . $template->template_code)

@section('content')
@php
    $materialLines = $template->items->where('item_type', 'MATERIAL')->count();
    $componentLines = $template->items->count() - $materialLines;
    $wastedLines = $template->items->where('waste_percentage', '>', 0)->count();
    $canCreateRecipe = $template->is_active && $template->items->isNotEmpty();
    $modelsInConfig = $configurations->pluck('productModel')->filter()->unique('id')->sortBy('name_ar');
@endphp
<div class="container-fluid px-4 py-4" x-data="{ showRecipeModal: {{ (request('open_modal') || old('target_type')) && $canCreateRecipe ? 'true' : 'false' }} }">

    <div class="page-header-card mb-4">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0 fs-8 text-muted">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="{{ route('recipes.templates.index') }}" class="text-decoration-none text-secondary">قوالب التصنيع</a></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $template->template_code }}</li>
            </ol>
        </nav>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="code-chip code-chip-lg">{{ $template->template_code }}</span>
                    @include('recipes.partials.active-chip', ['active' => $template->is_active])
                </div>
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1">{{ $template->name_ar }}</h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">{{ $template->description ?: 'لا يوجد وصف لاستخدام هذا القالب.' }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                @can('manufacturing_templates.manage')
                    <a href="{{ route('recipes.templates.edit', $template) }}" class="btn btn-outline-secondary fw-semibold">
                        <i class="fas fa-pen" aria-hidden="true"></i> تعديل القالب
                    </a>
                @endcan
                @can('recipes.manage')
                    @if ($canCreateRecipe)
                        <button type="button" @click="showRecipeModal = true" class="btn btn-warning fw-bold">
                            <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> إنشاء وصفة من القالب
                        </button>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @if (! $template->is_active)
        <div class="alert alert-warning border-0 shadow-sm d-flex gap-2 align-items-start" role="status">
            <i class="fas fa-circle-pause mt-1" aria-hidden="true"></i>
            <div>هذا القالب معطل، لذلك لا يمكن إنشاء وصفات منه. فعّله من «تعديل القالب» لاستخدامه مجدداً.</div>
        </div>
    @endif

    <div class="spec-summary mb-4">
        <div class="metric-tile">
            <span class="metric-tile-icon bg-light text-dark"><i class="fas fa-list-ol" aria-hidden="true"></i></span>
            <div><span class="metric-tile-label">إجمالي البنود</span><span class="metric-tile-value">{{ $template->items->count() }}</span></div>
        </div>
        <div class="metric-tile">
            <span class="metric-tile-icon kind-chip-material"><i class="fas fa-cube" aria-hidden="true"></i></span>
            <div><span class="metric-tile-label">مواد خام</span><span class="metric-tile-value">{{ $materialLines }}</span></div>
        </div>
        <div class="metric-tile">
            <span class="metric-tile-icon kind-chip-component"><i class="fas fa-cubes" aria-hidden="true"></i></span>
            <div><span class="metric-tile-label">مكونات نصف مصنعة</span><span class="metric-tile-value">{{ $componentLines }}</span></div>
        </div>
        <div class="metric-tile">
            <span class="metric-tile-icon bg-danger-subtle text-danger"><i class="fas fa-percent" aria-hidden="true"></i></span>
            <div><span class="metric-tile-label">بنود عليها هدر</span><span class="metric-tile-value">{{ $wastedLines }}</span></div>
        </div>
    </div>

    <section class="card border-0 shadow-sm rounded-3 mb-4" aria-labelledby="template-items-heading">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 id="template-items-heading" class="h6 fw-bold text-dark mb-0"><i class="fas fa-boxes-stacked text-warning me-2" aria-hidden="true"></i>بنود القالب</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">المادة / المكون</th>
                            <th>النوع</th>
                            <th class="text-end">الكمية الصافية</th>
                            <th>الوحدة</th>
                            <th class="text-end">الهدر</th>
                            <th class="text-end">الكمية المخططة</th>
                            <th class="pe-3">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($template->items as $item)
                            @php
                                $source = $item->item_type === 'MATERIAL' ? $item->material : $item->semiFinishedComponent;
                                $sourceCode = $item->item_type === 'MATERIAL' ? $item->material?->code : $item->semiFinishedComponent?->component_code;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $source?->name_ar ?? 'عنصر محذوف' }}</div>
                                    @if ($sourceCode)<span class="code-chip fs-8">{{ $sourceCode }}</span>@endif
                                </td>
                                <td>@include('recipes.partials.kind-chip', ['type' => $item->item_type])</td>
                                <td class="text-end"><span class="qty">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') }}</span></td>
                                <td class="fs-7">{{ $item->unit?->name_ar ?? '—' }}</td>
                                <td class="text-end">
                                    @if($item->waste_percentage > 0)
                                        <span class="status-chip status-chip-danger"><span class="qty">{{ number_format($item->waste_percentage, 1) }}%</span></span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end"><span class="qty qty-planned">{{ rtrim(rtrim(number_format($item->planned_quantity, 4), '0'), '.') }}</span></td>
                                <td class="pe-3 fs-7 text-muted">{{ $item->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr class="table-stack-empty">
                                <td colspan="7" class="empty-state" role="status">
                                    <p class="mb-0">لا توجد بنود في هذا القالب، لذلك لا يمكن إنشاء وصفة منه بعد.</p>
                                    @can('manufacturing_templates.manage')
                                        <div class="empty-state-cta"><a href="{{ route('recipes.templates.edit', $template) }}" class="btn btn-outline-primary">إضافة بنود</a></div>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @can('recipes.manage')
        @if ($canCreateRecipe)
            {{-- Create a recipe from this template --}}
            <div class="modal fade show" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="create-recipe-title" style="background: rgba(15,23,42,.55);"
                 x-show="showRecipeModal" :class="{ 'd-block': showRecipeModal }" @click.self="showRecipeModal = false" @keydown.escape.window="showRecipeModal = false"
                 x-effect="showRecipeModal && $nextTick(() => document.getElementById('target-product')?.focus())" x-cloak>
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('recipes.templates.create-recipe', $template) }}" method="POST" class="d-flex flex-column h-100"
                              x-data="{ targetType: @js(old('target_type', 'PRODUCT_CONFIGURATION')), selectedModelId: '' }">
                            @csrf
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title h5 fw-bold" id="create-recipe-title">إنشاء وصفة من القالب</h2>
                                    <p class="text-muted fs-7 mb-0">ستُنسخ {{ $template->items->count() }} بنود إلى مسودة V1 يمكنك مراجعتها قبل الاعتماد.</p>
                                </div>
                                <button type="button" class="btn-close" @click="showRecipeModal = false" aria-label="إغلاق"></button>
                            </div>
                            <div class="modal-body">
                                <fieldset class="mb-3">
                                    <legend class="form-label fw-bold fs-7">لمن هذه الوصفة؟</legend>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="target_type" id="target-product" value="PRODUCT_CONFIGURATION" x-model="targetType">
                                        <label class="btn btn-outline-primary" for="target-product"><i class="fas fa-couch" aria-hidden="true"></i> منتج نهائي</label>
                                        <input type="radio" class="btn-check" name="target_type" id="target-component" value="SEMI_FINISHED_COMPONENT" x-model="targetType">
                                        <label class="btn btn-outline-component" for="target-component"><i class="fas fa-cubes" aria-hidden="true"></i> مكون نصف مصنع</label>
                                    </div>
                                </fieldset>

                                <div x-show="targetType === 'PRODUCT_CONFIGURATION'">
                                    <div class="mb-3">
                                        <label for="recipe-model-filter" class="form-label fw-bold fs-7">الموديل <span class="text-muted fw-normal">(لتضييق القائمة)</span></label>
                                        <select id="recipe-model-filter" class="form-select" x-model="selectedModelId">
                                            <option value="">جميع الموديلات</option>
                                            @foreach($modelsInConfig as $mod)
                                                <option value="{{ $mod->id }}">{{ $mod->name_ar }} ({{ $mod->model_code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="recipe-configuration" class="form-label fw-bold fs-7">تكوين المنتج <span class="text-danger" aria-hidden="true">*</span></label>
                                        <select id="recipe-configuration" name="product_configuration_id" class="form-select @error('product_configuration_id') is-invalid @enderror" :required="targetType === 'PRODUCT_CONFIGURATION'">
                                            <option value="">اختر التكوين</option>
                                            @foreach($configurations as $cfg)
                                                <option value="{{ $cfg->id }}" @selected(old('product_configuration_id') == $cfg->id) x-show="!selectedModelId || selectedModelId == '{{ $cfg->product_model_id }}'">
                                                    {{ $cfg->productModel?->name_ar }} — {{ number_format($cfg->width_cm, 0) }}×{{ number_format($cfg->length_cm, 0) }} سم — {{ $cfg->has_storage ? 'مع تخزين' : 'بدون تخزين' }} ({{ $cfg->configuration_code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('product_configuration_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="mb-3" x-show="targetType === 'SEMI_FINISHED_COMPONENT'" x-cloak>
                                    <label for="recipe-component" class="form-label fw-bold fs-7">المكون نصف المصنع <span class="text-danger" aria-hidden="true">*</span></label>
                                    <select id="recipe-component" name="semi_finished_component_id" class="form-select @error('semi_finished_component_id') is-invalid @enderror" :required="targetType === 'SEMI_FINISHED_COMPONENT'">
                                        <option value="">اختر المكون</option>
                                        @foreach($components as $comp)
                                            <option value="{{ $comp->id }}" @selected(old('semi_finished_component_id') == $comp->id)>{{ $comp->name_ar }} ({{ $comp->component_code }})</option>
                                        @endforeach
                                    </select>
                                    @error('semi_finished_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div>
                                    <label for="recipe-name" class="form-label fw-bold fs-7">اسم الوصفة <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input type="text" id="recipe-name" name="name" class="form-control fw-bold @error('name') is-invalid @enderror" value="{{ old('name', 'وصفة '.$template->name_ar) }}" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" @click="showRecipeModal = false">إلغاء</button>
                                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> إنشاء المسودة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endcan

</div>
@endsection
