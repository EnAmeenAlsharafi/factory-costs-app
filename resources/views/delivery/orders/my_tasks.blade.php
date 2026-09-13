@extends('layouts.app')

@section('title', 'مهامي اليومية - التوصيل والتركيب')

@section('content')
<div class="container py-2" style="max-width: 500px;">
    <!-- Driver Mobile Header -->
    <div class="card border-0 bg-primary text-white shadow-sm rounded-4 mb-3">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>مهام التوصيل والتركيب
                    </h5>
                    <div class="small opacity-75 mt-1">مرحباً {{ auth()->user()->name }} (السائق/الفني)</div>
                </div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold">
                    {{ $myDeliveries->count() }} مهام
                </span>
            </div>
        </div>
    </div>

    <!-- Task Cards -->
    @forelse($myDeliveries as $delivery)
    <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center py-2 px-3">
            <span class="fw-bold text-dark">{{ $delivery->delivery_number }}</span>
            @php
                $statusBadges = [
                    'ASSIGNED' => ['bg' => 'bg-info', 'label' => 'معين لك'],
                    'READY_FOR_DELIVERY' => ['bg' => 'bg-primary', 'label' => 'جاهز للشحن'],
                    'OUT_FOR_DELIVERY' => ['bg' => 'bg-warning text-dark', 'label' => 'في الطريق للعميل'],
                    'DELIVERED' => ['bg' => 'bg-success', 'label' => 'تم التسليم'],
                ];
                $status = $statusBadges[$delivery->status] ?? ['bg' => 'bg-secondary', 'label' => $delivery->status];
            @endphp
            <span class="badge {{ $status['bg'] }} px-2 py-1">{{ $status['label'] }}</span>
        </div>
        <div class="card-body p-3">
            <!-- Customer Info -->
            <div class="mb-3">
                <div class="fw-bold fs-6 text-dark mb-1">{{ $delivery->customer_name_snapshot }}</div>
                <div class="text-muted small mb-2">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                    {{ $delivery->city_snapshot }} - {{ $delivery->district_snapshot }}
                    <br>
                    <span class="text-secondary">{{ $delivery->delivery_address_snapshot }}</span>
                </div>
                @if($delivery->customer_phone_snapshot)
                <a href="tel:{{ $delivery->customer_phone_snapshot }}" class="btn btn-outline-success btn-sm w-100 rounded-pill py-2 text-decoration-none">
                    <i class="fas fa-phone-alt me-2"></i>الاتصال بالعميل ({{ $delivery->customer_phone_snapshot }})
                </a>
                @endif
            </div>

            <!-- Items summary -->
            <div class="bg-light p-2 rounded-3 mb-3">
                <div class="small fw-bold text-secondary mb-1">قطع الأثاث المطلوبة:</div>
                <ul class="list-unstyled mb-0 small">
                    @foreach($delivery->lines as $line)
                    <li class="d-flex justify-content-between py-1 border-bottom border-light">
                        <span>{{ $line->productionOrder->customerOrderLine->productModel->name_ar ?? 'منتج أثاث' }}</span>
                        <span class="badge bg-secondary rounded-pill">{{ (float)$line->quantity }} قطعة</span>
                    </li>
                    @endforeach
                </ul>
            </div>

            <!-- Actions based on status -->
            <div class="d-grid gap-2">
                @if($delivery->status === 'READY_FOR_DELIVERY' || $delivery->status === 'ASSIGNED')
                    @can('delivery.dispatch')
                    <form action="{{ route('delivery.orders.dispatch', $delivery) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-shipping-fast me-1"></i>انطلاق وتأكيد خروج الشحنة
                        </button>
                    </form>
                    @endcan
                @elseif($delivery->status === 'OUT_FOR_DELIVERY')
                    @can('delivery.complete')
                    <form action="{{ route('delivery.orders.complete', $delivery) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success w-100 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>تم تسليم المنتجات للعميل
                        </button>
                    </form>
                    @endcan
                    @can('delivery.reschedule')
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#failModal-{{ $delivery->id }}">
                        <i class="fas fa-exclamation-triangle me-1"></i>تعذر التسليم / إعادة جدولة
                    </button>
                    @endcan
                @elseif($delivery->status === 'DELIVERED')
                    @can('delivery.install')
                    <form action="{{ route('delivery.orders.install', $delivery) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-teal text-white w-100 py-2 rounded-pill fw-bold shadow-sm" style="background-color: #0d9488;">
                            <i class="fas fa-tools me-1"></i>إكمال التركيب والتشغيل النهائي
                        </button>
                    </form>
                    @endcan
                @endif

                <a href="{{ route('delivery.orders.show', $delivery) }}" class="btn btn-link btn-sm text-decoration-none text-muted">
                    عرض تفاصيل الأمر الكاملة <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Fail / Reschedule Modal -->
    @can('delivery.reschedule')
    <div class="modal fade" id="failModal-{{ $delivery->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('delivery.orders.fail-or-reschedule', $delivery) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold">تسجيل إخفاق التوصيل / إعادة الجدولة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">الحدث المطلوب:</label>
                        <select name="new_status" class="form-select" required>
                            <option value="RESCHEDULED">إعادة جدولة لموعد آخر</option>
                            <option value="FAILED">متعذر التسليم (فشل)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">سبب الإخفاق / التأجيل:</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="العميل غير متواجد، الموقع غير دقيق..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">تاريخ التوصيل الجديد (اختياري):</label>
                        <input type="date" name="scheduled_delivery_date" class="form-control">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="returned_to_factory" value="1" id="returnSwitch-{{ $delivery->id }}" checked>
                        <label class="form-check-label small" for="returnSwitch-{{ $delivery->id }}">إعادة المنتجات إلى مخزن المنتجات الجاهزة</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger btn-sm">حفظ وإرسال التقرير</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
    @empty
    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
        <div class="card-body">
            <i class="fas fa-check-double text-success display-3 mb-3"></i>
            <h6 class="fw-bold">لا توجد مهام توصيل قيد التنفيذ حالياً</h6>
            <p class="text-muted small">جميع الطلبات المسندة إليك مكتملة أو لا توجد شحنات جديدة.</p>
        </div>
    </div>
    @endforelse
</div>
@endsection
