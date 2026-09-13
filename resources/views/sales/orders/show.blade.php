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
                                        $alreadyReleased = $line->productionOrders->where('status', '!=', 'CANCELLED')->sum('released_quantity');
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
