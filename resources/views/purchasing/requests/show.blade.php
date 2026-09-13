@extends('layouts.app')

@section('title', 'تفاصيل طلب الشراء - ' . $purchaseRequest->request_number)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-signature me-2 text-primary"></i>طلب شراء رقم: {{ $purchaseRequest->request_number }}
            </h1>
            <p class="text-muted small mb-0">تاريخ الطلب: {{ $purchaseRequest->request_date->format('Y-m-d') }} | المستودع: {{ $purchaseRequest->warehouse?->name_ar }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('purchasing.requests.index') }}" class="btn btn-outline-secondary rounded-pill px-3">العودة للطلبات</a>
            @if($purchaseRequest->status === 'APPROVED')
                @can('purchasing.manage_rfq')
                <a href="{{ route('purchasing.rfqs.create', ['purchase_request_id' => $purchaseRequest->id]) }}" class="btn btn-info rounded-pill px-3 text-white">
                    <i class="fas fa-paper-plane me-1"></i>إنشاء طلب عروض أسعار (RFQ)
                </a>
                @endcan
                @can('purchasing.create_po')
                <a href="{{ route('purchasing.orders.create', ['purchase_request_id' => $purchaseRequest->id]) }}" class="btn btn-success rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i>إصدار أمر شراء (PO)
                </a>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <!-- Request Lines Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">بنود الخامات المطلوبة</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>اسم الخامة</th>
                                <th>لون القماش</th>
                                <th>الكمية المطلوبة</th>
                                <th>الوحدة الأساسية</th>
                                <th>المورد المفضل</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseRequest->lines as $line)
                            <tr>
                                <td class="fw-bold text-dark">{{ $line->material?->name_ar }}</td>
                                <td>{{ $line->fabricColor?->color_name_ar ?? '-' }}</td>
                                <td class="fw-bold fs-6 text-primary">{{ number_format($line->requested_quantity, 4) }}</td>
                                <td><span class="badge bg-secondary">{{ $line->baseUnit?->name_ar }}</span></td>
                                <td>{{ $line->preferredSupplier?->name ?? 'غير محدد' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Linked POs -->
            @if($purchaseRequest->purchaseOrders->count() > 0)
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">أوامر الشراء المرتبطة بهذا الطلب</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>رقم أمر الشراء</th>
                                <th>المورد</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseRequest->purchaseOrders as $po)
                            <tr>
                                <td>
                                    <a href="{{ route('purchasing.orders.show', $po) }}" class="fw-bold text-primary">
                                        {{ $po->purchase_order_number }}
                                    </a>
                                </td>
                                <td>{{ $po->supplier?->name }}</td>
                                <td class="fw-bold">{{ number_format($po->total_amount, 2) }} SAR</td>
                                <td><span class="badge bg-info">{{ $po->status }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Details Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">حالة الاعتماد والمراجعة</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <small class="text-muted d-block">الحالة الحالية:</small>
                        <span class="badge bg-primary fs-6 px-3 py-2 mt-1">{{ $purchaseRequest->status }}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">الأولوية:</small>
                        <span class="fw-bold text-dark">{{ $purchaseRequest->priority }}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">مقدم الطلب:</small>
                        <span class="fw-bold text-dark">{{ $purchaseRequest->requestedByUser?->name }}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">مبرر الشراء:</small>
                        <div class="p-2 bg-light rounded-3 small mt-1 text-dark">{{ $purchaseRequest->justification ?? 'لا يوجد مبرر مدخل' }}</div>
                    </div>

                    <!-- Approval Form -->
                    @if(in_array($purchaseRequest->status, ['SUBMITTED', 'UNDER_REVIEW']))
                        @can('purchasing.review_request')
                        <hr>
                        <form action="{{ route('purchasing.requests.review', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold">سبب الرفض (في حال الرفض):</label>
                                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" placeholder="مطلوب في حال اختيار رفض..."></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="APPROVE" class="btn btn-success rounded-pill fw-bold">
                                    <i class="fas fa-check-circle me-1"></i>اعتماد طلب الشراء
                                </button>
                                <button type="submit" name="action" value="REJECT" class="btn btn-outline-danger rounded-pill fw-bold">
                                    <i class="fas fa-times-circle me-1"></i>رفض الطلب
                                </button>
                            </div>
                        </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
