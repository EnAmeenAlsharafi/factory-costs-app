@extends('layouts.app')

@section('title', 'مهام الأقسام - مصنع مفروشات سدير')
@section('page-title', 'مهام الأقسام')

@section('content')
@php
    $user = auth()->user();
    $canManageAllDepartments = $user->isAdministrator() || $user->hasRole('production_manager');
    $activeFilterCount = collect(['department_id', 'priority'])->filter(fn ($key) => request()->filled($key))->count();
    $priorityLabels = ['NORMAL' => 'عادي', 'URGENT' => 'عاجل', 'VIP' => 'VIP'];
    $emptyMessages = [
        'active' => 'لا توجد مهام مفتوحة لقسمك حالياً. ستظهر المهام هنا فور إطلاق أوامر إنتاج تمر بقسمك.',
        'waiting' => 'لا توجد مهام بانتظار البدء. كل المهام الجاهزة بدأ العمل عليها.',
        'in_progress' => 'لا توجد مهام قيد التنفيذ حالياً. ابدأ مهمة من تبويب "بانتظار البدء".',
        'rework' => 'لا توجد مهام إعادة عمل مفتوحة لقسمك — هذا مؤشر جيد.',
        'completed_today' => 'لم تُكمل أي مهمة اليوم بعد.',
    ];
@endphp

