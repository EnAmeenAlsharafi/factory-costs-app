@extends('layouts.app')

@section('title', 'مركز تمرير الإنتاج - مصنع مفروشات سدير')
@section('page-title', 'مركز تمرير الإنتاج')

@section('content')
@php
    $stageMeta = [
        'approve' => ['label' => 'اعتماد للإنتاج', 'icon' => 'fa-clipboard-check', 'hint' => 'طلبات بيع بانتظار المراجعة الفنية'],
        'release' => ['label' => 'إطلاق للورشة', 'icon' => 'fa-rocket', 'hint' => 'بنود معتمدة لم يُنشأ لها أمر إنتاج'],
        'materials' => ['label' => 'طلب المواد', 'icon' => 'fa-boxes-stacked', 'hint' => 'أوامر مُطلقة بلا طلب خامات'],
        'assign' => ['label' => 'إسناد للأقسام', 'icon' => 'fa-user-gear', 'hint' => 'عمليات جاهزة لم تُسند لأحد'],
    ];
    $priorityLabels = ['NORMAL' => 'عادي', 'URGENT' => 'عاجل', 'VIP' => 'VIP'];
    $result = session('pipeline_result');
    $itemName = fn ($productionOrder) => $productionOrder->display_name;
    $dueChip = function ($date) {
        if (! $date) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => ['danger', 'متأخر '.abs($days).' يوم'],
            $days === 0 => ['danger', 'التسليم اليوم'],
            $days <= 3 => ['warning', 'التسليم بعد '.$days.' أيام'],
            default => ['secondary', 'التسليم '.$date->format('Y-m-d')],
        };
    };
@endphp

