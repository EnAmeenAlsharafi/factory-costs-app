@extends('layouts.app')

@section('title', 'إسناد العمليات - مصنع مفروشات سدير')
@section('page-title', 'إسناد العمليات')

@section('content')
@php
    $priorityLabels = ['NORMAL' => 'عادي', 'URGENT' => 'عاجل', 'VIP' => 'VIP'];
    $employeesByDepartment = $employees->groupBy('department_id');
    $activeFilterCount = collect(['department_id', 'priority', 'unassigned_only'])->filter(fn ($key) => request()->filled($key))->count();
@endphp

<div class="d-flex flex-column gap-3">
    <div class="page-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <h1 class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-user-gear text-primary fs-5" aria-hidden="true"></i>
                    <span>لوحة إسناد العمليات</span>
                </h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">إسناد وإعادة إسناد العمليات الجاهزة للموظفين، مع عرض حمل العمل الحالي لكل موظف.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('production.assignment.manage')
                    <a href="{{ route('production.pipeline.index', 'assign') }}" class="btn btn-primary">
                        <i class="fas fa-layer-group" aria-hidden="true"></i> إسناد عدة عمليات دفعة واحدة
                    </a>
                @endcan
                <a href="{{ route('production.board.index') }}" class="btn btn-outline-primary">
                    <i class="fas fa-table-columns" aria-hidden="true"></i> لوحة متابعة الإنتاج
                </a>
            </div>
        </div>
    </div>

    <x-filter-sheet id="assignment-filters" :action="route('production.assignment.index')" :active-count="$activeFilterCount" :reset-url="route('production.assignment.index')">
        <div class="col-12 col-md-4">
            <label for="assign-department" class="form-label fs-7">القسم</label>
            <select name="department_id" id="assign-department" class="form-select">
                <option value="">جميع الأقسام التشغيلية</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>قسم {{ $dept->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label for="assign-priority" class="form-label fs-7">الأولوية</label>
            <select name="priority" id="assign-priority" class="form-select">
                <option value="">جميع الأولويات</option>
                @foreach ($priorityLabels as $priorityKey => $priorityLabel)
                    <option value="{{ $priorityKey }}" @selected(request('priority') === $priorityKey)>{{ $priorityLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" name="unassigned_only" id="unassigned-only" @checked(request()->boolean('unassigned_only'))>
                <label class="form-check-label fs-7" for="unassigned-only">غير مُسندة فقط</label>
            </div>
        </div>
    </x-filter-sheet>

    <div class="row g-4">
        <div class="col-lg-8">
            <h2 class="h6 fw-bold text-dark mb-3">العمليات الجاهزة والمُسندة <span class="count">{{ $operations->total() }}</span></h2>

            @if ($operations->isEmpty())
                <div class="card-factory empty-state" role="status">
                    <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
                    <p class="mb-0 fw-semibold">لا توجد عمليات جاهزة أو مُسندة مطابقة للفلتر الحالي.</p>
                </div>
            @else
                <div class="table-factory-wrapper">
                    <div class="table-responsive">
                        <table class="table table-factory table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>المنتج / أمر الإنتاج</th>
                                    <th>العملية / القسم</th>
                                    <th>الحالة</th>
                                    <th>المُسند إليه</th>
                                    <th class="text-end">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($operations as $op)
                                    @php
                                        $po = $op->productionOrder;
                                        $deptId = $op->workCenter?->department_id;
                                        $deptEmployees = $employeesByDepartment->get($deptId, collect());
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $po->display_name }}</div>
                                            <a href="{{ route('production.orders.show', $po) }}" class="fs-8 font-monospace text-decoration-none">{{ $po->production_order_number }}</a>
                                            @if (in_array($po->priority, ['URGENT', 'VIP'], true))
                                                <span class="status-chip status-chip-danger ms-1"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$po->priority] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $op->operation_name_snapshot }}</div>
                                            <small class="text-muted">{{ $op->workCenter?->department?->name_ar }}</small>
                                        </td>
                                        <td><x-status-badge domain="operation" :status="$op->status" /></td>
                                        <td>{{ $op->assignedUser?->name ?? 'غير مُسند' }}</td>
                                        <td class="text-end" style="min-width: 220px;">
                                            @if ($deptEmployees->isEmpty())
                                                <span class="text-muted fs-8">لا يوجد موظفون في هذا القسم</span>
                                            @else
                                                <form action="{{ route('production.operations.assign', $op) }}" method="POST" class="d-flex gap-1 justify-content-end">
                                                    @csrf
                                                    <select name="employee_id" class="form-select form-select-sm" required style="max-width: 160px;">
                                                        <option value="" disabled selected>اختر موظفاً...</option>
                                                        @foreach ($deptEmployees as $employee)
                                                            <option value="{{ $employee->id }}" @selected($op->assigned_user_id === $employee->id)>
                                                                {{ $employee->name }} ({{ $employee->active_tasks_count }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        {{ $op->assigned_user_id ? 'إعادة إسناد' : 'إسناد' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($operations->hasPages())
                    <div class="d-flex justify-content-center mt-3">{{ $operations->links() }}</div>
                @endif
            @endif
        </div>

        <div class="col-lg-4">
            <h2 class="h6 fw-bold text-dark mb-3">حمل عمل الموظفين</h2>
            <div class="card-factory p-0">
                <ul class="list-group list-group-flush">
                    @forelse ($employees as $employee)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-dark">{{ $employee->name }}</div>
                                <small class="text-muted">{{ $employee->department?->name_ar }}</small>
                            </div>
                            <div class="d-flex gap-1">
                                <span class="status-chip status-chip-warning" title="مهام نشطة (مُسندة/قيد التنفيذ)">{{ $employee->active_tasks_count }}</span>
                                <span class="status-chip status-chip-info" title="مهام جاهزة في قسمه">{{ $employee->ready_tasks_count }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted text-center py-4">لا يوجد موظفون مطابقون للفلتر الحالي.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
