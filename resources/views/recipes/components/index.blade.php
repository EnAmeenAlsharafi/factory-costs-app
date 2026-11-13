@extends('layouts.app')

@section('title', 'المكونات نصف المصنعة')

@section('content')
@php
    // Reopen the form that failed validation, with what the user typed.
    $reopenForm = old('_form');
    $oldComponent = $reopenForm === 'edit' ? [
        'id' => old('_component_id'),
        'name_ar' => old('name_ar'),
        'name_en' => old('name_en'),
        'width_cm' => old('width_cm'),
        'length_cm' => old('length_cm'),
        'has_storage' => (bool) old('has_storage'),
        'notes' => old('notes'),
    ] : (object) [];
@endphp
<div class="container-fluid px-4 py-4"
     x-data="{ showCreateModal: @js($reopenForm === 'create'), showEditModal: @js($reopenForm === 'edit'), editComponent: @js($oldComponent) }">

    <div class="page-header-card mb-4">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0 fs-8 text-muted">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">الرئيسية</a></li>
                <li class="breadcrumb-item">الوصفات والقوالب</li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">المكونات نصف المصنعة</li>
            </ol>
        </nav>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-cubes text-warning fs-5" aria-hidden="true"></i><span>المكونات نصف المصنعة</span>
                </h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">أجزاء تُصنع مرة وتدخل في أكثر من منتج (مثل بوكس السرير العادي أو المزود بتخزين). لكل مكون وصفته الخاصة.</p>
            </div>
            @can('semi_finished_components.manage')
                <button type="button" @click="showCreateModal = true" class="btn btn-factory-warning shadow-sm fs-7 flex-shrink-0">
                    <i class="fas fa-plus" aria-hidden="true"></i> مكون جديد
                </button>
            @endcan
        </div>
    </div>

    {{-- Flash messages are rendered once by the layout (partials.flash). --}}

    <form method="GET" action="{{ route('recipes.components.index') }}" role="search" class="d-flex gap-2 mb-3">
        <label for="components-search" class="visually-hidden">بحث في المكونات</label>
        <input type="search" id="components-search" name="search" class="form-control" inputmode="search" enterkeyhint="search" autocomplete="off"
               placeholder="ابحث باسم المكون أو الكود" value="{{ request('search') }}">
        <button type="submit" class="btn btn-factory-primary flex-shrink-0"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> بحث</button>
    </form>

    @if (request()->filled('search'))
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fs-7" aria-label="البحث النشط">
            <span class="status-chip status-chip-secondary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> {{ request('search') }}</span>
            <span class="text-muted">{{ $components->total() }} نتيجة</span>
            <a href="{{ route('recipes.components.index') }}" class="link-secondary">مسح البحث</a>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">المكون</th>
                            <th>الأبعاد</th>
                            <th>التخزين</th>
                            <th>وصفة التصنيع</th>
                            <th>الحالة</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($components as $comp)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $comp->name_ar }}</div>
                                    <span class="code-chip fs-8">{{ $comp->component_code }}</span>
                                </td>
                                <td>
                                    @if($comp->width_cm && $comp->length_cm)
                                        <span class="qty">{{ (float) $comp->width_cm }} × {{ (float) $comp->length_cm }}</span> <span class="text-muted fs-8">سم</span>
                                    @else
                                        <span class="text-muted fs-7">غير محدد</span>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->has_storage)
                                        <span class="status-chip status-chip-warning"><i class="fas fa-box-archive" aria-hidden="true"></i> مع تخزين</span>
                                    @else
                                        <span class="text-muted fs-7">بدون تخزين</span>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->recipe && $comp->recipe->currentApprovedVersion)
                                        <a href="{{ route('recipes.show', $comp->recipe) }}" class="status-chip status-chip-success text-decoration-none">
                                            <i class="fas fa-circle-check" aria-hidden="true"></i> <span class="ltr-isolate">{{ $comp->recipe->recipe_code }} · V{{ $comp->recipe->currentApprovedVersion->version_number }}</span>
                                        </a>
                                    @elseif($comp->recipe)
                                        <a href="{{ route('recipes.show', $comp->recipe) }}" class="status-chip status-chip-warning text-decoration-none">
                                            <i class="fas fa-file-pen" aria-hidden="true"></i> مسودة بانتظار الاعتماد
                                        </a>
                                    @else
                                        <span class="status-chip status-chip-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> بلا وصفة</span>
                                        @can('recipes.manage')
                                            <a href="{{ route('recipes.create', ['target_type' => 'SEMI_FINISHED_COMPONENT', 'semi_finished_component_id' => $comp->id]) }}" class="d-block fs-8 mt-1">إنشاء وصفة</a>
                                        @endcan
                                    @endif
                                </td>
                                <td>@include('recipes.partials.active-chip', ['active' => $comp->is_active])</td>
                                <td class="pe-3 text-end">
                                    @can('semi_finished_components.manage')
                                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                            <button type="button" @click="editComponent = @js($comp->only(['id', 'name_ar', 'name_en', 'width_cm', 'length_cm', 'has_storage', 'notes'])); showEditModal = true" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-pen" aria-hidden="true"></i> تعديل
                                            </button>
                                            <form action="{{ route('recipes.components.toggle-status', $comp) }}" method="POST" class="d-inline"
                                                  @if ($comp->is_active) onsubmit="return confirm('تعطيل المكون {{ $comp->name_ar }}؟ لن يظهر عند إضافة بنود جديدة للوصفات.');" @endif>
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $comp->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                    {{ $comp->is_active ? 'تعطيل' : 'تفعيل' }}
                                                </button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr class="table-stack-empty">
                                <td colspan="6" class="empty-state" role="status">
                                    <i class="fas fa-cubes fs-2 d-block mb-3 opacity-50" aria-hidden="true"></i>
                                    @if (request()->filled('search'))
                                        <p class="mb-0">لا توجد مكونات تطابق «{{ request('search') }}».</p>
                                        <div class="empty-state-cta"><a href="{{ route('recipes.components.index') }}" class="btn btn-outline-secondary">مسح البحث</a></div>
                                    @else
                                        <p class="fw-bold text-dark mb-1">لا توجد مكونات نصف مصنعة بعد</p>
                                        <p class="mb-0">أضف الأجزاء المشتركة بين المنتجات لتُستخدم كبنود داخل الوصفات.</p>
                                        @can('semi_finished_components.manage')
                                            <div class="empty-state-cta"><button type="button" @click="showCreateModal = true" class="btn btn-warning fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> إضافة أول مكون</button></div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($components->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $components->links() }}
            </div>
        @endif
    </div>

    @can('semi_finished_components.manage')
        @foreach (['create', 'edit'] as $mode)
            @php $isEdit = $mode === 'edit'; @endphp
            <div class="modal fade show" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="component-{{ $mode }}-title" style="background: rgba(15,23,42,.55);"
                 x-show="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }}" :class="{ 'd-block': {{ $isEdit ? 'showEditModal' : 'showCreateModal' }} }"
                 @click.self="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }} = false" @keydown.escape.window="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }} = false"
                 x-effect="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }} && $nextTick(() => $refs.{{ $mode }}Name.focus())" x-cloak>
                <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                    <div class="modal-content border-0 shadow">
                        <form @if ($isEdit) :action="`{{ url('recipes/components') }}/${editComponent.id}`" @else action="{{ route('recipes.components.store') }}" @endif method="POST" class="d-flex flex-column h-100">
                            @csrf
                            @if ($isEdit)
                                @method('PUT')
                                <input type="hidden" name="_component_id" :value="editComponent.id">
                            @endif
                            <input type="hidden" name="_form" value="{{ $mode }}">
                            <div class="modal-header">
                                <h2 class="modal-title h5 fw-bold" id="component-{{ $mode }}-title">{{ $isEdit ? 'تعديل المكون' : 'مكون نصف مصنع جديد' }}</h2>
                                <button type="button" class="btn-close" @click="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }} = false" aria-label="إغلاق"></button>
                            </div>
                            <div class="modal-body">
                                @php $errorsApply = $reopenForm === $mode; @endphp
                                <div class="mb-3">
                                    <label for="{{ $mode }}-name-ar" class="form-label fw-bold fs-7">اسم المكون <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input type="text" id="{{ $mode }}-name-ar" name="name_ar" x-ref="{{ $mode }}Name" class="form-control {{ $errorsApply && $errors->has('name_ar') ? 'is-invalid' : '' }}"
                                           @if ($isEdit) x-model="editComponent.name_ar" @else value="{{ $errorsApply ? old('name_ar') : '' }}" @endif placeholder="مثل: بوكس 160×200 عادي" required>
                                    @if ($errorsApply) @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                </div>
                                <div class="mb-3">
                                    <label for="{{ $mode }}-name-en" class="form-label fw-bold fs-7">الاسم بالإنجليزية <span class="text-muted fw-normal">(اختياري)</span></label>
                                    <input type="text" id="{{ $mode }}-name-en" name="name_en" dir="ltr" class="form-control"
                                           @if ($isEdit) x-model="editComponent.name_en" @else value="{{ $errorsApply ? old('name_en') : '' }}" @endif placeholder="Box 160x200">
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="{{ $mode }}-width" class="form-label fw-bold fs-7">العرض (سم)</label>
                                        <input type="number" step="0.1" min="0" inputmode="decimal" id="{{ $mode }}-width" name="width_cm" class="form-control {{ $errorsApply && $errors->has('width_cm') ? 'is-invalid' : '' }}"
                                               @if ($isEdit) x-model="editComponent.width_cm" @else value="{{ $errorsApply ? old('width_cm') : '' }}" @endif placeholder="160">
                                    </div>
                                    <div class="col-6">
                                        <label for="{{ $mode }}-length" class="form-label fw-bold fs-7">الطول (سم)</label>
                                        <input type="number" step="0.1" min="0" inputmode="decimal" id="{{ $mode }}-length" name="length_cm" class="form-control {{ $errorsApply && $errors->has('length_cm') ? 'is-invalid' : '' }}"
                                               @if ($isEdit) x-model="editComponent.length_cm" @else value="{{ $errorsApply ? old('length_cm') : '' }}" @endif placeholder="200">
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" name="has_storage" value="1" id="{{ $mode }}-has-storage"
                                           @if ($isEdit) :checked="!! editComponent.has_storage" @elseif ($errorsApply && old('has_storage')) checked @endif>
                                    <label class="form-check-label fw-bold fs-7" for="{{ $mode }}-has-storage">يتضمن تخزين هيدروليكي</label>
                                </div>
                                <div>
                                    <label for="{{ $mode }}-notes" class="form-label fw-bold fs-7">ملاحظات <span class="text-muted fw-normal">(اختياري)</span></label>
                                    <textarea id="{{ $mode }}-notes" name="notes" class="form-control" rows="2" @if ($isEdit) x-model="editComponent.notes" @endif placeholder="مواصفات إضافية للمكون">{{ ! $isEdit && $errorsApply ? old('notes') : '' }}</textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" @click="{{ $isEdit ? 'showEditModal' : 'showCreateModal' }} = false">إلغاء</button>
                                <button type="submit" class="btn btn-warning fw-bold">{{ $isEdit ? 'حفظ التعديلات' : 'حفظ المكون' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endcan

</div>
@endsection
