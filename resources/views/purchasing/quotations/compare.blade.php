@extends('layouts.app')

@section('title', 'مقارنة عروض أسعار الموردين')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-table-columns me-2 text-primary"></i>جدول مقارنة عروض الأسعار التجارية (Supplier Comparison Matrix)
            </h1>
            <p class="text-muted small mb-0">مقارنة التكلفة الموحدة لكل وحدة أساسية، مدة التوريد، وشروط الموردين لتسهيل اتخاذ قرار الشراء</p>
        </div>
        <a href="{{ route('purchasing.quotations.index') }}" class="btn btn-outline-secondary rounded-pill px-4">سجل عروض الموردين</a>
    </div>

    <!-- PR Filter -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('purchasing.quotations.compare') }}" method="GET" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label small fw-bold">تصفية بناءً على طلب الشراء:</label>
                    <select name="purchase_request_id" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع عروض الأسعار المسجلة</option>
                        @foreach($approvedRequests as $pr)
                        <option value="{{ $pr->id }}" {{ request('purchase_request_id') == $pr->id ? 'selected' : '' }}>
                            {{ $pr->request_number }} - {{ $pr->justification }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Matrix Table per Material -->
    @forelse($comparisonMatrix as $matKey => $matData)
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-box text-primary me-2"></i>{{ $matData['material_name'] }}
                    @if($matData['color_name']) <span class="badge bg-info ms-2">لون: {{ $matData['color_name'] }}</span> @endif
                </h6>
                <small class="text-muted">الوحدة الأساسية القياسية: {{ $matData['base_unit'] }}</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="bg-light small">
                    <tr>
                        <th class="text-start">المورد</th>
                        <th>رقم عرض السعر</th>
                        <th>الكمية المعروضة</th>
                        <th>وحدة الشراء للمورد</th>
                        <th>سعر وحدة الشراء</th>
                        <th class="bg-warning bg-opacity-10 text-dark fw-bold">السعر التنافسي المكافئ للوحدة الأساسية</th>
                        <th>إجمالي البند</th>
                        <th>مدة التوريد</th>
                        <th>الحالة التجارية</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @foreach($matData['quotes'] as $q)
                    <tr class="{{ $q['is_selected'] ? 'table-success' : '' }}">
                        <td class="text-start fw-bold text-dark">{{ $q['supplier_name'] }}</td>
                        <td class="fw-bold">{{ $q['quotation_number'] }}</td>
                        <td>{{ number_format($q['quoted_quantity'], 2) }}</td>
                        <td><span class="badge bg-secondary">{{ $q['purchase_unit'] }}</span></td>
                        <td>{{ number_format($q['unit_price'], 2) }} SAR</td>
                        <td class="bg-warning bg-opacity-10 fw-bold fs-6 text-primary">
                            {{ number_format($q['base_unit_equivalent_price'], 4) }} SAR / {{ $matData['base_unit'] }}
                        </td>
                        <td class="fw-bold">{{ number_format($q['line_total'], 2) }} SAR</td>
                        <td>{{ $q['lead_time_days'] ? $q['lead_time_days'] . ' أيام' : '-' }}</td>
                        <td>
                            @if($q['is_selected'])
                            <span class="badge bg-success">العرض المعتمد</span>
                            @else
                            <span class="badge bg-light text-dark border">قيد الدراسة</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('purchasing.quotations.show', $q['quotation_id']) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                عرض التفاصيل
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
        <div class="card-body">
            <i class="fas fa-scale-balanced text-muted display-4 mb-3"></i>
            <h6 class="fw-bold text-dark">لا توجد عروض أسعار متوفرة للمقارنة حالياً</h6>
            <p class="text-muted small">قم بتسجيل عروض أسعار الموردين أولاً لإجراء المقارنات التنافسية.</p>
        </div>
    </div>
    @endforelse
</div>
@endsection
