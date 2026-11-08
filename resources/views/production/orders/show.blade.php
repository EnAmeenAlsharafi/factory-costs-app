@extends('layouts.app')

@section('title', 'تفاصيل أمر الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    @if($order->customerOrderLine?->special_fabric)
        <div class="alert alert-warning">
            <strong>طلب بقماش خاص:</strong> {{ $order->customerOrderLine->fabric_notes }}
            <p class="mb-0">يلزم ربط القماش واللون والمورد بالمخزون، ثم استلام القماش وصرفه على هذا الأمر قبل التنجيد.
                <a href="{{ route('sales.orders.show', $order->customerOrder) }}">مراجعة الربط في طلب العميل</a>
            </p>
        </div>
    @endif
    @include('production.orders.partials.header')

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <small class="text-muted d-block">الكمية المكتملة كلياً</small>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <span class="fs-4 fw-bold text-success">{{ $order->completed_quantity }}</span>
                        <span class="text-muted">من أصل {{ $order->released_quantity }} قطعة</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <small class="text-muted d-block">نسبة الإنجاز النهائية</small>
                    @php
                        $pct = $order->released_quantity > 0 ? round(($order->completed_quantity / $order->released_quantity) * 100) : 0;
                    @endphp
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <span class="fs-4 fw-bold text-primary">{{ $pct }}%</span>
                        <div class="progress w-50" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <small class="text-muted d-block">صافي التكلفة الفعلية للمواد</small>
                    <div class="mt-1">
                        @if($canViewCost)
                            <span class="fs-4 fw-bold text-dark">{{ number_format($costSummary['actual_net_material_cost'], 2) }} ر.س</span>
                        @else
                            <span class="badge bg-secondary fs-6">سري</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <small class="text-muted d-block">بلاغات الجودة / الهدر</small>
                    <div class="mt-1 d-flex gap-2 align-items-center">
                        <span class="badge bg-danger fs-6">{{ $order->qualityIncidents->count() }} بلاغ جودة</span>
                        <span class="badge bg-secondary fs-6">{{ $order->wasteRecords->count() }} هدر</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs order-tabs border-bottom mb-4" id="orderTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="wip-tab" data-bs-toggle="tab" data-bs-target="#wip-pane" type="button">
                <i class="fas fa-tasks me-1"></i> المراحل والمواصفات
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials-pane" type="button">
                <i class="fas fa-clipboard-list me-1"></i> الخامات ({{ $order->materialRequests->count() }})
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="quality-tab" data-bs-toggle="tab" data-bs-target="#quality-pane" type="button">
                <i class="fas fa-exclamation-triangle me-1"></i> الجودة ({{ $order->qualityIncidents->count() }})
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="waste-tab" data-bs-toggle="tab" data-bs-target="#waste-pane" type="button">
                <i class="fas fa-dumpster me-1"></i> الهدر ({{ $order->wasteRecords->count() }})
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="cost-tab" data-bs-toggle="tab" data-bs-target="#cost-pane" type="button">
                <i class="fas fa-calculator me-1"></i> التكلفة
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="fg-tab" data-bs-toggle="tab" data-bs-target="#fg-pane" type="button">
                <i class="fas fa-boxes me-1"></i> الاستلام بالمخزون ({{ $order->finishedGoodsReceipts->count() }})
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button">
                <i class="fas fa-timeline me-1"></i> السجل
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="orderTabsContent">
        <!-- Tab 1: WIP Operations & Specs -->
        <div class="tab-pane fade show active" id="wip-pane">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-bed me-1"></i> مواصفات التصنيع المعتمدة</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" style="width: 140px;">نوع المنتج:</td>
                                    <td class="fw-bold">
                                        @if($order->is_custom_design)
                                            <span class="badge bg-info text-dark"><i class="fas fa-paint-brush me-1"></i>تصميم خاص: {{ $order->custom_design_name }}</span>
                                        @else
                                            {{ $order->productModel?->name_ar }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">الأبعاد المطلوبة:</td>
                                    <td class="fw-bold">{{ (int)$order->requested_width_cm }} × {{ (int)$order->requested_length_cm }} سم</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">خيار التخزين:</td>
                                    <td>
                                        @if($order->has_storage)
                                            <span class="badge bg-success">يتضمن تخزين</span>
                                        @else
                                            <span class="badge bg-light text-dark border">بدون تخزين</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">مورد القماش:</td>
                                    <td><strong class="text-dark">{{ $order->fabricSupplier?->name ?? $order->fabricMaterial?->fabricSpec?->supplier?->name ?? 'غير محدد' }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">نوع القماش / الكتالوج:</td>
                                    <td>
                                        <span class="text-dark fw-semibold">{{ $order->fabricMaterial?->name_ar ?? 'غير محدد' }}</span>
                                        @if($order->fabricMaterial?->fabricSpec?->catalog_number)
                                            <span class="badge bg-light text-primary border ms-1">كتالوج: #{{ $order->fabricMaterial->fabricSpec->catalog_number }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">أكواد اللون (داخلي / مورد):</td>
                                    <td>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            @if($order->fabric_color_code)
                                                <span class="badge bg-light text-dark border">
                                                    داخلي (CS): <code class="text-dark fw-bold fs-7">{{ $order->fabric_color_code }}</code>
                                                </span>
                                            @elseif($order->fabricColor)
                                                <span class="badge bg-light text-dark border"><i class="fas fa-circle me-1" style="color: {{ $order->fabricColor->hex_code ?? '#ccc' }};"></i>{{ $order->fabricColor->color_name_ar }}</span>
                                            @else
                                                <span class="text-muted">غير محدد</span>
                                            @endif

                                            @if($order->fabric_supplier_color_code)
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                                    كود المورد: <code class="text-primary fw-bold fs-7">{{ $order->fabric_supplier_color_code }}</code>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">وصفة التصنيع:</td>
                                    <td>
                                        @if($order->recipeVersion)
                                            <span class="status-chip status-chip-success"><i class="fas fa-scroll" aria-hidden="true"></i> {{ $order->recipeVersion->recipe?->name }} (V{{ $order->recipeVersion->version_number }})</span>
                                        @else
                                            <span class="status-chip status-chip-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> غير مرتبط بوصفة</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">ملاحظات الإنتاج:</td>
                                    <td class="text-dark bg-light p-2 rounded small">{{ $order->production_notes ?? 'لا توجد ملاحظات مدونة' }}</td>
                                </tr>
                            </table>
                            @if ($recipeChoices !== null)
                                <div id="recipe" tabindex="-1" class="recipe-link-box {{ $order->recipeVersion ? '' : 'is-missing' }}">
                                    @if (! $order->recipeVersion)
                                        <p class="fw-semibold mb-1"><i class="fas fa-link me-1" aria-hidden="true"></i> اربط هذا الطلب بوصفة جاهزة</p>
                                        <p class="fs-7 text-muted mb-2">إذا كان التصميم الخاص يطابق منتجاً لديه وصفة، اربطه بها لتُحسب الخامات تلقائياً ويُطلب صرفها بضغطة واحدة.</p>
                                    @endif
                                    @if ($recipeChoices->isEmpty())
                                        <p class="fs-7 text-muted mb-0">لا توجد وصفات معتمدة بعد. أنشئ وصفة من «وصفات التصنيع» أولاً.</p>
                                    @else
                                        <details @if (! $order->recipeVersion) open @endif>
                                            <summary class="fs-7 fw-semibold text-primary">{{ $order->recipeVersion ? 'تغيير الوصفة' : 'اختيار الوصفة' }}</summary>
                                            <form action="{{ route('production.orders.link-recipe', $order) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2"
                                                  @if ($order->materialRequests->where('status', 'DRAFT')->isNotEmpty()) data-confirm="ستُحذف مسودات طلبات الخامات الحالية وتُحسب الخامات من الوصفة الجديدة. متابعة؟" @endif>
                                                @csrf
                                                <label for="recipe-version" class="visually-hidden">الوصفة</label>
                                                <select id="recipe-version" name="manufacturing_recipe_version_id" class="form-select flex-grow-1" style="min-width: 220px;" required>
                                                    <option value="">اختر وصفة معتمدة...</option>
                                                    @foreach ($recipeChoices as $version)
                                                        <option value="{{ $version->id }}" @selected($order->manufacturing_recipe_version_id === $version->id)>
                                                            {{ $version->recipe?->name }} (V{{ $version->version_number }}){{ $version->recipe?->product_configuration_id && $version->recipe->product_configuration_id === $order->product_configuration_id ? ' — مطابقة للمنتج' : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-primary text-nowrap"><i class="fas fa-link me-1" aria-hidden="true"></i> {{ $order->recipeVersion ? 'تغيير' : 'ربط الوصفة' }}</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card border-0 shadow-sm mb-4" id="operations" tabindex="-1">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-tasks me-1"></i> مراحل التصنيع في الأقسام</h6>
                            <a href="{{ route('production.queue.index') }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> طابور الأقسام</a>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                @forelse($order->operations as $op)
                                    @php
                                        $opPct = $op->required_quantity > 0 ? round(($op->completed_quantity / $op->required_quantity) * 100) : 0;
                                        $openRework = $op->targetReworkActions->isNotEmpty();
                                    @endphp
                                    <div class="list-group-item px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-secondary rounded-circle" style="width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;">{{ $op->sequence_number }}</span>
                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark">{{ $op->operation_name_snapshot }}</h6>
                                                    <small class="text-muted"><i class="fas fa-building me-1"></i>{{ $op->workCenter?->name_ar }} ({{ $op->workCenter?->department?->name_ar }})</small>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                @if ($openRework)
                                                    <span class="rework-flag"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل</span>
                                                @endif
                                                <span class="badge {{ $op->status_badge_class }}">{{ $op->status_arabic }}</span>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="progress flex-grow-1" style="height: 10px;">
                                                <div class="progress-bar bg-success" style="width: {{ $opPct }}%"></div>
                                            </div>
                                            <div class="fw-bold fs-7" style="min-width: 100px; text-align: left;">
                                                <span class="text-success fs-6 ltr-isolate">{{ $op->completed_quantity }}</span> / {{ $op->required_quantity }} قطعة
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-3 fs-8 text-muted">
                                            <span><i class="fas fa-user-check me-1" aria-hidden="true"></i>{{ $op->assignedUser?->name ?? 'غير مُسند' }}</span>
                                            @if ($op->started_at)
                                                <span><i class="fas fa-play me-1" aria-hidden="true"></i>بدء: {{ $op->started_at->format('Y-m-d H:i') }}</span>
                                            @endif
                                            @if ($op->completed_at)
                                                <span><i class="fas fa-flag-checkered me-1" aria-hidden="true"></i>اكتمال: {{ $op->completed_at->format('Y-m-d H:i') }}</span>
                                            @endif
                                        </div>

                                        @if (in_array($op->status, ['READY', 'ASSIGNED'], true) && $assignableEmployees !== null)
                                            @php $departmentEmployees = $assignableEmployees->get($op->workCenter?->department_id, collect()); @endphp
                                            @if ($departmentEmployees->isNotEmpty())
                                                <form action="{{ route('production.operations.assign', $op) }}" method="POST" class="op-assign-form mt-2">
                                                    @csrf
                                                    <label for="assign-op-{{ $op->id }}" class="visually-hidden">إسناد «{{ $op->operation_name_snapshot }}» إلى</label>
                                                    <select id="assign-op-{{ $op->id }}" name="employee_id" class="form-select form-select-sm" required>
                                                        <option value="">{{ $op->assigned_user_id ? 'إعادة الإسناد إلى...' : 'إسناد إلى...' }}</option>
                                                        @foreach ($departmentEmployees as $employee)
                                                            <option value="{{ $employee->id }}" @selected($op->assigned_user_id === $employee->id)>{{ $employee->name }} — {{ $employee->open_tasks }} مهام مفتوحة</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">{{ $op->assigned_user_id ? 'إعادة إسناد' : 'إسناد' }}</button>
                                                </form>
                                            @endif
                                        @endif

                                        @if ($op->status === 'BLOCKED' && $op->activeBlock)
                                            <div class="alert alert-danger py-2 mt-2 mb-0 fs-8" role="alert">
                                                <i class="fas fa-hand me-1" aria-hidden="true"></i>
                                                <strong>متوقف:</strong> {{ $op->activeBlock->reason_arabic }}
                                                @if ($op->activeBlock->note) — {{ $op->activeBlock->note }} @endif
                                                @can('production.operation.unblock')
                                                    <form action="{{ route('production.operations.resolve-block', $op) }}" method="POST" class="d-inline ms-2"
                                                          data-confirm="تأكيد حل العائق واستئناف عملية «{{ $op->operation_name_snapshot }}»؟">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-unlock me-1" aria-hidden="true"></i> حل العائق</button>
                                                    </form>
                                                @endcan
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-route fa-2x mb-2 d-block"></i>
                                        لم يتم إطلاق أمر الإنتاج للورشة بعد.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Material Requests & Issues -->
        <div class="tab-pane fade" id="materials-pane">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-clipboard-list me-1"></i> طلبات خامات الإنتاج الصادرة</h6>
                    <a href="{{ route('production.material-requests.create', ['production_order_id' => $order->id]) }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> طلب خامات جديد
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>القسم الطالب</th>
                                    <th>المستودع</th>
                                    <th>تاريخ الطلب</th>
                                    <th>عدد البنود</th>
                                    <th>الحالة</th>
                                    <th class="text-end">التفاصيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->materialRequests as $req)
                                    <tr>
                                        <td>
                                            <a href="{{ route('production.material-requests.show', $req) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                                {{ $req->request_number }}
                                            </a>
                                        </td>
                                        <td>{{ $req->requestedFromDepartment?->name_ar ?? 'غير محدد' }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $req->warehouse?->name_ar }}</span></td>
                                        <td>{{ $req->request_date?->format('Y-m-d') }}</td>
                                        <td><span class="badge bg-secondary rounded-pill">{{ $req->lines->count() }}</span></td>
                                        <td><span class="badge bg-info text-dark">{{ $req->status }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('production.material-requests.show', $req) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> عرض</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">لا توجد طلبات خامات إنتاج مدونة لهذا الأمر.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Quality & Rework -->
        <div class="tab-pane fade" id="quality-pane">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> سجل حالات الجودة المسجلة</h6>
                    <a href="{{ route('production.quality-incidents.create', ['production_order_id' => $order->id]) }}" class="btn btn-sm btn-danger">
                        <i class="fas fa-plus me-1"></i> تسجيل بلاغ جودة
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم البلاغ</th>
                                    <th>نوع العيب</th>
                                    <th>الأهمية</th>
                                    <th>قسم الاكتشاف</th>
                                    <th>القسم المسؤول</th>
                                    <th>القرار</th>
                                    <th>الحالة</th>
                                    <th class="text-end">التفاصيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->qualityIncidents as $inc)
                                    <tr>
                                        <td>
                                            <a href="{{ route('production.quality-incidents.show', $inc) }}" class="fw-bold font-monospace text-danger text-decoration-none">
                                                {{ $inc->incident_number }}
                                            </a>
                                        </td>
                                        <td>{{ \App\Models\QualityIncident::TYPE_LABELS[$inc->incident_type] ?? $inc->incident_type }}</td>
                                        <td><span class="badge bg-warning text-dark">{{ \App\Models\QualityIncident::SEVERITY_LABELS[$inc->severity][0] ?? $inc->severity }}</span></td>
                                        <td>{{ $inc->detectedDepartment?->name_ar }}</td>
                                        <td>{{ $inc->responsibleDepartment?->name_ar ?? 'غير محدد' }}</td>
                                        <td><span class="badge bg-primary">{{ $inc->disposition ?? 'قيد الدراسة' }}</span></td>
                                        <td><span class="badge bg-secondary">{{ $inc->status }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('production.quality-incidents.show', $inc) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> عرض</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">لا توجد حالات جودة أو عيوب مسجلة لهذا الأمر.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Waste Records -->
        <div class="tab-pane fade" id="waste-pane">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-dumpster me-1"></i> سجلات الهدر والتلف التحليلي</h6>
                    <a href="{{ route('production.waste.create', ['production_order_id' => $order->id]) }}" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-plus me-1"></i> تسجيل هدر
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم الهدر</th>
                                    <th>المادة الخام</th>
                                    <th>الكمية التالفة</th>
                                    <th>السبب التحليلي</th>
                                    <th>القسم المسؤول</th>
                                    <th>التكلفة التحليلية</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->wasteRecords as $waste)
                                    <tr>
                                        <td class="fw-bold font-monospace text-danger">{{ $waste->waste_number }}</td>
                                        <td>{{ $waste->material?->name_ar }}</td>
                                        <td class="fw-bold text-danger">{{ (float)$waste->quantity }} {{ $waste->unit?->name_ar }}</td>
                                        <td>{{ $waste->wasteReason?->name_ar }}</td>
                                        <td>{{ $waste->responsibleDepartment?->name_ar ?? $waste->detectedDepartment?->name_ar }}</td>
                                        <td>
                                            @if($canViewCost)
                                                <span class="fw-bold text-dark">{{ number_format($waste->total_cost, 2) }} ر.س</span>
                                            @else
                                                <span class="text-muted small">سري</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">لا توجد كميات هدر تالفة مدونة لهذا الأمر.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 5: Material Cost Summary -->
        <div class="tab-pane fade" id="cost-pane">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-calculator me-1"></i> ملخص وتكلفة الاستهلاك المادي لأمر الإنتاج</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted">إجمالي تكلفة الخامات المصروفة:</td>
                                    <td class="fw-bold">
                                        @if($canViewCost)
                                            {{ number_format($costSummary['total_issued_cost'], 2) }} ر.س
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">إجمالي المرتجع للمستودع:</td>
                                    <td class="fw-bold text-success">
                                        @if($canViewCost)
                                            - {{ number_format($costSummary['total_returned_cost'], 2) }} ر.س
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fw-bold text-dark">صافي الاستهلاك الفعلي للمواد:</td>
                                    <td class="fw-bold fs-5 text-primary">
                                        @if($canViewCost)
                                            {{ number_format($costSummary['actual_net_material_cost'], 2) }} ر.س
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted">التكلفة التقديرية المخططة (BOM):</td>
                                    <td class="fw-bold">
                                        @if($canViewCost)
                                            {{ number_format($costSummary['planned_bom_cost'], 2) }} ر.س
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">انحراف التكلفة الفعلية عن BOM:</td>
                                    <td class="fw-bold {{ $costSummary['is_over_budget'] ? 'text-danger' : 'text-success' }}">
                                        @if($canViewCost)
                                            {{ $costSummary['cost_variance'] > 0 ? '+' : '' }}{{ number_format($costSummary['cost_variance'], 2) }} ر.س ({{ $costSummary['cost_variance_percentage'] }}%)
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">تكلفة الهدر والتلف (تحليلي):</td>
                                    <td class="fw-bold text-danger">
                                        @if($canViewCost)
                                            {{ number_format($costSummary['total_waste_cost'], 2) }} ر.س
                                        @else
                                            <span class="text-muted">سري</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 6: Finished Goods Handover -->
        <div class="tab-pane fade" id="fg-pane">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-boxes text-success me-2"></i>سندات تسليم المنتجات الجاهزة للمخزن
                    </h6>
                    @if((float)$order->completed_quantity > 0 && auth()->user()->can('finished_goods.receive'))
                    <a href="{{ route('finished-goods.receipts.create', ['production_order_id' => $order->id]) }}" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>إنشاء سند تسليم جديد
                    </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th class="ps-4">رقم سند التسليم</th>
                                    <th>المخزن المستلم</th>
                                    <th>الكمية المستلمة</th>
                                    <th>تاريخ السند</th>
                                    <th>حالة السند</th>
                                    <th class="text-end pe-4">عرض</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->finishedGoodsReceipts as $receipt)
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <a href="{{ route('finished-goods.receipts.show', $receipt) }}" class="text-success text-decoration-none">
                                            {{ $receipt->receipt_number }}
                                        </a>
                                    </td>
                                    <td>{{ $receipt->warehouse->name_ar ?? 'مخزن الجاهز' }}</td>
                                    <td><span class="badge bg-success px-3 py-1 fs-6">{{ (float)$receipt->received_quantity }} قطعة</span></td>
                                    <td>{{ $receipt->receipt_date ? $receipt->receipt_date->format('Y-m-d') : '' }}</td>
                                    <td>
                                        @if($receipt->status === 'POSTED')
                                            <span class="badge bg-success">مرحل لمخزن الجاهز</span>
                                        @else
                                            <span class="badge bg-secondary">مسودة</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ route('finished-goods.receipts.show', $receipt) }}" class="btn btn-sm btn-light rounded-circle shadow-sm">
                                            <i class="fas fa-eye text-success"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        لم يتم تسجيل أي سند تسليم للمنتجات الجاهزة بعد لأمر الإنتاج هذا.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 7: Unified Timeline -->
        <div class="tab-pane fade" id="timeline-pane">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-timeline text-primary me-2"></i> سجل رحلة أمر الإنتاج</h6>
                </div>
                <div class="card-body">
                    <x-order-timeline :timeline="$timeline" empty-text="لا توجد أحداث تنفيذية مسجلة بعد لهذا الأمر." />
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hold -->
<div class="modal fade" id="holdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('production.orders.hold', $order) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">تعليق أمر الإنتاج</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">سبب التعليق والإيقاف المؤقت</label>
                        <textarea name="hold_reason" class="form-control" rows="3" required placeholder="سبب إيقاف أمر الإنتاج بالورشة..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-dark">تأكيد التعليق</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
