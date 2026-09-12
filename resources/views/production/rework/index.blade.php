@extends('layouts.app')

@section('title', 'إعادة التصنيع والإصلاح - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">أوامر إعادة التصنيع والإصلاح (Rework & Repair Operations)</h4>
            <p class="text-muted mb-0 mt-1">متابعة تنفيذ أوامر التعديل والإصلاح الإضافية الناتجة عن حالات الجودة بالورشة</p>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم الأمر</th>
                            <th>بلاغ الجودة المرتبط</th>
                            <th>أمر الإنتاج</th>
                            <th>نوع الإجراء</th>
                            <th>الكمية</th>
                            <th>القسم المكلف</th>
                            <th>تاريخ البدء</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reworks as $rework)
                            <tr>
                                <td class="fw-bold font-monospace text-primary">{{ $rework->rework_number }}</td>
                                <td>
                                    <a href="{{ route('production.quality-incidents.show', $rework->qualityIncident) }}" class="font-monospace text-danger text-decoration-none">
                                        {{ $rework->qualityIncident?->incident_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('production.orders.show', $rework->productionOrder) }}" class="font-monospace text-dark text-decoration-none">
                                        {{ $rework->productionOrder?->production_order_number }}
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $rework->action_type }}</span></td>
                                <td><span class="fw-bold">{{ $rework->quantity }} قطعة</span></td>
                                <td>{{ $rework->assignedDepartment?->name_ar }}</td>
                                <td>{{ $rework->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($rework->status === 'COMPLETED')
                                        <span class="badge bg-success">مكتمل ומُنجز</span>
                                    @else
                                        <span class="badge bg-warning text-dark">قيد الإنجاز</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($rework->status !== 'COMPLETED' && auth()->user()->can('production.manage_rework'))
                                        <form action="{{ route('production.rework.complete', $rework) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i> إنجاز الإجراء
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-tools fa-3x mb-3 d-block"></i>
                                    لا توجد أوامر إعادة تصنيع أو إصلاح مسجلة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($reworks->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $reworks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
