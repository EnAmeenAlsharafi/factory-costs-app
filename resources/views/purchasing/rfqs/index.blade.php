@extends('layouts.app')

@section('title', 'طلبات عروض الأسعار (RFQ)')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-paper-plane me-2 text-primary"></i>طلبات عروض الأسعار (Request for Quotations)
            </h1>
            <p class="text-muted small mb-0">إرسال واستدراج عروض أسعار الخامات والمواد من الموردين المعتمدين</p>
        </div>
        @can('purchasing.manage_rfq')
        <a href="{{ route('purchasing.rfqs.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i>طلب عرض سعر جديد (RFQ)
        </a>
        @endcan
    </div>

    <!-- RFQs Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light small">
                    <tr>
                        <th>رقم طلب عرض السعر</th>
                        <th>تاريخ الإصدار</th>
                        <th>طلب الشراء المرتبط</th>
                        <th>الموردون المستهدفون</th>
                        <th>الحالة</th>
                        <th>منشئ الطلب</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($rfqs as $rfq)
                    <tr>
                        <td class="fw-bold text-primary">{{ $rfq->rfq_number }}</td>
                        <td>{{ $rfq->issue_date->format('Y-m-d') }}</td>
                        <td>{{ $rfq->purchaseRequest?->request_number ?? '-' }}</td>
                        <td>
                            @foreach($rfq->rfqSuppliers as $sup)
                            <span class="badge bg-light text-dark border me-1">{{ $sup->supplier?->name }}</span>
                            @endforeach
                        </td>
                        <td><span class="badge bg-info">{{ $rfq->status }}</span></td>
                        <td>{{ $rfq->createdByUser?->name }}</td>
                        <td class="text-end">
                            <a href="{{ route('purchasing.rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                التفاصيل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">لا توجد طلبات عروض أسعار مسجلة.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rfqs->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $rfqs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