<div class="d-flex flex-column gap-3 pipeline-page">
    <div class="page-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-diagram-next text-primary fs-5" aria-hidden="true"></i>
                    <span>مركز تمرير الإنتاج</span>
                </h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">حدّد عدة طلبات وانقلها للمرحلة التالية بضغطة واحدة. ما لا يمكن نقله يُتخطّى مع ذكر السبب.</p>
            </div>
            @can('production.view')
                <a href="{{ route('production.board.index') }}" class="btn btn-outline-primary">
                    <i class="fas fa-table-columns" aria-hidden="true"></i> متابعة التنفيذ في الأقسام
                </a>
            @endcan
        </div>
    </div>

    <nav aria-label="مراحل تمرير الطلب">
        <ol class="pipeline-steps" x-data x-init="$nextTick(() => { const active = $el.querySelector('.is-active'); if (active && $el.scrollWidth > $el.clientWidth) { const box = $el.getBoundingClientRect(), item = active.getBoundingClientRect(); $el.scrollLeft += (item.left - box.left) - (box.width - item.width) / 2; } })">
            @foreach ($allowedStages as $index => $stageKey)
                <li class="pipeline-step {{ $stageKey === $stage ? 'is-active' : '' }}">
                    <a href="{{ route('production.pipeline.index', $stageKey) }}" @if ($stageKey === $stage) aria-current="step" @endif>
                        <span class="pipeline-step-index" aria-hidden="true">{{ $loop->iteration }}</span>
                        <span class="pipeline-step-text">
                            <span class="pipeline-step-label">{{ $stageMeta[$stageKey]['label'] }}</span>
                            <span class="pipeline-step-hint">{{ $stageMeta[$stageKey]['hint'] }}</span>
                        </span>
                        <span class="pipeline-step-count {{ $counts[$stageKey] > 0 ? 'has-work' : '' }}" aria-label="{{ $counts[$stageKey] }} بانتظار الإجراء">{{ $counts[$stageKey] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($result)
        <div class="card-factory pipeline-result {{ empty($result['skipped']) ? 'is-success' : 'is-partial' }}" role="status" aria-live="polite">
            <div class="d-flex align-items-start gap-2">
                <i class="fas {{ empty($result['skipped']) ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-warning' }} fs-5 mt-1" aria-hidden="true"></i>
                <div class="min-w-0 flex-grow-1">
                    <p class="fw-bold mb-1">
                        {{ $result['action'] }}: تم تنفيذ {{ count($result['done']) }}
                        @if (! empty($result['skipped']))
                            ، وتُخطّي {{ count($result['skipped']) }}
                        @endif
                    </p>
                    @if (! empty($result['skipped']))
                        <ul class="pipeline-result-list mb-1">
                            @foreach ($result['skipped'] as $reference => $reason)
                                <li><bdi class="fw-semibold">{{ $reference }}</bdi>: {{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($result['done']))
                        <details>
                            <summary class="fs-7 text-muted">عرض ما تم تنفيذه</summary>
                            <ul class="pipeline-result-list mt-1 mb-0">
                                @foreach ($result['done'] as $line)
                                    <li><bdi>{{ $line }}</bdi></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" action="{{ route('production.pipeline.index', $stage) }}" class="pipeline-search" role="search">
            @if ($stage === 'assign' && $departmentId && ! auth()->user()->isDepartmentRestricted())
                <input type="hidden" name="department_id" value="{{ $departmentId }}">
            @endif
            <label for="pipeline-search" class="visually-hidden">بحث</label>
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="pipeline-search" name="search" value="{{ $search }}" class="form-control" placeholder="رقم الطلب أو اسم العميل...">
        </form>

        @if ($stage === 'assign' && $departments->count() > 1)
            <div class="pipeline-filter-chips" role="group" aria-label="تصفية حسب القسم">
                <a href="{{ route('production.pipeline.index', ['stage' => 'assign', 'search' => $search ?: null]) }}" class="pipeline-chip {{ ! $departmentId ? 'is-active' : '' }}">كل الأقسام <span>{{ $readyByDepartment->sum() }}</span></a>
                @foreach ($departments as $department)
                    <a href="{{ route('production.pipeline.index', ['stage' => 'assign', 'department_id' => $department->id, 'search' => $search ?: null]) }}" class="pipeline-chip {{ (int) $departmentId === $department->id ? 'is-active' : '' }}">
                        {{ $department->name_ar }} <span>{{ $readyByDepartment[$department->id] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ========== Stage forms ========== --}}
    @php
        $formAction = match ($stage) {
            'approve' => route('production.pipeline.approve'),
            'release' => route('production.pipeline.release'),
            'materials' => route('production.pipeline.materials'),
            'assign' => route('production.pipeline.assign'),
        };
    @endphp

    <form method="POST" action="{{ $formAction }}" class="card-factory p-0 pipeline-board"
          x-data="{
              count: 0,
              total: 0,
              departments: [],
              busy: false,
              refresh() {
                  const picked = [...$root.querySelectorAll('input.pick:checked')];
                  this.count = picked.length;
                  this.total = $root.querySelectorAll('input.pick:not(:disabled)').length;
                  this.departments = [...new Set(picked.map(el => el.dataset.department).filter(Boolean))];
              },
              toggleAll(on) {
                  $root.querySelectorAll('input.pick:not(:disabled)').forEach(el => el.checked = on);
                  this.refresh();
              },
          }"
          x-init="refresh()"
          @change="refresh()"
          @submit="busy = true">
        @csrf

        @php
            $isEmpty = match ($stage) {
                'approve' => $orders->isEmpty(),
                'release' => $lines->isEmpty() && $draftOrders->isEmpty(),
                'materials' => $productionOrders->isEmpty(),
                'assign' => $operations->isEmpty(),
            };
        @endphp

        @if ($isEmpty)
            <div class="empty-state" role="status">
                @if ($search !== '')
                    <i class="fas fa-filter-circle-xmark fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
                    <p class="fw-semibold mb-2">لا توجد نتائج تطابق «{{ $search }}» في هذه المرحلة.</p>
                    <a href="{{ route('production.pipeline.index', $stage) }}" class="btn btn-outline-primary btn-sm">مسح البحث</a>
                @else
                    <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">لا يوجد شيء بانتظار «{{ $stageMeta[$stage]['label'] }}».</p>
                    @php $nextBusy = $allowedStages->first(fn ($key) => $key !== $stage && $counts[$key] > 0); @endphp
                    @if ($nextBusy)
                        <a href="{{ route('production.pipeline.index', $nextBusy) }}" class="btn btn-primary btn-sm mt-2">
                            انتقل إلى «{{ $stageMeta[$nextBusy]['label'] }}» ({{ $counts[$nextBusy] }})
                        </a>
                    @else
                        <p class="text-muted fs-7 mb-0">كل المراحل خالية الآن.</p>
                    @endif
                @endif
            </div>
        @else
            <div class="pipeline-list-head">
                <label class="d-flex align-items-center gap-2 mb-0">
                    <input type="checkbox" class="form-check-input pipeline-check" :checked="total > 0 && count === total" :indeterminate="count > 0 && count < total" @change.stop="toggleAll($event.target.checked)" aria-label="تحديد الكل">
                    <span class="fw-semibold">تحديد الكل</span>
                </label>
                <span class="text-muted fs-7" x-text="count ? 'تم اختيار ' + count + ' من ' + total : total + ' جاهز للتحديد'"></span>
            </div>

            <div class="pipeline-list">
                {{-- ---------- 1. Approve ---------- --}}
                @if ($stage === 'approve')
                    @foreach ($orders as $order)
                        @php $due = $dueChip($order->requested_delivery_date); @endphp
                        <label class="pipeline-item">
                            <input type="checkbox" class="form-check-input pipeline-check pick" name="order_ids[]" value="{{ $order->id }}">
                            <span class="pipeline-item-main">
                                <span class="pipeline-item-title">{{ $order->customer?->name }}</span>
                                <span class="pipeline-item-ref"><bdi>{{ $order->order_number }}</bdi></span>
                                <span class="pipeline-item-meta">
                                    <span><i class="fas fa-layer-group" aria-hidden="true"></i> {{ $order->lines_count }} بند / {{ (int) $order->lines_sum_quantity }} قطعة</span>
                                    @if ($due)
                                        <span class="status-chip status-chip-{{ $due[0] }}"><i class="far fa-calendar" aria-hidden="true"></i> {{ $due[1] }}</span>
                                    @endif
                                    @if ($order->priority && $order->priority !== 'NORMAL')
                                        <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$order->priority] ?? $order->priority }}</span>
                                    @endif
                                    @if ($paymentHolds[$order->id] ?? false)
                                        <span class="status-chip status-chip-warning" title="لم تكتمل شروط السداد أو الائتمان لهذا الطلب"><i class="fas fa-hand" aria-hidden="true"></i> موقوف مالياً</span>
                                    @endif
                                </span>
                            </span>
                            <a href="{{ route('sales.orders.review', $order) }}" class="btn btn-sm btn-outline-secondary pipeline-item-link" @click.stop>مراجعة تفصيلية</a>
                        </label>
                    @endforeach
                @endif

                {{-- ---------- 2. Release ---------- --}}
                @if ($stage === 'release')
                    @if ($lines->isNotEmpty())
                        <div class="pipeline-group-title">بنود معتمدة لم تُطلق بعد</div>
                        @foreach ($lines as $line)
                            @php
                                $order = $line->customerOrder;
                                $hasRecipe = $line->custom_design || $line->approved_recipe_version_id;
                                $due = $dueChip($order->requested_delivery_date);
                            @endphp
                            <label class="pipeline-item {{ $hasRecipe ? '' : 'is-disabled' }}">
                                <input type="checkbox" class="form-check-input pipeline-check pick" name="line_ids[]" value="{{ $line->id }}" @disabled(! $hasRecipe)>
                                <span class="pipeline-item-main">
                                    <span class="pipeline-item-title">{{ $line->custom_design ? ($line->custom_design_name ?: 'تصميم خاص') : ($line->productModel?->name_ar ?? 'بند '.$line->item_number) }}</span>
                                    <span class="pipeline-item-ref"><bdi>{{ $order->order_number }}</bdi> · {{ $order->customer?->name }}</span>
                                    <span class="pipeline-item-meta">
                                        <span><i class="fas fa-cubes" aria-hidden="true"></i> {{ $line->remaining_quantity }} قطعة للإطلاق</span>
                                        @if ($line->requested_width_cm && $line->requested_length_cm)
                                            <span><i class="fas fa-ruler-combined" aria-hidden="true"></i> <bdi>{{ (float) $line->requested_width_cm }}×{{ (float) $line->requested_length_cm }}</bdi> سم</span>
                                        @endif
                                        @if ($due)
                                            <span class="status-chip status-chip-{{ $due[0] }}"><i class="far fa-calendar" aria-hidden="true"></i> {{ $due[1] }}</span>
                                        @endif
                                        @if ($line->custom_design)
                                            <span class="status-chip status-chip-info">تصميم خاص</span>
                                        @elseif ($line->approved_recipe_version_id)
                                            <span class="status-chip status-chip-success"><i class="fas fa-check" aria-hidden="true"></i> وصفة معتمدة</span>
                                        @else
                                            <span class="status-chip status-chip-danger"><i class="fas fa-ban" aria-hidden="true"></i> بلا وصفة — أنشئ أمر الإنتاج ثم اربطه بوصفة من صفحته</span>
                                        @endif
                                    </span>
                                </span>
                                <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary pipeline-item-link" @click.stop>الطلب</a>
                            </label>
                        @endforeach
                    @endif

                    @if ($draftOrders->isNotEmpty())
                        <div class="pipeline-group-title">أوامر إنتاج مسودة بانتظار الإطلاق</div>
                        @foreach ($draftOrders as $productionOrder)
                            @php $hasRecipe = $productionOrder->is_custom_design || $productionOrder->manufacturing_recipe_version_id; @endphp
                            <label class="pipeline-item {{ $hasRecipe ? '' : 'is-disabled' }}">
                                <input type="checkbox" class="form-check-input pipeline-check pick" name="production_order_ids[]" value="{{ $productionOrder->id }}" @disabled(! $hasRecipe)>
                                <span class="pipeline-item-main">
                                    <span class="pipeline-item-title">{{ $itemName($productionOrder) }}</span>
                                    <span class="pipeline-item-ref"><bdi>{{ $productionOrder->production_order_number }}</bdi> · <bdi>{{ $productionOrder->customerOrder?->order_number }}</bdi></span>
                                    <span class="pipeline-item-meta">
                                        <span><i class="fas fa-cubes" aria-hidden="true"></i> {{ $productionOrder->released_quantity }} قطعة</span>
                                        @unless ($hasRecipe)
                                            <span class="status-chip status-chip-danger"><i class="fas fa-ban" aria-hidden="true"></i> بلا وصفة معتمدة</span>
                                        @endunless
                                    </span>
                                </span>
                                <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-sm btn-outline-secondary pipeline-item-link" @click.stop>الأمر</a>
                            </label>
                        @endforeach
                    @endif
                @endif

                {{-- ---------- 3. Materials ---------- --}}
                @if ($stage === 'materials')
                    @foreach ($productionOrders as $productionOrder)
                        @php $hasRecipe = $productionOrder->material_requirements_count > 0 || $productionOrder->manufacturing_recipe_version_id; @endphp
                        <label class="pipeline-item {{ $hasRecipe ? '' : 'is-disabled' }}">
                            <input type="checkbox" class="form-check-input pipeline-check pick" name="production_order_ids[]" value="{{ $productionOrder->id }}" @disabled(! $hasRecipe)>
                            <span class="pipeline-item-main">
                                <span class="pipeline-item-title">{{ $itemName($productionOrder) }}</span>
                                <span class="pipeline-item-ref"><bdi>{{ $productionOrder->production_order_number }}</bdi> · {{ $productionOrder->customerOrder?->customer?->name }}</span>
                                <span class="pipeline-item-meta">
                                    <span><i class="fas fa-cubes" aria-hidden="true"></i> {{ $productionOrder->released_quantity }} قطعة</span>
                                    @if (in_array($productionOrder->priority, ['URGENT', 'VIP'], true))
                                        <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$productionOrder->priority] }}</span>
                                    @endif
                                    @if ($hasRecipe)
                                        <span class="status-chip status-chip-success"><i class="fas fa-list-check" aria-hidden="true"></i> الخامات من الوصفة{{ $productionOrder->material_requirements_count ? ' ('.$productionOrder->material_requirements_count.')' : '' }}</span>
                                    @else
                                        <span class="status-chip status-chip-warning"><i class="fas fa-pen" aria-hidden="true"></i> بلا وصفة — يحتاج طلباً يدوياً</span>
                                    @endif
                                </span>
                            </span>
                            @if ($hasRecipe)
                                <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-sm btn-outline-secondary pipeline-item-link" @click.stop>الأمر</a>
                            @else
                                <a href="{{ route('production.material-requests.create', ['production_order_id' => $productionOrder->id]) }}" class="btn btn-sm btn-primary pipeline-item-link" @click.stop>طلب يدوي</a>
                            @endif
                        </label>
                    @endforeach
                @endif

                {{-- ---------- 4. Assign ---------- --}}
                @if ($stage === 'assign')
                    @foreach ($operations as $operation)
                        @php $productionOrder = $operation->productionOrder; @endphp
                        <label class="pipeline-item">
                            <input type="checkbox" class="form-check-input pipeline-check pick" name="operation_ids[]" value="{{ $operation->id }}" data-department="{{ $operation->workCenter?->department_id }}">
                            <span class="pipeline-item-main">
                                <span class="pipeline-item-title">{{ $operation->operation_name_snapshot }}</span>
                                <span class="pipeline-item-ref">{{ $itemName($productionOrder) }} · <bdi>{{ $productionOrder->production_order_number }}</bdi></span>
                                <span class="pipeline-item-meta">
                                    <span class="status-chip status-chip-primary"><i class="fas fa-building" aria-hidden="true"></i> {{ $operation->workCenter?->department?->name_ar ?? '—' }}</span>
                                    <span><i class="fas fa-cubes" aria-hidden="true"></i> {{ $operation->required_quantity }} قطعة</span>
                                    @if (in_array($productionOrder->priority, ['URGENT', 'VIP'], true))
                                        <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$productionOrder->priority] }}</span>
                                    @endif
                                    @if ($operation->ready_at)
                                        <span class="text-muted"><i class="far fa-clock" aria-hidden="true"></i> جاهزة منذ {{ $operation->ready_at->diffForHumans(null, true) }}</span>
                                    @endif
                                </span>
                            </span>
                            <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-sm btn-outline-secondary pipeline-item-link" @click.stop>الأمر</a>
                        </label>
                    @endforeach
                @endif
            </div>

            {{-- ---------- Bulk action bar ---------- --}}
            <div class="pipeline-bar" role="region" aria-label="إجراء على المحدد">
                <span class="pipeline-bar-count" aria-live="polite"><strong x-text="count">0</strong> محدد</span>

                @if ($stage === 'release')
                    <label for="pipeline-routing" class="visually-hidden">مسار التصنيع</label>
                    <select id="pipeline-routing" name="production_routing_id" class="form-select pipeline-bar-field" required>
                        <option value="">مسار التصنيع...</option>
                        @foreach ($routings as $routing)
                            <option value="{{ $routing->id }}" @selected((int) old('production_routing_id', $selectedRoutingId) === $routing->id)>{{ $routing->name_ar }} ({{ $routing->operations_count }} مراحل)</option>
                        @endforeach
                    </select>
                    <label for="pipeline-priority" class="visually-hidden">الأولوية</label>
                    <select id="pipeline-priority" name="priority" class="form-select pipeline-bar-field pipeline-bar-field-sm">
                        @foreach ($priorityLabels as $key => $label)
                            <option value="{{ $key }}" @selected(old('priority', 'NORMAL') === $key)>أولوية: {{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($stage === 'materials')
                    <label for="pipeline-warehouse" class="visually-hidden">المستودع</label>
                    <select id="pipeline-warehouse" name="warehouse_id" class="form-select pipeline-bar-field" required>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) $selectedWarehouseId === $warehouse->id)>من: {{ $warehouse->name_ar }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($stage === 'assign')
                    <div class="d-flex flex-wrap gap-2 align-items-center" x-data="{ mode: 'auto' }">
                        <div class="btn-group" role="radiogroup" aria-label="طريقة الإسناد">
                            <input type="radio" class="btn-check" name="mode" id="mode-auto" value="auto" x-model="mode">
                            <label class="btn btn-outline-primary" for="mode-auto" title="كل عملية تذهب لأقل موظف انشغالاً في قسمها">توزيع تلقائي</label>
                            <input type="radio" class="btn-check" name="mode" id="mode-employee" value="employee" x-model="mode">
                            <label class="btn btn-outline-primary" for="mode-employee">موظف محدد</label>
                        </div>
                        <template x-if="mode === 'employee'">
                            <span class="d-flex flex-wrap gap-2 align-items-center">
                                <label for="pipeline-employee" class="visually-hidden">الموظف</label>
                                <select id="pipeline-employee" name="employee_id" class="form-select pipeline-bar-field" required>
                                    <option value="">اختر الموظف...</option>
                                    @foreach ($departments as $department)
                                        @php $departmentEmployees = $employees->where('department_id', $department->id); @endphp
                                        @if ($departmentEmployees->isNotEmpty())
                                            <optgroup label="{{ $department->name_ar }}">
                                                @foreach ($departmentEmployees as $employee)
                                                    <option value="{{ $employee->id }}" data-department="{{ $department->id }}">{{ $employee->name }} — {{ $employee->open_tasks }} مهام مفتوحة</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                <span class="fs-8 text-warning-emphasis" x-show="departments.length > 1">العمليات المحددة من أكثر من قسم؛ ما لا يتبع قسم الموظف سيُتخطّى.</span>
                            </span>
                        </template>
                    </div>
                @endif

                <button type="submit" class="btn btn-primary pipeline-bar-submit" :disabled="count === 0 || busy">
                    <span x-show="busy" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <i class="fas {{ $stageMeta[$stage]['icon'] }}" aria-hidden="true" x-show="!busy"></i>
                    @switch($stage)
                        @case('approve') اعتماد المحدد للإنتاج @break
                        @case('release') إطلاق المحدد للورشة @break
                        @case('materials') طلب المواد للمحدد @break
                        @case('assign') إسناد المحدد @break
                    @endswitch
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
