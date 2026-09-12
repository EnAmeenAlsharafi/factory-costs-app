@extends('layouts.app')

@section('title', 'أرصدة المخزون والدفعات')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">أرصدة ودفعات المخزون</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">أرصدة المواد الخام والتشغيل والدفعات (Stock Balances & Lots)</h1>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-7 fw-semibold d-block">إجمالي قيمة المواد المسجلة بالدفعات</span>
                            <h3 class="fw-bold mb-0 text-primary mt-1">@can('costing.view'){{ number_format($totalInventoryValue, 2) }} <small class="fs-6 text-muted">ر.س</small>@else<span class="fs-6 text-muted">سري</span>@endcan</h3>
                        </div>
                        <div class="avatar-circle bg-primary bg-opacity-10 text-primary rounded-3 p-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-coins fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-7 fw-semibold d-block">عدد المواد ذات الرصيد المتاح</span>
                            <h3 class="fw-bold mb-0 text-success mt-1">{{ $materialsWithStockCount }} <small class="fs-6 text-muted">صنف</small></h3>
                        </div>
                        <div class="avatar-circle bg-success bg-opacity-10 text-success rounded-3 p-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-boxes-stacked fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-7 fw-semibold d-block">عدد الدفعات المتاحة النشطة</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1">{{ $activeLotsCount }} <small class="fs-6 text-muted">دفعة (Lot)</small></h3>
                        </div>
                        <div class="avatar-circle bg-warning bg-opacity-10 text-warning rounded-3 p-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-barcode fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventory.balances.index') }}" class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="بحث باسم المادة، الكود، أو رقم الدفعة..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <select name="category_id" class="form-select bg-light">
                        <option value="">كل تصنيفات المواد</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name_ar }}</option>
                        @endforeach
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
                    @if(request()->anyFilled(['search', 'category_id', 'warehouse_id']))
                        <a href="{{ route('inventory.balances.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs Nav -->
    <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="stockTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials-pane" type="button" role="tab">
                <i class="fas fa-layer-group me-1"></i> ملخص أرصدة المواد المحسوبة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="lots-tab" data-bs-toggle="tab" data-bs-target="#lots-pane" type="button" role="tab">
                <i class="fas fa-barcode me-1"></i> تفاصيل الدفعات والتشغيلات (Lots)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="stockTabsContent">

        <!-- Pane 1: Aggregated Material Balances -->
        <div class="tab-pane fade show active" id="materials-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">كود المادة</th>
                                    <th>اسم المادة الخاملة</th>
                                    <th>التصنيف</th>
                                    <th>الوحدة الأساسية</th>
                                    <th class="text-center">إجمالي الوارد (IN)</th>
                                    <th class="text-center">إجمالي المنصرف (OUT)</th>
                                    <th class="text-center">الرصيد الصافي (Balance)</th>
                                    <th class="text-end pe-3">إجمالي القيمة المسجلة بالدفعات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($materials as $material)
                                    <tr>
                                        <td class="ps-3 fw-mono text-muted">{{ $material->code }}</td>
                                        <td>
                                            <a href="{{ route('materials.show', $material) }}" class="fw-bold text-decoration-none text-dark">
                                                {{ $material->name_ar }}
                                            </a>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">{{ $material->category->name_ar }}</span></td>
                                        <td>{{ $material->baseUnit->name_ar }}</td>
                                        <td class="text-center fw-mono text-success">+{{ number_format($material->total_in, 2) }}</td>
                                        <td class="text-center fw-mono text-danger">-{{ number_format($material->total_out, 2) }}</td>
                                        <td class="text-center">
                                            @if($material->stock_balance > 0)
                                                <span class="badge bg-success bg-opacity-10 text-success fs-7 fw-bold px-3 py-2">
                                                    {{ number_format($material->stock_balance, 2) }} {{ $material->baseUnit->name_ar }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary fs-7 px-3 py-2">0.00</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 fw-mono fw-bold">
                                            @can('costing.view'){{ number_format($material->total_lot_value, 2) }} <small class="text-muted fs-8">ر.س</small>@else<span class="text-muted">سري</span>@endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fs-1 d-block mb-3 text-secondary"></i>
                                            لا توجد مواد أو أرصدة مطابقة لشروط البحث
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($materials->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $materials->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Pane 2: Active Inventory Lots -->
        <div class="tab-pane fade" id="lots-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">رقم الدفعة (Lot Code)</th>
                                    <th>المادة الخام</th>
                                    <th>المستودع</th>
                                    <th>تاريخ الورود</th>
                                    <th class="text-center">الكمية الأولية</th>
                                    <th class="text-center">المتبقي (Remaining)</th>
                                    <th class="text-end">تكلفة الوحدة</th>
                                    <th class="text-end pe-3">إجمالي قيمة المتبقي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lots as $lot)
                                    <tr>
                                        <td class="ps-3 fw-mono fw-bold text-primary">
                                            <a href="{{ route('inventory.lots.show', $lot) }}" class="text-decoration-none">
                                                {{ $lot->lot_code }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $lot->material->name_ar }}</div>
                                            <small class="text-muted fw-mono">{{ $lot->material->code }}</small>
                                        </td>
                                        <td><span class="badge bg-info bg-opacity-10 text-dark border-0">{{ $lot->warehouse->name_ar }}</span></td>
                                        <td>{{ $lot->received_date->format('Y-m-d') }}</td>
                                        <td class="text-center fw-mono">{{ number_format($lot->original_quantity, 2) }} {{ $lot->material->baseUnit->name_ar }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $lot->remaining_quantity > 0 ? 'bg-success' : 'bg-secondary' }} px-2 py-1 fs-7 fw-mono">
                                                {{ number_format($lot->remaining_quantity, 2) }} {{ $lot->material->baseUnit->name_ar }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-mono">@can('costing.view'){{ number_format($lot->unit_cost, 2) }} ر.س@else<span class="text-muted">سري</span>@endcan</td>
                                        <td class="text-end pe-3 fw-mono fw-bold text-dark">
                                            @can('costing.view'){{ number_format($lot->remaining_quantity * $lot->unit_cost, 2) }} <small class="text-muted fs-8">ر.س</small>@else<span class="text-muted">سري</span>@endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-barcode fs-1 d-block mb-3 text-secondary"></i>
                                            لا توجد دفعات مخزنية مسجلة
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