<div class="d-flex flex-column gap-3">
    <div class="page-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-list-check text-warning fs-5" aria-hidden="true"></i>
                    <span>مهام الأقسام</span>
                </h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">
                    @if ($canManageAllDepartments)
                        العمليات التشغيلية لجميع الأقسام — افتح المهمة لتسجيل الإنجاز.
                    @else
                        قسمك: <strong>{{ $user->department?->name_ar ?? 'غير محدد' }}</strong> — افتح المهمة لتسجيل الإنجاز.
                    @endif
                </p>
            </div>
            @if ($canManageAllDepartments)
                <a href="{{ route('production.board.index') }}" class="btn btn-outline-primary">
                    <i class="fas fa-table-columns" aria-hidden="true"></i> لوحة متابعة الإنتاج
                </a>
            @endif
        </div>
    </div>

    {{-- Status tabs (keep filtering simple) --}}
    <nav class="segment-tabs" aria-label="تصنيف المهام">
        @foreach ($tabs as $tabKey => $tabLabel)
            <a href="{{ route('production.queue.index', array_merge(request()->except(['tab', 'page']), ['tab' => $tabKey])) }}"
               class="segment-tab {{ $tab === $tabKey ? 'active' : '' }}"
               @if ($tab === $tabKey) aria-current="page" @endif>
                @if ($tabKey === 'rework') <i class="fas fa-rotate" aria-hidden="true"></i> @endif
                {{ $tabLabel }}
                <span class="count">{{ $tabCounts[$tabKey] }}</span>
            </a>
        @endforeach
    </nav>

    <x-filter-sheet id="queue-filters" :action="route('production.queue.index')" :active-count="$activeFilterCount" :reset-url="route('production.queue.index', ['tab' => $tab])">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($canManageAllDepartments)
            <div class="col-12 col-md-5">
                <label for="queue-department" class="form-label fs-7">القسم</label>
                <select name="department_id" id="queue-department" class="form-select">
                    <option value="">جميع الأقسام التشغيلية</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>قسم {{ $dept->name_ar }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-12 col-md-4">
            <label for="queue-priority" class="form-label fs-7">الأولوية</label>
            <select name="priority" id="queue-priority" class="form-select">
                <option value="">جميع الأولويات</option>
                @foreach ($priorityLabels as $priorityKey => $priorityLabel)
                    <option value="{{ $priorityKey }}" @selected(request('priority') === $priorityKey)>{{ $priorityLabel }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-sheet>

    @if ($operations->isEmpty())
        <div class="card-factory empty-state" role="status">
            <i class="fas {{ $tab === 'rework' ? 'fa-circle-check text-success' : 'fa-mug-hot text-secondary' }} fs-2 mb-3 d-block" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold">{{ $emptyMessages[$tab] }}</p>
        </div>
    @else
        {{-- Mobile: task cards --}}
        <div class="task-list mobile-cards-only-lg">
            @foreach ($operations as $op)
                @php
                    $po = $op->productionOrder;
                    $productName = $po->is_custom_design ? $po->custom_design_name : ($po->productModel?->name_ar ?? 'منتج');
                    $remaining = max(0, $op->required_quantity - $op->completed_quantity);
                    $isRework = $op->targetReworkActions->isNotEmpty();
                    $tone = \App\Services\StatusPresenter::present('operation', $op->status)['tone'];
                @endphp
                <article class="task-card tone-{{ $tone }} {{ $isRework ? 'is-rework' : '' }}" aria-labelledby="task-title-{{ $op->id }}">
                    <div class="task-card-head">
                        <div class="min-w-0">
                            <h2 id="task-title-{{ $op->id }}" class="task-card-title">{{ $productName }}</h2>
                            <div class="task-card-ref">
                                {{ $op->operation_name_snapshot }} &bull; <span class="ltr-isolate">{{ $po->production_order_number }}</span>
                            </div>
                        </div>
                        <x-status-badge domain="operation" :status="$op->status" />
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($isRework)
                            <span class="rework-flag"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل</span>
                        @endif
                        @if (in_array($po->priority, ['URGENT', 'VIP'], true))
                            <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$po->priority] }}</span>
                        @endif
                    </div>
                    <div class="task-card-meta">
                        <span><i class="fas fa-ruler-combined" aria-hidden="true"></i><span class="ltr-isolate">{{ (int) $po->requested_width_cm }}×{{ (int) $po->requested_length_cm }}</span> سم{{ $po->has_storage ? ' — سحارة' : '' }}</span>
                        @if ($po->fabricMaterial)
                            <span><i class="fas fa-scroll" aria-hidden="true"></i>{{ $po->fabricMaterial->name_ar }}</span>
                        @endif
                        @if ($po->fabricColor || $po->fabric_color_code)
                            <span>
                                <span class="color-dot" style="background: {{ $po->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span>
                                لون <strong class="ltr-isolate">{{ $po->fabric_color_code ?? $po->fabricColor?->color_code }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="qty-trio" aria-label="الكميات">
                        <div><small>المطلوب</small><strong>{{ $op->required_quantity }}</strong></div>
                        <div><small>المنجز</small><strong>{{ $op->completed_quantity }}</strong></div>
                        <div class="is-remaining"><small>المتبقي</small><strong>{{ $remaining }}</strong></div>
                    </div>
                    <div class="task-card-actions">
                        <button type="button" class="btn btn-factory-primary" data-bs-toggle="modal" data-bs-target="#task-sheet-{{ $op->id }}">
                            <i class="fas fa-folder-open" aria-hidden="true"></i> فتح المهمة
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Tablet/desktop: dense table --}}
        <div class="table-factory-wrapper has-mobile-cards-lg">
            <div class="table-responsive">
                <table class="table table-factory table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>المنتج والمقاس</th>
                            <th>العملية / القسم</th>
                            <th>القماش واللون</th>
                            <th class="text-center">المطلوب / المنجز / المتبقي</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operations as $op)
                            @php
                                $po = $op->productionOrder;
                                $remaining = max(0, $op->required_quantity - $op->completed_quantity);
                                $isRework = $op->targetReworkActions->isNotEmpty();
                            @endphp
                            <tr @class(['table-warning' => $isRework])>
                                <td>
                                    <div class="fw-bold text-dark">{{ $po->is_custom_design ? $po->custom_design_name : $po->productModel?->name_ar }}</div>
                                    <small class="text-muted d-block"><span class="ltr-isolate">{{ (int) $po->requested_width_cm }}×{{ (int) $po->requested_length_cm }}</span> سم {{ $po->has_storage ? '(سحارة)' : '' }}</small>
                                    <a href="{{ route('production.orders.show', $po) }}" class="fs-8 font-monospace text-decoration-none">{{ $po->production_order_number }}</a>
                                    @if ($isRework)
                                        <span class="rework-flag ms-1"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $op->operation_name_snapshot }}</div>
                                    <small class="text-muted">{{ $op->workCenter?->department?->name_ar }} &bull; تسلسل #{{ $op->sequence_number }}</small>
                                </td>
                                <td>
                                    <small class="d-block text-dark">{{ $po->fabricMaterial?->name_ar ?? '-' }}</small>
                                    @if ($po->fabricColor || $po->fabric_color_code)
                                        <small class="text-muted"><span class="color-dot" style="background: {{ $po->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span> <span class="ltr-isolate">{{ $po->fabric_color_code ?? $po->fabricColor?->color_code }}</span></small>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap">
                                    <span class="fw-bold">{{ $op->required_quantity }}</span> /
                                    <span class="fw-bold text-success">{{ $op->completed_quantity }}</span> /
                                    <span class="fw-bold text-warning-emphasis">{{ $remaining }}</span>
                                </td>
                                <td><x-status-badge domain="operation" :status="$op->status" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-factory-primary" data-bs-toggle="modal" data-bs-target="#task-sheet-{{ $op->id }}">
                                        <i class="fas fa-folder-open" aria-hidden="true"></i> فتح المهمة
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($operations->hasPages())
            <div class="d-flex justify-content-center">{{ $operations->links() }}</div>
        @endif

        {{-- Task sheets: one per operation, shared by cards and table. Full screen on phones. --}}
        @foreach ($operations as $op)
            @include('production.queue.partials.task-sheet', ['op' => $op, 'priorityLabels' => $priorityLabels])
        @endforeach
    @endif
</div>
@endsection
