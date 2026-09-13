@extends('layouts.app')

@section('title', 'تفاصيل مرتجع العميل - ' . $return->return_number)

@section('content')
<div class="container-fluid py-3">
    <!-- Header Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h1 class="h3 font-weight-bold text-gray-800 mb-0">مرتجع عميل: {{ $return->return_number }}</h1>
                        @php
                            $statusBadges = [
                                'REPORTED' => 'bg-warning text-dark',
                                'RECEIVED_AT_FACTORY' => 'bg-info',
                                'LINKED_TO_QUALITY' => 'bg-danger',
                            ];
                            $statusLabels = [
                                'REPORTED' => 'بلاغ مسجل',
                                'RECEIVED_AT_FACTORY' => 'مستلم بالمصنع',
                                'LINKED_TO_QUALITY' => 'مرتبط بحادثة جودة',
                            ];
                        @endphp
                        <span class="badge {{ $statusBadges[$return->status] ?? 'bg-secondary' }} px-3 py-2 fs-6">
                            {{ $statusLabels[$return->status] ?? $return->status }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        تاريخ الإبلاغ: {{ $return->reported_at ? $return->reported_at->format('Y-m-d H:i') : '' }} | 
                        المبلغ عنه: {{ $return->reportedByUser->name ?? 'النظام' }}
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if($return->status === 'REPORTED')
                        @can('delivery.manage_returns')
                        <button type="button" class="btn btn-info text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#receiveModal">
                            <i class="fas fa-warehouse me-1"></i>استلام المرتجع وإيداعه بالمخزون
                        </button>
                        @endcan
                    @endif

                    @if(! $return->quality_incident_id)
                        @can('delivery.manage_returns')
                        <a href="{{ route('quality-incidents.create', ['production_order_id' => $return->production_order_id, 'customer_return_id' => $return->id]) }}" class="btn btn-outline-danger shadow-sm">
                            <i class="fas fa-exclamation-triangle me-1"></i>إنشاء ورابط حادثة جودة جديد
                        </a>
                        @endcan
                    @endif

                    <a href="{{ route('customer-returns.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-1"></i>العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Order & Return Details -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-info-circle text-danger me-2"></i>تفاصيل بلاغ الإرجاع
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small">الكمية المرتجعة:</div>
                            <div class="fw-bold fs-5 text-danger">{{ (float)$return->quantity_returned }} قطعة</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">سبب الإرجاع:</div>
                            @php
                                $reasons = [
                                    'DEFECTIVE' => 'عيب تصنيعي / جودة',
                                    'WRONG_SPECIFICATION' => 'مواصفة غير مطابقة',
                                    'TRANSPORT_DAMAGE' => 'تلف في النقل والتركيب',
                                    'CUSTOMER_CHANGE' => 'رغبة العميل',
                                ];
                            @endphp
                            <div class="fw-bold text-dark">{{ $reasons[$return->reason_code] ?? $return->reason_code }}</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">الوصف التفصيلي:</div>
                        <div class="bg-light p-3 rounded text-dark mt-1">
                            {{ $return->description ?? 'لا يوجد وصف مدون.' }}
                        </div>
                    </div>

                    @if($return->condition_code)
                    <div class="mb-3">
                        <div class="text-muted small">حالة المرتجع عند الفحص بالمخزن:</div>
                        <span class="badge bg-secondary px-3 py-1 fs-6 mt-1">{{ $return->condition_code }}</span>
                    </div>
                    @endif

                    @if($return->receivedByUser)
                    <div class="text-muted small">
                        استلام بالمخزن بواسطة: <strong class="text-dark">{{ $return->receivedByUser->name }}</strong> 
                        في ({{ $return->received_at ? $return->received_at->format('Y-m-d H:i') : '' }})
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Side: Linked Context (Production, Delivery, Quality) -->
        <div class="col-lg-6">
            <!-- Production Order Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-industry text-primary me-2"></i>أمر الإنتاج وطلب المبيعات المرتبط
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">أمر الإنتاج:</div>
                        @if($return->productionOrder)
                            <a href="{{ route('production.orders.show', $return->productionOrder) }}" class="fw-bold text-primary text-decoration-none fs-6">
                                {{ $return->productionOrder->production_order_number }}
                            </a>
                            <div class="small text-muted">
                                المنتج: {{ $return->productionOrder->customerOrderLine->productModel->name_ar ?? 'منتج أثاث' }}
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">طلب المبيعات والعميل:</div>
                        @if($return->customerOrder)
                            <a href="{{ route('sales.orders.show', $return->customerOrder) }}" class="fw-bold text-dark text-decoration-none">
                                {{ $return->customerOrder->order_number }}
                            </a>
                            <div class="small text-muted">العميل: {{ $return->customerOrder->customer->name ?? $return->customerOrder->customer->name_ar ?? '-' }}</div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quality Incident Linkage Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-shield-alt text-warning me-2"></i>ربط الجودة وضبط جودة التصنيع (Stage 10)
                    </h6>
                </div>
                <div class="card-body">
                    @if($return->qualityIncident)
                        <div class="d-flex align-items-center justify-content-between bg-soft-danger p-3 rounded-3">
                            <div>
                                <div class="fw-bold text-danger">مرتبط بحادثة الجودة رقم: {{ $return->qualityIncident->incident_number }}</div>
                                <div class="small text-muted">القسم المسؤول: {{ $return->qualityIncident->responsibleDepartment->name_ar ?? 'غير محدد' }}</div>
                            </div>
                            <a href="{{ route('quality-incidents.show', $return->qualityIncident) }}" class="btn btn-sm btn-outline-danger">
                                عرض الحادثة
                            </a>
                        </div>
                    @else
                        <p class="text-muted small mb-0">لم يتم ربط هذا المرتجع بعد بحادثة عدم مطابقة / جودة تصنيعية.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receive Modal -->
@can('delivery.manage_returns')
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('customer-returns.receive', $return) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fs-6 fw-bold">استلام المنتجات المرتجعة في مخزن المنتجات الجاهزة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">تقييم حالة الأثاث عند الاستلام بالمصنع:</label>
                    <select name="condition_code" class="form-select">
                        <option value="GOOD">سليم وسالح للبيع المباشر (Good)</option>
                        <option value="NEEDS_INSPECTION">يحتاج فحص فني وإعادة تجهيز (Needs Inspection)</option>
                        <option value="DAMAGED">تالف يحتاج إعادة تصنيع (Damaged)</option>
                        <option value="SCRAP_CANDIDATE">خردة / سكراب (Scrap)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-info text-white btn-sm">تأكيد الاستلام والتسليم للمخزن</button>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection
