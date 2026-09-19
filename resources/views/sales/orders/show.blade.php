@extends('layouts.app')

@section('title', 'طلب عميل ' . $order->order_number)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 mb-1 text-dark fw-bold">طلب عميل: {{ $order->order_number }}</h1>
                @if($order->status === 'DRAFT')
                    <span class="badge bg-secondary">مسودة</span>
                @elseif($order->status === 'PENDING_PRODUCTION_REVIEW')
                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> بانتظار مراجعة الإنتاج</span>
                @elseif($order->status === 'APPROVED_FOR_PRODUCTION')
                    <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> معتمد للإنتاج</span>
                @elseif($order->status === 'CANCELLED')
                    <span class="badge bg-danger"><i class="fas fa-ban me-1"></i> ملغى</span>
                @endif
            </div>
            <p class="text-muted mb-0 fs-7">تم الإنشاء بتاريخ {{ $order->created_at->format('Y-m-d H:i') }} بواسطة {{ $order->creator?->name }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> طلبات العملاء
            </a>

            @if($order->status === 'DRAFT')
                @can('orders.submit_review')
                    <form action="{{ route('sales.orders.submit-review', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning fw-semibold text-dark" onclick="return confirm('هل أنت تأكد من إرسال الطلب لمراجعة مدير الإنتاج؟')">
                            <i class="fas fa-paper-plane me-1"></i> إرسال لمراجعة الإنتاج
                        </button>
                    </form>
                @endcan
            @endif

            @if($order->status === 'PENDING_PRODUCTION_REVIEW')
                @can('orders.review_production')
                    <a href="{{ route('sales.orders.review', $order) }}" class="btn btn-warning fw-bold text-dark">
                        <i class="fas fa-clipboard-check me-1"></i> المراجعة الفنية والاعتماد للإنتاج
                    </a>
                @endcan
            @endif

            @if($order->status === 'APPROVED_FOR_PRODUCTION' && auth()->user()->can('delivery.create'))
                <a href="{{ route('delivery.orders.create', ['customer_order_id' => $order->id]) }}" class="btn btn-primary fw-bold">
                    <i class="fas fa-truck-loading me-1"></i> إنشاء أمر توصيل وتركيب
                </a>
            @endif

            @if(in_array($order->status, ['DRAFT', 'PENDING_PRODUCTION_REVIEW', 'APPROVED_FOR_PRODUCTION']))
                @can('orders.edit')
                    <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> تعديل الطلب
                    </a>
                @endcan
            @endif

            @if($order->status !== 'CANCELLED')
                @can('orders.cancel')
                    <form action="{{ route('sales.orders.cancel', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger fw-semibold" onclick="return confirm('هل أنت تأكد من إلغاء هذا الطلب؟')">
                            <i class="fas fa-ban me-1"></i> إلغاء الطلب
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Production Approval Notice --}}
    @if($order->status === 'APPROVED_FOR_PRODUCTION')
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-1"><i class="fas fa-check-circle me-2"></i> هذا الطلب معتمد رسمياً للإنتاج</h6>
                    <span class="fs-7">تم الاعتماد بواسطة: <strong>{{ $order->productionApprover?->name }}</strong> بتاريخ {{ $order->production_approved_at ? $order->production_approved_at->format('Y-m-d H:i') : '' }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">العميل وأمر الشراء</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $order->customer?->name }}</h6>
                    <small class="text-muted">PO: {{ $order->customer_po_number ?? 'غير محدد' }} | {{ $order->customer?->phone }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">قناة البيع</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $order->salesChannel?->name_ar }}</h6>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">تاريخ الطلب / التسليم</span>
                    <h6 class="fw-bold text-dark mb-0">{{ $order->order_date ? $order->order_date->format('Y-m-d') : '-' }}</h6>
                    <small class="text-muted">التسليم: {{ $order->promised_delivery_date ? $order->promised_delivery_date->format('Y-m-d') : 'غير محدد' }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-warning bg-opacity-10 border-start border-4 border-warning">
                <div class="card-body">
                    <span class="text-muted fs-7 d-block mb-1">إجمالي مبلغ الطلب</span>
                    <h4 class="fw-bold text-dark mb-0">{{ number_format($order->total_amount, 2) }} <small class="fs-6">ر.س</small></h4>
                </div>
            </div>
        </div>
    </div>

    @if($order->notes)
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-body p-3">
                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-sticky-note text-warning me-1"></i> ملاحظات ووصايا الطلب:</h6>
                <p class="text-muted mb-0 fs-7">{{ $order->notes }}</p>
            </div>
        </div>
    @endif

    {{-- Receivables & Payment Control Card (Stage 13) --}}
    @php
        $eligibilityService = app(\App\Services\OrderPaymentEligibilityService::class);
        $prodEligibility = $eligibilityService->checkProductionEligibility($order);
        $delEligibility = $eligibilityService->checkDeliveryEligibility($order);
    @endphp
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold fs-6 text-dark">
                <i class="fas fa-money-bill-wave text-success me-2"></i> الدفعات والتحصيل ومتابعة المستحقات (Payment & Receivables Status)
            </h5>
            <div class="d-flex gap-2">
                @can('receivables.payment.create')
                    <a href="{{ route('receivables.payments.create', ['order_id' => $order->id]) }}" class="btn btn-sm btn-warning fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> تسجيل دفعة للطلب
                    </a>
                @endcan
                @can('receivables.override_payment_control')
                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold" data-bs-toggle="modal" data-bs-target="#overrideModal">
                        <i class="fas fa-shield-alt me-1"></i> اعتماد استثناء سداد/ائتمان
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-3">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block fw-bold">شروط السداد المعتمدة:</small>
                        <span class="badge bg-secondary px-2 py-1 mt-1">
                            @switch($order->payment_terms_type)
                                @case('FULL_BEFORE_PRODUCTION') سداد كامل قبل الإنتاج @break
                                @case('DEPOSIT_AND_BALANCE') عربون + المتبقي عند التسليم @break
                                @case('CASH_ON_DELIVERY') الدفع عند الاستلام (COD) @break
                                @case('CREDIT') بيع آجل (Credit Order) @break
                                @default {{ $order->payment_terms_type ?? 'سداد قبل الإنتاج' }}
                            @endswitch
                        </span>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block fw-bold">المسدد المؤكد / المطلوب:</small>
                        <div class="fw-bold text-success mt-1">{{ number_format($order->confirmed_paid_amount, 2) }} / {{ number_format($order->total_amount, 2) }} ر.س</div>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block fw-bold">المتبقي القائم (Outstanding):</small>
                        <div class="fw-bold text-danger mt-1">{{ number_format($order->outstanding_balance, 2) }} ر.س</div>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block fw-bold">حالة السداد التشغيلية:</small>
                        <span class="badge {{ $order->payment_status === 'PAID' ? 'bg-success' : ($order->payment_status === 'OVERDUE' ? 'bg-danger' : 'bg-warning text-dark') }} px-2 py-1 mt-1">
                            @switch($order->payment_status)
                                @case('PAID') مدفوع بالكامل @break
                                @case('DEPOSIT_PENDING') ينتظر العربون @break
                                @case('PARTIALLY_PAID') مدفوع جزئياً @break
                                @case('OVERDUE') متأخر عن الاستحقاق @break
                                @case('CREDIT') آجل ضمن الحد @break
                                @default غير مدفوع
                            @endswitch
                        </span>
                    </div>
                </div>
            </div>

            {{-- Eligibility Badges --}}
            <div class="row g-2 mb-3">
                <div class="col-12 col-md-6">
                    <div class="p-2 rounded border {{ $prodEligibility['eligible'] ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' }}">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold small text-dark"><i class="fas fa-industry me-1"></i> حالة السداد للإنتاج:</span>
                            <span class="badge {{ $prodEligibility['eligible'] ? 'bg-success' : 'bg-danger' }}">
                                {{ $prodEligibility['eligible'] ? 'مسموح للإنتاج' : 'غير مسموح' }}
                            </span>
                        </div>
                        <div class="small text-muted mt-1">{{ $prodEligibility['reason'] }}</div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="p-2 rounded border {{ $delEligibility['eligible'] ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' }}">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold small text-dark"><i class="fas fa-truck me-1"></i> حالة السداد للتسليم:</span>
                            <span class="badge {{ $delEligibility['eligible'] ? 'bg-success' : 'bg-danger' }}">
                                {{ $delEligibility['eligible'] ? 'مسموح بالتسليم' : 'غير مسموح' }}
                            </span>
                        </div>
                        <div class="small text-muted mt-1">{{ $delEligibility['reason'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Linked Allocations Table --}}
            @if ($order->allocations->isNotEmpty())
                <h6 class="fw-bold text-dark mb-2 small"><i class="fas fa-link me-1"></i> الدفعات المخصصة لهذا الطلب:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 fs-8">
                        <thead class="bg-light">
                            <tr>
                                <th>رقم الدفعة</th>
                                <th>تاريخ الدفعة</th>
                                <th>طريقة الدفع</th>
                                <th>نوع التخصيص</th>
                                <th class="text-end">المبلغ المخصص</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->allocations as $alloc)
                                <tr>
                                    <td class="font-monospace fw-bold">
                                        <a href="{{ route('receivables.payments.show', $alloc->payment) }}" class="text-decoration-none">
                                            {{ $alloc->payment->payment_number ?? '-' }}
                                        </a>
                                    </td>
                                    <td>{{ $alloc->payment?->payment_date?->format('Y-m-d') }}</td>
                                    <td>{{ $alloc->payment?->payment_method }}</td>
                                    <td>{{ $alloc->allocation_type }}</td>
                                    <td class="text-end fw-bold text-success">{{ number_format($alloc->allocated_amount, 2) }} ر.س</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Override Modal --}}
    @can('receivables.override_payment_control')
        <div class="modal fade" id="overrideModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('receivables.overrides.store') }}">
                        @csrf
                        <input type="hidden" name="customer_order_id" value="{{ $order->id }}">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt me-2"></i> اعتماد استثناء سداد / ائتمان</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">مرحلة الاستثناء الإداري <span class="text-danger">*</span></label>
                                <select name="override_stage" class="form-select" required>
                                    <option value="PRODUCTION_RELEASE">السماح بإطلاق الإنتاج بالرغم من نقص السداد/تجاوز الائتمان</option>
                                    <option value="DELIVERY_DISPATCH">السماح بتسليم المنتج قبل تصفية المتبقي</option>
                                    <option value="CREDIT_LIMIT">تجاوز السقف الائتماني للعميل</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">مبرر الاستثناء المعتمد <span class="text-danger">*</span></label>
                                <textarea name="reason" rows="3" class="form-control" placeholder="اكتب المبرر والمسؤول صاحب التوجيه..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-danger fw-bold">اعتماد وتأكيد الاستثناء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    {{-- Order Lines Table --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-header bg-light py-3">
            <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-list text-primary me-2"></i> بنود الطلب والمواصفات الفنية ({{ $order->lines->count() }})</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#</th>
                            <th>الموديل / التصميم</th>
                            <th>المقاس المطلوب من العميل</th>
                            <th>المقاس المرجعي للإنتاج</th>
                            <th>وصفة التصنيع المعتمدة (BOM)</th>
                            <th class="text-center">الكمية</th>
                            <th class="text-end">سعر الوحدة</th>
                            <th class="text-end pe-3">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->lines as $index => $line)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $index + 1 }}</td>
                                <td>
                                    @if($line->custom_design)
                                        <span class="badge bg-purple text-white mb-1" style="background-color: #6f42c1;">تصميم خاص</span>
                                        <div class="fw-bold text-dark">{{ $line->custom_design_name ?? 'تصميم خاص بدون اسم' }}</div>
                                    @else
                                        <div class="fw-bold text-dark">{{ $line->productModel?->name_ar ?? 'موديل غير محدد' }}</div>
                                        <small class="text-muted">كود: {{ $line->productModel?->model_code }}</small>
                                    @endif

                                    @if($line->fabricMaterial || $line->fabricSupplier || $line->fabric_color_code)
                                        <div class="mt-2 p-2 bg-light rounded border border-warning-subtle fs-8">
                                            <strong class="text-dark d-block mb-1"><i class="fas fa-palette text-warning me-1"></i> مواصفات القماش:</strong>
                                            <div class="d-flex flex-wrap gap-2 text-dark">
                                                <span><strong>المورد:</strong> {{ $line->fabricSupplier?->name ?? 'غير محدد' }}</span>
                                                <span>•</span>
                                                <span><strong>نوع القماش:</strong> {{ $line->fabricMaterial?->name_ar ?? 'غير محدد' }}</span>
                                                <span>•</span>
                                                <span><strong>رقم/كود اللون:</strong> <code class="text-dark bg-white px-1 border rounded fw-bold">{{ $line->fabric_color_code ?? 'غير محدد' }}</code></span>
                                            </div>
                                        </div>
                                    @endif

                                    @if($line->notes)
                                        <div class="fs-8 text-muted mt-1"><i class="fas fa-info-circle me-1"></i> {{ $line->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($line->requested_width_cm && $line->requested_length_cm)
                                        <div class="fw-semibold text-dark">
                                            {{ $line->requested_width_cm }} × {{ $line->requested_length_cm }} سم
                                        </div>
                                    @else
                                        <span class="text-muted">حسب التكوين</span>
                                    @endif
                                </td>
                                <td>
                                    @if($line->reference_width_cm && $line->reference_length_cm)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            {{ $line->reference_width_cm }} × {{ $line->reference_length_cm }} سم
                                        </span>
                                    @else
                                        <span class="text-muted fs-8">لم تحدد في المراجعة</span>
                                    @endif
                                </td>
                                <td>
                                    @if($line->recipeVersion)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fas fa-scroll me-1"></i> {{ $line->recipeVersion->recipe?->name }} (V{{ $line->recipeVersion->version_number }})
                                        </span>
                                    @else
                                        <span class="text-muted fs-8">غير ربط</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-dark fs-6">
                                    {{ $line->quantity }}
                                    @php
                                        $alreadyReleased = $line->productionOrders ? $line->productionOrders->where('status', '!=', 'CANCELLED')->sum('released_quantity') : 0;
                                    @endphp
                                    @if($alreadyReleased > 0)
                                        <small class="d-block text-success fw-normal fs-8">مُطلق للإنتاج: {{ $alreadyReleased }}</small>
                                    @endif
                                </td>
                                <td class="text-end font-monospace text-dark">{{ number_format($line->unit_price, 2) }} ر.س</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-primary fs-6">
                                    <div>{{ number_format($line->total_price, 2) }} ر.س</div>
                                    @if($order->status === 'APPROVED_FOR_PRODUCTION' && auth()->user()->can('production.release'))
                                        @if($line->quantity - $alreadyReleased > 0)
                                            <a href="{{ route('production.orders.create', ['line_id' => $line->id]) }}" class="btn btn-sm btn-success mt-1">
                                                <i class="fas fa-industry me-1"></i> أمر إنتاج
                                            </a>
                                        @else
                                            <span class="badge bg-light text-success border mt-1"><i class="fas fa-check me-1"></i>مكتمل الإطلاق</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="7" class="text-end fw-bold fs-6">الإجمالي الكلي:</td>
                            <td class="text-end pe-3 fw-bold text-primary fs-5 font-monospace">
                                {{ number_format($order->total_amount, 2) }} ر.س
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Delivery Orders Section --}}
    @php
        $deliveries = \App\Models\DeliveryOrder::with(['assignedUser', 'lines.productionOrder'])->where('customer_order_id', $order->id)->latest()->get();
    @endphp
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold fs-6 text-dark">
                <i class="fas fa-truck text-primary me-2"></i>أوامر التوصيل والتركيب الميداني ({{ $deliveries->count() }})
            </h5>
            @if($order->status === 'APPROVED_FOR_PRODUCTION' && auth()->user()->can('delivery.create'))
                <a href="{{ route('delivery.orders.create', ['customer_order_id' => $order->id]) }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>أمر توصيل جديد
                </a>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-7">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">رقم أمر التوصيل</th>
                            <th>المسؤول / السائق</th>
                            <th>العنوان المسجل</th>
                            <th>تاريخ التوصيل المجدول</th>
                            <th>الحالة</th>
                            <th class="text-end pe-3">عرض</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveries as $delivery)
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="{{ route('delivery.orders.show', $delivery) }}" class="text-primary text-decoration-none">
                                        {{ $delivery->delivery_number }}
                                    </a>
                                </td>
                                <td>{{ $delivery->assignedUser->name ?? 'غير معين' }}</td>
                                <td>{{ $delivery->city_snapshot }} - {{ $delivery->district_snapshot }}</td>
                                <td>{{ $delivery->scheduled_delivery_date ? $delivery->scheduled_delivery_date->format('Y-m-d') : 'غير محدد' }}</td>
                                <td>
                                    <span class="badge bg-primary px-2 py-1">{{ $delivery->status }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('delivery.orders.show', $delivery) }}" class="btn btn-sm btn-light rounded-circle shadow-sm">
                                        <i class="fas fa-eye text-primary"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    لا توجد أوامر توصيل صادرة لطلب المبيعات هذا بعد.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Order Change Audit History Card --}}
    @if($order->changes->isNotEmpty())
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-light py-3">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-history text-warning me-2"></i> سجل التعديلات والتغييرات ({{ $order->changes->count() }})</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-7">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">التاريخ والوقت</th>
                                <th>المستخدم</th>
                                <th>نوع التغيير</th>
                                <th>ملاحظات التغيير</th>
                                <th>تغيير بعد اعتماد الإنتاج؟</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->changes as $change)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $change->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="fw-semibold text-dark">{{ $change->user?->name ?? 'النظام' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $change->change_type }}</span>
                                    </td>
                                    <td class="text-dark">{{ $change->change_notes }}</td>
                                    <td>
                                        @if($change->occurred_after_production_approval)
                                            <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> نعم (أعاد الطلب للمراجعة)</span>
                                        @else
                                            <span class="text-muted">لا</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
