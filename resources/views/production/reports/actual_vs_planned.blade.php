@extends('layouts.app')

@section('title', 'تقرير التكلفة الفعلية مقابل المخططة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">تقرير التكلفة الفعلية للمواد مقابل المخطط (Actual vs BOM Cost Report)</h4>
            <p class="text-muted mb-0 mt-1">حساب صافي التكلفة الفعلية = (إجمالي المصروف - إجمالي المرتجع للمخزن)</p>
        </div>
        <a href="{{ route('production.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للتقارير
        </a>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>العميل والمنتج</th>
                            <th>إجمالي المصروف</th>
                            <th>إجمالي المرتجع</th>
                            <th>صافي التكلفة الفعلية</th>
                            <th>تكلفة الهدر (تحليلي)</th>
                            <th>التكلفة المخططة (BOM)</th>
                            <th>الانحراف (Variance)</th>
                            <th>نسبة الانحراف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php
                                $report = $costReports[$order->id] ?? [];
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('production.orders.show', $order) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $order->production_order_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $order->productModel?->name_ar ?? $order->custom_design_name }}</div>
                                    <small class="text-muted">{{ $order->customerOrder?->customer?->name_ar }}</small>
                                </td>
                                <td>
                                    @if($canViewCost)
                                        {{ number_format($report['total_issued_cost'] ?? 0, 2) }} ر.س
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        <span class="text-success">{{ number_format($report['total_returned_cost'] ?? 0, 2) }} ر.س</span>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        <strong class="text-dark fs-6">{{ number_format($report['actual_net_material_cost'] ?? 0, 2) }} ر.س</strong>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        <span class="text-danger fw-bold">{{ number_format($report['total_waste_cost'] ?? 0, 2) }} ر.س</span>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        {{ number_format($report['planned_bom_cost'] ?? 0, 2) }} ر.س
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        @php $var = $report['cost_variance'] ?? 0; @endphp
                                        <span class="fw-bold {{ $var > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $var > 0 ? '+' : '' }}{{ number_format($var, 2) }} ر.س
                                        </span>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canViewCost)
                                        @php $varPct = $report['cost_variance_percentage'] ?? 0; @endphp
                                        <span class="badge {{ $varPct > 0 ? 'bg-danger' : 'bg-success' }}">
                                            {{ $varPct > 0 ? '+' : '' }}{{ $varPct }}%
                                        </span>
                                    @else
                                        <span class="text-muted small">سري</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">لا توجد أية أستعلامات متوفرة لأوامر الإنتاج.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
