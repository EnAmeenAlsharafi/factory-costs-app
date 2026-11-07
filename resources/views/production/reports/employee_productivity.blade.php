@extends('layouts.app')

@section('title', 'تقرير إنتاجية الموظفين - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="page-header-card d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">تقرير إنتاجية الموظفين</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">المهام المُسندة والمنجزة لكل موظف في الأقسام التشغيلية</p>
        </div>
        <a href="{{ route('production.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للتقارير
        </a>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">القسم</label>
                    <select name="department_id" class="form-select">
                        <option value="">-- كل الأقسام --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">من تاريخ</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-7">إلى تاريخ</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">الموظف</th>
                            <th>القسم</th>
                            <th class="text-center">عدد المهام المُسندة</th>
                            <th class="text-center">إجمالي الكمية المنجزة</th>
                            <th class="text-center">قيد التنفيذ</th>
                            <th class="text-center">متوقفة (عوائق)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byEmployee as $row)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $row['employee']->name ?? 'غير معروف' }}</td>
                                <td>{{ $row['employee']->department?->name_ar ?? '-' }}</td>
                                <td class="text-center">{{ $row['assigned_count'] }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($row['completed_quantity']) }}</td>
                                <td class="text-center">
                                    @if($row['in_progress_count'] > 0)
                                        <span class="badge bg-warning bg-opacity-10 text-dark">{{ $row['in_progress_count'] }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($row['blocked_count'] > 0)
                                        <span class="badge bg-danger bg-opacity-10 text-danger">{{ $row['blocked_count'] }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">لا توجد مهام مُسندة لموظفين ضمن الفلاتر المحددة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
