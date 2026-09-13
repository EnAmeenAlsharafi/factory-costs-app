@extends('layouts.app')

@section('title', 'أمر الشراء - ' . $purchaseOrder->purchase_order_number)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-contract me-2 text-primary"></i>أمر شراء رقم: {{ $purchaseOrder->purchase_order_number }}
            </h1>
            <p class="text-muted small mb-0">المورد: {{ $purchaseOrder->supplier?->name }} | التاريخ: {{ $purchaseOrder->order_date->format('Y-m-d') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('purchasing.orders.index') }}" class="btn btn-outline-secondary rounded-pill px-3">العودة للأوامر</a>
            <a href="{{ route('purchasing.orders.print', $purchaseOrder) }}" target="_blank" class="btn btn-outline-dark rounded-pill px-3">
                <i class="fas fa-print me-1"></i>طباعة أمر الشراء
            </a>

            @if(in_array($purchaseOrder->status, ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED']))
                @can('inventory.receive')
                <form action="{{ route('purchasing.orders.receive-form', $purchaseOrder) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-truck-loading me-1"></i>استلام خامات بالمخزن (Stage 5)
                    </button>
                </form>
                @endcan
            @endif

            @if(in_array($purchaseOrder->status, ['DRAFT', 'PENDING_APPROVAL']))
                @can('purchasing.approve_po')
                <form action="{{ route('purchasing.orders.approve', $purchaseOrder) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-check-circle me-1"></i>اعتماد أمر الشراء
                    </button>
                </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <!-- Order Lines & Receipt Progress -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">بنود أمر الشراء وتتبع الاستلام بالمخزن</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>اسم الخامة</th>
                                <th>الكمية المطلوبة (وحدة الشراء)</th>
                                <th>سعر الوحدة Agreed</th>
                                <th>إجمالي البند</th>
                                <th>المستلم فعلياً (وحدة أساسية)</th>
                                <th>المتبقي للاستلام</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseOrder->lines as $line)
                            <tr>
                                <td class="fw-bold text-dark">
                                    {{ $line->material?->name_ar }}
                                    @if($line->fabricColor)<div class="text-info fs-8">لون: {{ $line->fabricColor->color_name_ar }}</div>@endif
                                </td>
                                <td>{{ number_format($line->ordered_quantity, 2) }} {{ $line->purchaseUnit?->name_ar }}</td>
                                <td class="fw-bold">{{ number_format($line->unit_price, 2) }} SAR</td>
                                <td class="fw-bold text-dark">{{ number_format($line->line_total, 2) }} SAR</td>
                                <td><span class="badge bg-success fs-7">{{ number_format($line->received_base_quantity, 2) }} {{ $line->material?->unitOfMeasure?->name_ar }}</span></td>
                                <td>
                                    @if($line->remaining_base_quantity > 0)
                                    <span class="badge bg-warning text-dark fs-7">{{ number_format($line->remaining_base_quantity, 2) }} {{ $line->material?->unitOfMeasure?->name_ar }}</span>
                                    @else
                                    <span class="badge bg-secondary">مكتمل الاستلام</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Price Variance Reporting (Authorized Only) -->
            @can('costing.view')
            @if(!empty($varianceSummary['lines']))
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-chart-line text-warning me-2"></i>تقرير فروقات أسعار الشراء (Price Variance)
                    </h6>
                    <span class="badge {{ $varianceSummary['net_variance'] > 0 ? 'bg-danger' : 'bg-success' }} px-3 py-2">
                        صافي الفرق: {{ number_format($varianceSummary['net_variance'], 2) }} SAR
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>اسم الخامة</th>
                                <th>السعر المتفق عليه بـ PO</th>
                                <th>متوسط سعر الاستلام الفعلي</th>
                                <th>إجمالي التكلفة المتوقعة</th>
                                <th>إجمالي التكلفة الفعلية</th>
                                <th>فرق السعر</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($varianceSummary['lines'] as $var)
                            <tr>
                                <td class="fw-bold">{{ $var['material_name'] }}</td>
                                <td>{{ number_format($var['expected_base_unit_price'], 4) }} SAR</td>
                                <td class="fw-bold text-primary">{{ number_format($var['actual_average_base_unit_cost'], 4) }} SAR</td>
                                <td>{{ number_format($var['expected_total_cost'], 2) }} SAR</td>
                                <td class="fw-bold">{{ number_format($var['actual_total_cost'], 2) }} SAR</td>
                                <td class="fw-bold {{ $var['variance_amount'] > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $var['variance_amount'] > 0 ? '+' : '' }}{{ number_format($var['variance_amount'], 2) }} SAR
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @endcan

            <!-- Linked Stage 5 Receipts -->
            @if($purchaseOrder->materialReceipts->count() > 0)
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">سندات الاستلام المخزنية المرتبطة (Stage 5 Material Receipts)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>رقم سند الاستلام</th>
                                <th>تاريخ الاستلام</th>
                                <th>إجمالي التكلفة الفعلية</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($purchaseOrder->materialReceipts as $mr)
                            <tr>
                                <td>
                                    <a href="{{ route('inventory.receipts.show', $mr) }}" class="fw-bold text-primary">
                                        {{ $mr->receipt_number }}
                                    </a>
                                </td>
                                <td>{{ $mr->receipt_date->format('Y-m-d') }}</td>
                                <td class="fw-bold">{{ number_format($mr->total_amount, 2) }} SAR</td>
                                <td>
                                    @if($mr->isPosted())
                                    <span class="badge bg-success">معتمد ومرحل للمخزون واللوتات</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $mr->status }}</span>
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
                    <h6 class="fw-bold mb-0 text-dark">المالية والحالة التجارية</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">المجموع الفرعي:</span>
                        <span class="fw-bold text-dark">{{ number_format($purchaseOrder->subtotal, 2) }} SAR</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">الخصم:</span>
                        <span class="fw-bold text-danger">-{{ number_format($purchaseOrder->discount_amount, 2) }} SAR</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">أجور الشحن والنقل:</span>
                        <span class="fw-bold text-dark">+{{ number_format($purchaseOrder->shipping_amount, 2) }} SAR</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">الإجمالي المعتمد:</span>
                        <span class="fw-bold fs-5 text-primary">{{ number_format($purchaseOrder->total_amount, 2) }} SAR</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">حالة أمر الشراء:</small>
                        <span class="badge bg-primary fs-6 px-3 py-2 mt-1">{{ $purchaseOrder->status }}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">المستودع المستهدف:</small>
                        <span class="fw-bold text-dark">{{ $purchaseOrder->warehouse?->name_ar }}</span>
                    </div>

                    <!-- Close PO Action -->
                    @if(in_array($purchaseOrder->status, ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED']))
                        @can('purchasing.cancel_po')
                        <hr>
                        <button type="button" class="btn btn-outline-danger w-100 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#closePoModal">
                            <i class="fas fa-times-circle me-1"></i>إغلاق المتبقي من أمر الشراء
                        </button>

                        <div class="modal fade" id="closePoModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <form action="{{ route('purchasing.orders.close', $purchaseOrder) }}" method="POST" class="modal-content">
                                    @csrf
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title fs-6 fw-bold">إغلاق المتبقي من أمر الشراء</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">سبب الإغلاق وحسم المتبقي:</label>
                                            <textarea name="close_reason" class="form-control" rows="3" placeholder="المورد أكتفى بتوريد الشحنة الأولى ولا توجد متبقيات..." required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-danger btn-sm">تأكيد الإغلاق</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
