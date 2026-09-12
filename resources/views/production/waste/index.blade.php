@extends('layouts.app')

@section('title', 'الهدر والتلف التحليلي - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">سجلات الهدر والتلف التحليلي (Production Waste Log)</h4>
            <p class="text-muted mb-0 mt-1">تتبع التكلفة التحليلية للكميات التالفة والمعدومة أثناء العمليات الإنتاجية بدون ازدواجية محاسبية</p>
        </div>
        <div class="d-flex gap-2">
            @can('production.record_waste')
                <a href="{{ route('production.orders.index') }}" class="btn btn-danger">
                    <i class="fas fa-plus me-1"></i> تسجيل كمية هدر جديدة
                </a>
            @endcan
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم السجل</th>
                            <th>أمر الإنتاج</th>
                            <th>المادة الخام</th>
                            <th>الكمية التالفة</th>
                            <th>السبب التحليلي</th>
                            <th>القسم المتسبب</th>
                            <th>تكلفة الوحدة</th>
                            <th>التكلفة الإجمالية</th>
                            <th>تاريخ الحدوث</th>
                            <th>الاعتماد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wasteRecords as $waste)
                            <tr>
                                <td class="fw-bold font-monospace text-danger">{{ $waste->waste_number }}</td>
                                <td>
                                    <a href="{{ route('production.orders.show', $waste->productionOrder) }}" class="font-monospace text-dark text-decoration-none">
                                        {{ $waste->productionOrder?->production_order_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $waste->material?->name_ar }}</div>
                                    @if($waste->fabricColor)
                                        <small class="text-muted"><i class="fas fa-circle me-1" style="color: {{ $waste->fabricColor->hex_code ?? '#ccc' }}"></i>{{ $waste->fabricColor->color_name_ar }}</small>
                                    @endif
                                </td>
                                <td class="fw-bold text-danger">{{ (float)$waste->quantity }} {{ $waste->unit?->name_ar }}</td>
                                <td><span class="badge bg-light text-dark border fs-7">{{ $waste->wasteReason?->name_ar }}</span></td>
                                <td>{{ $waste->responsibleDepartment?->name_ar ?? $waste->detectedDepartment?->name_ar }}</td>
                                <td>
                                    @can('costing.view')
                                        {{ number_format($waste->unit_cost, 2) }} ر.س
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endcan
                                </td>
                                <td>
                                    @can('costing.view')
                                        <span class="fw-bold text-dark">{{ number_format($waste->total_cost, 2) }} ر.س</span>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endcan
                                </td>
                                <td>{{ $waste->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($waste->approved_by_user_id)
                                        <span class="badge bg-success">معتمد</span>
                                    @elseif(auth()->user()->can('production.approve_waste'))
                                        <form method="POST" action="{{ route('production.waste.approve', $waste) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success" type="submit">اعتماد</button>
                                        </form>
                                    @else
                                        <span class="badge bg-warning text-dark">بانتظار الاعتماد</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fas fa-dumpster fa-3x mb-3 d-block"></i>
                                    لا توجد سجلات هدر أو تلف مدونة حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($wasteRecords->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $wasteRecords->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
