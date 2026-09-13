@extends('layouts.app')

@section('title', 'تخطيط المشتريات ومتابعة النواقص')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-chart-pie me-2 text-primary"></i>تخطيط المشتريات والاحتياجات المخزنية
            </h1>
            <p class="text-muted small mb-0">مراقبة رصيد الخامات مقابل حد الإعادة وحساب الكميات المطلوبة لشراء المواد</p>
        </div>
    </div>

    <!-- Filters & Bulk PR Form -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('purchasing.planning.index') }}" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">تصنيف الخامات:</label>
                    <select name="category_id" class="form-select">
                        <option value="">جميع التصنيفات</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name_ar }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="only_deficit" value="1" id="onlyDeficit" {{ request('only_deficit') ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold text-danger" for="onlyDeficit">
                            عرض المواد تحت حد الإعادة والنواقص فقط
                        </label>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-filter me-1"></i>تطبيق التصفية
                    </button>
                    <a href="{{ route('purchasing.planning.index') }}" class="btn btn-outline-secondary rounded-pill px-3">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Planning Matrix Table -->
    <form action="{{ route('purchasing.planning.bulk-request') }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">جدول تخطيط خامات المصنع</h6>
                <div class="d-flex align-items-center gap-2">
                    <select name="warehouse_id" class="form-select form-select-sm" required style="width: 220px;">
                        <option value="">اختر المستودع المستهدف...</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name_ar }} ({{ $w->code }})</option>
                        @endforeach
                    </select>
                    @can('purchasing.request')
                    <button type="submit" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark px-3">
                        <i class="fas fa-plus-circle me-1"></i>توليد طلب شراء للمواد المحددة
                    </button>
                    @endcan
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light small">
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>رمز الخامة واسمها</th>
                            <th>التصنيف</th>
                            <th>الوحدة</th>
                            <th>المخزون الحالي</th>
                            <th>حد الأمان / حد الإعادة</th>
                            <th>قادم من POs</th>
                            <th>حالة التوفر</th>
                            <th>الكمية المقترحة للشراء</th>
                            <th width="120">الكمية المطلوبة</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($planningMatrix as $idx => $row)
                        <tr class="{{ $row['status'] === 'BELOW_MINIMUM' ? 'table-danger' : ($row['status'] === 'REORDER' ? 'table-warning' : '') }}">
                            <td class="text-center">
                                @if($row['status'] !== 'OK')
                                <input type="checkbox" name="items[{{ $idx }}][material_id]" value="{{ $row['material_id'] }}" checked class="form-check-input">
                                @endif
                            </td>
                            <td class="fw-bold text-dark">
                                {{ $row['material_name'] }}
                                <div class="text-muted fs-8">{{ $row['material_code'] }}</div>
                            </td>
                            <td>{{ $row['category_name'] }}</td>
                            <td><span class="badge bg-secondary">{{ $row['unit_name'] }}</span></td>
                            <td class="fw-bold fs-6">{{ number_format($row['current_stock'], 2) }}</td>
                            <td>
                                <span class="text-danger fw-bold">{{ number_format($row['min_stock_level'], 2) }}</span> /
                                <span class="text-warning fw-bold">{{ number_format($row['reorder_level'], 2) }}</span>
                            </td>
                            <td class="text-info fw-bold">+{{ number_format($row['open_po_incoming_quantity'], 2) }}</td>
                            <td>
                                @if($row['status'] === 'BELOW_MINIMUM')
                                <span class="badge bg-danger">تحت النواقص الحادة</span>
                                @elseif($row['status'] === 'REORDER')
                                <span class="badge bg-warning text-dark">تحت حد إعادة الطلب</span>
                                @else
                                <span class="badge bg-success">متوفر بشكل آمن</span>
                                @endif
                            </td>
                            <td class="fw-bold text-primary">{{ number_format($row['suggested_reorder_quantity'], 2) }}</td>
                            <td>
                                @if($row['status'] !== 'OK')
                                <input type="number" step="0.0001" min="0.0001" name="items[{{ $idx }}][requested_quantity]" value="{{ $row['suggested_reorder_quantity'] > 0 ? $row['suggested_reorder_quantity'] : 10 }}" class="form-control form-control-sm">
                                @else
                                -
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">لا توجد مواد تطابق خيارات التصفية الحالية.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
@endsection
