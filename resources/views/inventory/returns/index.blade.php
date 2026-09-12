@extends('layouts.app')

@section('title', 'سندات إرجاع المواد للمخزن')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">سندات إرجاع المواد</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">سندات إرجاع المواد للمخزن (Material Returns)</h1>
        </div>

        @can('inventory.return')
            <div>
                <a href="{{ route('inventory.returns.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> إنشاء سند إرجاع جديد
                </a>
            </div>
        @endcan
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventory.returns.index') }}" class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="بحث برقم السند..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <select name="status" class="form-select bg-light">
                        <option value="">كل الحالات</option>
                        <option value="DRAFT" {{ request('status') == 'DRAFT' ? 'selected' : '' }}>مسودة (Draft)</option>
                        <option value="POSTED" {{ request('status') == 'POSTED' ? 'selected' : '' }}>مُرحل (Posted)</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <select name="department_id" class="form-select bg-light">
                        <option value="">كل الأقسام</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    @if(request()->anyFilled(['search', 'status', 'department_id']))
                        <a href="{{ route('inventory.returns.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">رقم السند</th>
                            <th>تاريخ الإرجاع</th>
                            <th>المستودع المستقبل</th>
                            <th>القسم المرجع</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-end">إجمالي التكلفة المرجعة</th>
                            <th class="text-center pe-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td class="ps-3 fw-mono fw-bold text-primary">
                                    <a href="{{ route('inventory.returns.show', $return) }}" class="text-decoration-none">
                                        {{ $return->return_number }}
                                    </a>
                                </td>
                                <td>{{ $return->return_date->format('Y-m-d') }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $return->warehouse->name_ar }}</span></td>
                                <td><span class="badge bg-info bg-opacity-10 text-dark border-0">{{ $return->department?->name_ar ?? 'غير محدد' }}</span></td>
                                <td class="fw-mono text-muted">
                                    @if($return->issue)
                                        <a href="{{ route('inventory.issues.show', $return->issue) }}" class="text-decoration-none">
                                            {{ $return->issue->issue_number }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($return->status === 'POSTED')
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">مُرحل (Posted)</span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-1">مسودة (Draft)</span>
                                    @endif
                                </td>
                                <td class="text-end fw-mono fw-bold text-dark">@can('costing.view'){{ number_format($return->total_cost, 2) }} ر.س@else<span class="text-muted">سري</span>@endcan</td>
                                <td class="text-center pe-3">
                                    <a href="{{ route('inventory.returns.show', $return) }}" class="btn btn-sm btn-outline-secondary" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-undo-alt fs-1 d-block mb-3 text-secondary"></i>
                                    لا توجد سندات إرجاع مواد مسجلة
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($returns->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $returns->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
