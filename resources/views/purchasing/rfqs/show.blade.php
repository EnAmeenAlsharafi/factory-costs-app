@extends('layouts.app')

@section('title', 'تفاصيل RFQ - ' . $purchaseRfq->rfq_number)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-paper-plane me-2 text-primary"></i>طلب عرض سعر رقم: {{ $purchaseRfq->rfq_number }}
            </h1>
            <p class="text-muted small mb-0">تاريخ الإصدار: {{ $purchaseRfq->issue_date->format('Y-m-d') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('purchasing.rfqs.index') }}" class="btn btn-outline-secondary rounded-pill px-3">العودة لطلبات الأسعار</a>
            @can('purchasing.manage_quotes')
            <a href="{{ route('purchasing.quotations.create', ['rfq_id' => $purchaseRfq->id]) }}" class="btn btn-success rounded-pill px-3">
                <i class="fas fa-plus me-1"></i>تسجيل عرض سعر مورد
            </a>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">الموردون المستهدفون بطلب السعر</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>اسم المورد</th>
                                <th>رقم الهاتف</th>
                                <th>حالة الاستجابة</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseRfq->rfqSuppliers as $sup)
                            <tr>
                                <td class="fw-bold">{{ $sup->supplier?->name }}</td>
                                <td>{{ $sup->supplier?->phone }}</td>
                                <td><span class="badge bg-info">{{ $sup->status }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($purchaseRfq->supplierQuotations->count() > 0)
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">عروض الأسعار المستلمة من الموردين</h6>
                    <a href="{{ route('purchasing.quotations.compare', ['purchase_request_id' => $purchaseRfq->purchase_request_id]) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                        <i class="fas fa-scale-balanced me-1"></i>جدول مقارنة عروض الموردين
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>رقم عرض السعر</th>
                                <th>المورد</th>
                                <th>الإجمالي</th>
                                <th>مدة التوريد</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseRfq->supplierQuotations as $sq)
                            <tr>
                                <td>
                                    <a href="{{ route('purchasing.quotations.show', $sq) }}" class="fw-bold text-primary">
                                        {{ $sq->supplier_quotation_number }}
                                    </a>
                                </td>
                                <td>{{ $sq->supplier?->name }}</td>
                                <td class="fw-bold">{{ number_format($sq->total_amount, 2) }} SAR</td>
                                <td>{{ $sq->lead_time_days }} أيام</td>
                                <td>
                                    @if($sq->status === 'SELECTED')
                                    <span class="badge bg-success">تم اختياره كمرجعية</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $sq->status }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">بيانات الطلب</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <small class="text-muted d-block">طلب الشراء المرتبط:</small>
                        <span class="fw-bold text-dark">{{ $purchaseRfq->purchaseRequest?->request_number ?? 'غير مرتبط بطلب محدد' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">تاريخ الموعد النهائي:</small>
                        <span class="fw-bold text-dark">{{ $purchaseRfq->response_due_date ? $purchaseRfq->response_due_date->format('Y-m-d') : 'غير محدد' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">الحالة:</small>
                        <span class="badge bg-primary fs-6 px-3 py-2 mt-1">{{ $purchaseRfq->status }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
