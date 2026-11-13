@extends('layouts.app')

@section('title', 'قوالب التصنيع')

@section('content')
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'قوالب التصنيع',
        'subtitle' => 'أنماط بنود جاهزة: اختر قالباً لتوليد مسودة وصفة ببنودها بدلاً من إدخالها من الصفر.',
        'icon' => 'fas fa-layer-group',
        'breadcrumbs' => [['title' => 'الوصفات والقوالب'], ['title' => 'قوالب التصنيع']],
        'actionUrl' => route('recipes.templates.create'),
        'actionText' => 'قالب جديد',
        'actionPermission' => 'manufacturing_templates.manage',
    ])

    {{-- Flash messages are rendered once by the layout (partials.flash). --}}

    <form method="GET" action="{{ route('recipes.templates.index') }}" role="search" class="d-flex gap-2 my-3">
        <label for="templates-search" class="visually-hidden">بحث في القوالب</label>
        <input type="search" id="templates-search" name="search" class="form-control" inputmode="search" enterkeyhint="search" autocomplete="off"
               placeholder="ابحث باسم القالب أو الكود" value="{{ request('search') }}">
        <button type="submit" class="btn btn-factory-primary flex-shrink-0"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> بحث</button>
    </form>

    @if (request()->filled('search'))
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fs-7" aria-label="البحث النشط">
            <span class="status-chip status-chip-secondary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> {{ request('search') }}</span>
            <span class="text-muted">{{ $templates->total() }} نتيجة</span>
            <a href="{{ route('recipes.templates.index') }}" class="link-secondary">مسح البحث</a>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">القالب</th>
                            <th>الكود</th>
                            <th>البنود</th>
                            <th>الحالة</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $tpl)
                            @php
                                $materialLines = $tpl->items->where('item_type', 'MATERIAL')->count();
                                $componentLines = $tpl->items->count() - $materialLines;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('recipes.templates.show', $tpl) }}" class="fw-bold text-dark text-decoration-none">{{ $tpl->name_ar }}</a>
                                    @if ($tpl->description)
                                        <div class="text-muted fs-8 text-truncate" style="max-width: 28rem;">{{ $tpl->description }}</div>
                                    @endif
                                </td>
                                <td><span class="code-chip">{{ $tpl->template_code }}</span></td>
                                <td class="fs-7">
                                    <span class="fw-bold">{{ $tpl->items->count() }} بند</span>
                                    <span class="text-muted">— {{ $materialLines }} مادة خام @if ($componentLines) · {{ $componentLines }} مكون @endif</span>
                                </td>
                                <td>@include('recipes.partials.active-chip', ['active' => $tpl->is_active])</td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        @can('recipes.manage')
                                            @if ($tpl->is_active && $tpl->items->isNotEmpty())
                                                <a href="{{ route('recipes.templates.show', [$tpl, 'open_modal' => 1]) }}" class="btn btn-sm btn-warning fw-bold">
                                                    <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> إنشاء وصفة
                                                </a>
                                            @endif
                                        @endcan
                                        <a href="{{ route('recipes.templates.show', $tpl) }}" class="btn btn-sm btn-outline-secondary">عرض</a>
                                        @can('manufacturing_templates.manage')
                                            <a href="{{ route('recipes.templates.edit', $tpl) }}" class="btn btn-sm btn-outline-secondary" aria-label="تعديل القالب {{ $tpl->name_ar }}" title="تعديل">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table-stack-empty">
                                <td colspan="5" class="empty-state" role="status">
                                    <i class="fas fa-layer-group fs-2 d-block mb-3 opacity-50" aria-hidden="true"></i>
                                    @if (request()->filled('search'))
                                        <p class="mb-0">لا توجد قوالب تطابق «{{ request('search') }}».</p>
                                        <div class="empty-state-cta"><a href="{{ route('recipes.templates.index') }}" class="btn btn-outline-secondary">مسح البحث</a></div>
                                    @else
                                        <p class="fw-bold text-dark mb-1">لا توجد قوالب بعد</p>
                                        <p class="mb-0">أنشئ قالباً للبنود المتكررة (مثل سرير قياسي) لتوفير الوقت عند إنشاء الوصفات.</p>
                                        @can('manufacturing_templates.manage')
                                            <div class="empty-state-cta"><a href="{{ route('recipes.templates.create') }}" class="btn btn-warning fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> إنشاء أول قالب</a></div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($templates->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $templates->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
