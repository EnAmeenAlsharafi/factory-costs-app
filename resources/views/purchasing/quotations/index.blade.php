@extends('layouts.app')

@section('title', 'عروض أسعار الموردين')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-scale-balanced me-2 text-primary"></i>عروض أسعار الموردين (Supplier Quotations)
            </h1>
            <p class="text-muted small mb-0">سجل عروض أسعار الموردين وإجراء المقارنات التجارية واختيار المورد المناسب</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('purchasing.quotations.compare') }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-table-columns me-1"></i>مقارنة عروض الموردين
            </a>
            @can('purchasing.manage_quotes')
            <a href="{{ route('purchasing.quotations.create') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-1"></i>تسجيل عرض سعر مورد
            </a>
            @endcan
        </div>
    </div>

    <!-- Quotations Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light small">
                    <tr>
                        <th>رقم عرض السعر</th>
                        <th>المورد</th>
                        <th>تاريخ العرض</th>
                        <th>مدة التوريد</th>
                        <th>إجمالي العرض</th>
                        <th>شروط الدفع</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($quotations as $sq)
                    <tr class="{{ $sq->status === 'SELECTED' ? 'table-success' : '' }}">
                        <td class="fw-bold text-primary">{{ $sq->supplier_quotation_number }}</td>
                        <td class="fw-bold text-dark">{{ $sq->supplier?->name }}</td>
                        <td>{{ $sq->quotation_date->format('Y-m-d') }}</td>
                        <td>{{ $sq->lead_time_days ? $sq->lead_time_days . ' أيام' : '-' }}</td>
                        <td class="fw-bold fs-6 text-dark">{{ number_format($sq->total_amount, 2) }} SAR</td>
                        <td><span class="badge bg-light text-dark border">{{ $sq->payment_terms ?? 'نقداً' }}</span></td>
                        <td>
                            @if($sq->status === 'SELECTED')
                            <span class="badge bg-success">تم الاختيار للمشتروات</span>
                            @else
                            <span class="badge bg-secondary">{{ $sq->status }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('purchasing.quotations.show', $sq) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                التفاصيل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا توجد عروض أسعار موردين مسجلة.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($quotations->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $quotations->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
