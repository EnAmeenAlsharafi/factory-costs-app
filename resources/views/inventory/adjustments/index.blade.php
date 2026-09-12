@extends('layouts.app')

@section('title', 'تسويات المخزون والجرد')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">تسويات المخزون</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">تسويات وتعديلات أرصدة المخزون (Stock Adjustments)</h1>
        </div>

        @can('inventory.adjust')
            <div>
                <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> إنشاء تسوية مخزنية جديدة
                </a>
            </div>
        @endcan
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventory.adjustments.index') }}" class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="بحث برقم التسوية..." value="{{ request('search') }}">
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
                    <select name="warehouse_id" class="form-select bg-light">
                        <option value="">كل المستودعات</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    @if(request()->anyFilled(['search', 'status', 'warehouse_id']))
                        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Adjustments Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">رقم التسوية</th>
                            <th>تاريخ التسوية</th>
                            <th>المستودع</th>
                            <th>سبب التسوية والجرد</th>
                            <th>أنشئ بواسطة</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center pe-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adjustments as $adj)
                            <tr>
                                <td class="ps-3 fw-mono fw-bold text-primary">
                                    <a href="{{ route('inventory.adjustments.show', $adj) }}" class="text-decoration-none">
                                        {{ $adj->adjustment_number }}
                                    </a>
                                </td>
                                <td>{{ $adj->adjustment_date->format('Y-m-d') }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $adj->warehouse->name_ar }}</span></td>
                                <td><span class="badge bg-info bg-opacity-10 text-dark border-0">{{ $adj->reason->name_ar }}</span></td>
                                <td>{{ $adj->user->name }}</td>
                                <td class="text-center">
                                    @if($adj->status === 'POSTED')
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">مُرحل (Posted)</span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-1">مسودة (Draft)</span>
                                    @endif
                                </td>
                                <td class="text-center pe-3">
                                    <a href="{{ route('inventory.adjustments.show', $adj) }}" class="btn btn-sm btn-outline-secondary" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-sliders-h fs-1 d-block mb-3 text-secondary"></i>
                                    لا توجد تسويات مخزنية مسجلة
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($adjustments->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $adjustments->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
