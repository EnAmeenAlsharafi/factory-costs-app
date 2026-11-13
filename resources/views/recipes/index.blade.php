@extends('layouts.app')

@section('title', 'وصفات التصنيع')

@section('content')
@php
    $activeFilterCount = collect(['target_type', 'model_id'])->filter(fn (string $key): bool => request()->filled($key))->count();
    $targetTypes = ['PRODUCT_CONFIGURATION' => 'منتج نهائي', 'SEMI_FINISHED_COMPONENT' => 'مكون نصف مصنع'];
@endphp
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'وصفات التصنيع',
        'subtitle' => 'قائمة المواد لكل منتج أو مكون، بإصدارات تُعتمد قبل أن يستخدمها الإنتاج.',
        'icon' => 'fas fa-scroll',
        'breadcrumbs' => [['title' => 'الوصفات والقوالب'], ['title' => 'وصفات التصنيع']],
        'actionUrl' => route('recipes.create'),
        'actionText' => 'وصفة جديدة',
        'actionPermission' => 'recipes.manage',
    ])

    {{-- Flash messages are rendered once by the layout (partials.flash). --}}

    <div class="mt-3">
        <x-filter-sheet id="recipes-filters" :action="route('recipes.index')" :active-count="$activeFilterCount" :reset-url="route('recipes.index', request()->only('search'))">
            <x-slot:search>
                <label for="recipes-search" class="visually-hidden">بحث في الوصفات</label>
                <input type="search" id="recipes-search" name="search" class="form-control" inputmode="search" enterkeyhint="search" autocomplete="off"
                       placeholder="ابحث بالكود أو اسم الوصفة أو الموديل أو المكون" value="{{ request('search') }}">
            </x-slot:search>
            <div class="col-12 col-md-4">
                <label for="recipes-target" class="form-label fs-7">النوع</label>
                <select name="target_type" id="recipes-target" class="form-select">
                    <option value="">جميع الأنواع</option>
                    @foreach ($targetTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('target_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="recipes-model" class="form-label fs-7">الموديل</label>
                <select name="model_id" id="recipes-model" class="form-select">
                    <option value="">جميع الموديلات</option>
                    @foreach($productModels as $model)
                        <option value="{{ $model->id }}" @selected(request('model_id') == $model->id)>{{ $model->name_ar }}</option>
                    @endforeach
                </select>
            </div>
        </x-filter-sheet>
    </div>

    @if ($activeFilterCount > 0 || request()->filled('search'))
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fs-7" aria-label="الفلاتر النشطة">
            @if (request()->filled('search'))<span class="status-chip status-chip-secondary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> {{ request('search') }}</span>@endif
            @if (request()->filled('target_type'))<span class="status-chip status-chip-secondary">{{ $targetTypes[request('target_type')] ?? request('target_type') }}</span>@endif
            @if (request()->filled('model_id'))<span class="status-chip status-chip-secondary">{{ $productModels->firstWhere('id', (int) request('model_id'))?->name_ar }}</span>@endif
            <span class="text-muted">{{ $recipes->total() }} نتيجة</span>
            <a href="{{ route('recipes.index') }}" class="link-secondary">مسح الكل</a>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">الوصفة</th>
                            <th>النوع</th>
                            <th>الإصدار المعتمد</th>
                            <th>الإصدارات</th>
                            <th>الحالة</th>
                            <th>آخر تحديث</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipes as $recipe)
                            @php
                                $configuration = $recipe->productConfiguration;
                                $draftCount = $recipe->versions->where('status', 'DRAFT')->count();
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('recipes.show', $recipe) }}" class="fw-bold text-dark text-decoration-none">{{ $recipe->name }}</a>
                                    <div class="d-flex flex-wrap align-items-center gap-2 fs-8 text-muted mt-1">
                                        <span class="code-chip">{{ $recipe->recipe_code }}</span>
                                        @if($recipe->target_type === 'PRODUCT_CONFIGURATION' && $configuration)
                                            <span>{{ $configuration->productModel?->name_ar }} · <span class="qty">{{ (float) $configuration->width_cm }}×{{ (float) $configuration->length_cm }}</span> سم{{ $configuration->has_storage ? ' · مع تخزين' : '' }}</span>
                                        @elseif($recipe->target_type === 'SEMI_FINISHED_COMPONENT' && $recipe->semiFinishedComponent)
                                            <span>{{ $recipe->semiFinishedComponent->name_ar }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>@include('recipes.partials.kind-chip', ['type' => $recipe->target_type === 'PRODUCT_CONFIGURATION' ? 'PRODUCT' : 'SEMI_FINISHED_COMPONENT'])</td>
                                <td>
                                    @if($recipe->currentApprovedVersion)
                                        <span class="status-chip status-chip-success"><i class="fas fa-circle-check" aria-hidden="true"></i> V{{ $recipe->currentApprovedVersion->version_number }} معتمد</span>
                                    @else
                                        <span class="status-chip status-chip-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> غير معتمدة</span>
                                    @endif
                                </td>
                                <td class="fs-7">
                                    {{ $recipe->versions->count() }} إصدار
                                    @if ($draftCount)
                                        <div class="text-warning-emphasis fs-8 fw-semibold">{{ $draftCount }} مسودة قيد الإعداد</div>
                                    @endif
                                </td>
                                <td>@include('recipes.partials.active-chip', ['active' => $recipe->is_active])</td>
                                <td class="fs-7 text-muted"><time datetime="{{ $recipe->updated_at->toIso8601String() }}" class="ltr-isolate">{{ $recipe->updated_at->format('Y-m-d') }}</time></td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-sm btn-outline-primary">عرض</a>
                                        @can('recipes.manage')
                                            <a href="{{ route('recipes.edit', $recipe) }}" class="btn btn-sm btn-outline-secondary" aria-label="تعديل بيانات الوصفة {{ $recipe->recipe_code }}" title="تعديل البيانات">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table-stack-empty">
                                <td colspan="7" class="empty-state" role="status">
                                    <i class="fas fa-scroll fs-2 d-block mb-3 opacity-50" aria-hidden="true"></i>
                                    @if ($activeFilterCount > 0 || request()->filled('search'))
                                        <p class="mb-0">لا توجد وصفات تطابق البحث أو الفلاتر الحالية.</p>
                                        <div class="empty-state-cta"><a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a></div>
                                    @else
                                        <p class="fw-bold text-dark mb-1">لا توجد وصفات تصنيع بعد</p>
                                        <p class="mb-0">الوصفة تحدد المواد والكميات لكل منتج، ويستخدم الإنتاج الإصدار المعتمد منها.</p>
                                        @can('recipes.manage')
                                            <div class="empty-state-cta">
                                                <a href="{{ route('recipes.create') }}" class="btn btn-warning fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> إنشاء وصفة</a>
                                                <a href="{{ route('recipes.templates.index') }}" class="btn btn-outline-secondary">البدء من قالب</a>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($recipes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
