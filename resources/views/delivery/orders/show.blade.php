@extends('layouts.app')

@section('title', 'تفاصيل أمر التوصيل - ' . $delivery->delivery_number)

@section('content')
<div class="container-fluid py-3">
    <!-- Header / Actions Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h1 class="h3 font-weight-bold text-gray-800 mb-0">أمر توصيل: {{ $delivery->delivery_number }}</h1>
                        @php
                            $badgeClasses = [
                                'PENDING' => 'bg-secondary',
                                'ASSIGNED' => 'bg-info',
                                'READY_FOR_DELIVERY' => 'bg-primary',
                                'OUT_FOR_DELIVERY' => 'bg-warning text-dark',
                                'DELIVERED' => 'bg-success',
                                'INSTALLED' => 'bg-teal text-white',
                                'FAILED' => 'bg-danger',
                                'RESCHEDULED' => 'bg-dark',
                                'CANCELLED' => 'bg-secondary',
                            ];
                            $statusLabels = [
                                'PENDING' => 'مسودة',
                                'ASSIGNED' => 'تم تعيين السائق',
                                'READY_FOR_DELIVERY' => 'جاهز للشحن',
                                'OUT_FOR_DELIVERY' => 'خرج للتوصيل',
                                'DELIVERED' => 'تم التسليم',
                                'INSTALLED' => 'تم التركيب والتشغيل',
                                'FAILED' => 'متعذر التسليم',
                                'RESCHEDULED' => 'معاد جدولته',
                                'CANCELLED' => 'ملغى',
                            ];
                        @endphp
                        <span class="badge {{ $badgeClasses[$delivery->status] ?? 'bg-secondary' }} px-3 py-2 fs-6">
                            {{ $statusLabels[$delivery->status] ?? $delivery->status }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        طلب المبيعات المرتبط: 
                        @if($delivery->customerOrder)
                            <a href="{{ route('sales.orders.show', $delivery->customerOrder) }}" class="fw-bold text-primary text-decoration-none">
                                {{ $delivery->customerOrder->order_number }}
                            </a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                        | تاريخ الإنشاء: {{ $delivery->created_at->format('Y-m-d H:i') }}
                    </p>
                </div>

                <!-- Workflow Action Buttons -->
                <div class="d-flex flex-wrap gap-2">
                    @can('delivery.assign')
                    <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
                        <i class="fas fa-user-check me-1"></i>
                        {{ $delivery->assignedUser ? 'تغيير السائق/الفني' : 'تعيين السائق/الفني' }}
                    </button>
                    @endcan

                    @if($delivery->status === 'READY_FOR_DELIVERY' || $delivery->status === 'ASSIGNED')
                        @can('delivery.dispatch')
                        <form action="{{ route('delivery.orders.dispatch', $delivery) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark fw-bold shadow-sm" onclick="return confirm('تأكيد خروج الشحنة للتوصيل؟ سيتم اقتطاع وحجز الأثاث من المخزن الجاهز.')">
                                <i class="fas fa-shipping-fast me-1"></i>تأكيد الخروج للتوصيل (Dispatch)
                            </button>
                        </form>
                        @endcan
                    @endif

                    @if($delivery->status === 'OUT_FOR_DELIVERY')
                        @can('delivery.complete')
                        <form action="{{ route('delivery.orders.complete', $delivery) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success fw-bold shadow-sm" onclick="return confirm('تأكيد تسليم الشحنة للعميل بنجاح؟')">
                                <i class="fas fa-check-circle me-1"></i>إكمال التسليم للعميل
                            </button>
                        </form>
                        @endcan

                        @can('delivery.reschedule')
                        <button type="button" class="btn btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#failModal">
                            <i class="fas fa-exclamation-triangle me-1"></i>تعذر التسليم / تأجيل
                        </button>
                        @endcan
                    @endif

                    @if($delivery->status === 'DELIVERED')
                        @can('delivery.install')
                        <form action="{{ route('delivery.orders.install', $delivery) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-teal text-white fw-bold shadow-sm" style="background-color: #0d9488;" onclick="return confirm('تأكيد إكمال أعمال التركيب والتشغيل لدى موقع العميل؟')">
                                <i class="fas fa-tools me-1"></i>إكمال التركيب والتشغيل
                            </button>
                        </form>
                        @endcan
                    @endif

                    <a href="{{ route('delivery.orders.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-1"></i>العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Customer Snapshot & Details -->
        <div class="col-lg-4">
            <!-- Customer Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-user-pin text-primary me-2"></i>بيانات موقع التسليم والعميل
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">اسم المستلم:</div>
                        <div class="fw-bold fs-6 text-dark">{{ $delivery->customer_name_snapshot }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">رقم التواصل:</div>
                        <div class="fw-bold text-primary" dir="ltr">
                            <a href="tel:{{ $delivery->customer_phone_snapshot }}" class="text-decoration-none">
                                <i class="fas fa-phone me-1"></i>{{ $delivery->customer_phone_snapshot }}
                            </a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">المدينة والحي:</div>
                        <div class="fw-semibold text-dark">
                            {{ $delivery->city_snapshot ?? 'غير محدد' }} - {{ $delivery->district_snapshot ?? '-' }}
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">العنوان التفصيلي:</div>
                        <div class="bg-light p-2 rounded text-dark small">
                            {{ $delivery->delivery_address_snapshot ?? 'لا يوجد عنوان تفصيلي مدون' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Assignment & Schedule Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-id-card text-primary me-2"></i>الفني / السائق والتوقيت
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">المسؤول عن التوصيل/التركيب:</div>
                        @if($delivery->assignedUser)
                            <div class="fw-bold text-dark d-flex align-items-center gap-2 mt-1">
                                <i class="fas fa-user-circle text-primary fs-5"></i>
                                <span>{{ $delivery->assignedUser->name }}</span>
                            </div>
                        @else
                            <div class="badge bg-warning text-dark border mt-1">لم يتم تعيين سائق بعد</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">التاريخ المجدول:</div>
                        <div class="fw-bold text-dark mt-1">
                            <i class="far fa-calendar-alt text-muted me-1"></i>
                            {{ $delivery->scheduled_delivery_date ? $delivery->scheduled_delivery_date->format('Y-m-d') : 'غير محدد' }}
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">مسؤول التنسيق والشحن:</div>
                        <div class="small text-muted mt-1">
                            إنشاء: {{ $delivery->createdByUser->name ?? 'النظام' }}
                            @if($delivery->dispatchedByUser)
                                <br>خروج: {{ $delivery->dispatchedByUser->name }} ({{ $delivery->dispatched_at ? $delivery->dispatched_at->format('Y-m-d H:i') : '' }})
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Items & History -->
        <div class="col-lg-8">
            <!-- Shipped Goods Lines -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-couch text-primary me-2"></i>قطع الأثاث المشمولة في أمر التوصيل
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th class="ps-4">الموديل / الأثاث</th>
                                    <th>أمر الإنتاج المرتبط</th>
                                    <th>الكمية المشحونة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($delivery->lines as $line)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">
                                            {{ $line->productionOrder->customerOrderLine->productModel->name_ar ?? 'منتج أثاث' }}
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('production.orders.show', $line->productionOrder) }}" class="badge bg-light text-primary border text-decoration-none">
                                            {{ $line->productionOrder->production_order_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary fs-6 px-3 py-1">{{ (float)$line->quantity }} قطعة</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Event Timeline Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-history text-primary me-2"></i>سجل الأحداث والتتبع الزمني (Timeline Log)
                    </h6>
                </div>
                <div class="card-body">
                    @forelse($delivery->events as $event)
                    <div class="d-flex align-items-start gap-3 pb-3 mb-3 border-bottom border-light">
                        <div class="badge bg-soft-primary text-primary rounded-circle p-2">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">{{ $event->event_type }}</span>
                                <span class="text-muted small">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                            <div class="small text-secondary mt-1">بواسطة: {{ $event->user->name ?? 'النظام' }}</div>
                            @if($event->notes)
                                <div class="bg-light p-2 rounded small mt-1 text-dark">{{ $event->notes }}</div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3 mb-0">لا توجد أحداث مسجلة بعد في السجل الزمني.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Driver Modal -->
@can('delivery.assign')
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('delivery.orders.assign', $delivery) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fs-6 fw-bold">تعيين / تغيير السائق أو الفني</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">اختر السائق / الفني:</label>
                    <select name="assigned_user_id" class="form-select" required>
                        <option value="">-- اختر الفني --</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" {{ $delivery->assigned_user_id == $driver->id ? 'selected' : '' }}>
                                {{ $driver->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary btn-sm">حفظ التعيين</button>
            </div>
        </form>
    </div>
</div>
@endcan

<!-- Fail / Reschedule Modal -->
@can('delivery.reschedule')
<div class="modal fade" id="failModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('delivery.orders.fail-or-reschedule', $delivery) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fs-6 fw-bold">تسجيل تعذر التسليم أو إعادة الجدولة</h5>
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
                    <label class="form-label small fw-bold">سبب الإخفاق / التأجيل التفصيلي:</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">تاريخ التوصيل الجديد (في حالة إعادة الجدولة):</label>
                    <input type="date" name="scheduled_delivery_date" class="form-control">
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="returned_to_factory" value="1" id="returnSwitchShow" checked>
                    <label class="form-check-label small" for="returnSwitchShow">إعادة المنتجات لمخزن المنتجات الجاهزة بالمصنع</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger btn-sm">تأكيد ورصد الأحداث</button>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection
