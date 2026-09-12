@extends('layouts.app')

@section('title', 'تفاصيل بلاغ الجودة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0 text-dark">بلاغ جودة: {{ $qualityIncident->incident_number }}</h4>
                @php
                    $sevBadge = match($qualityIncident->severity) {
                        'LOW' => 'bg-info text-dark',
                        'MEDIUM' => 'bg-warning text-dark',
                        'HIGH' => 'bg-danger',
                        'CRITICAL' => 'bg-dark text-white',
                        default => 'bg-secondary'
                    };
                @endphp
                <span class="badge {{ $sevBadge }} fs-6">{{ $qualityIncident->severity }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">
                أمر الإنتاج: <a href="{{ route('production.orders.show', $qualityIncident->productionOrder) }}" class="fw-bold font-monospace text-primary text-decoration-none">{{ $qualityIncident->productionOrder->production_order_number }}</a>
                &bull; العميل: <strong>{{ $qualityIncident->productionOrder->customerOrder?->customer?->name_ar }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.quality-incidents.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> عودة لسجل الجودة
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Incident Summary -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> بيانات بلاغ العيب المكتشف</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted" style="width: 140px;">نوع العيب:</td>
                            <td class="fw-bold"><span class="badge bg-light text-dark border fs-7">{{ $qualityIncident->incident_type }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">الكمية المتأثرة:</td>
                            <td class="fw-bold text-danger">{{ $qualityIncident->affected_quantity }} قطعة</td>
                        </tr>
                        <tr>
                            <td class="text-muted">قسم الاكتشاف:</td>
                            <td>{{ $qualityIncident->detectedDepartment?->name_ar }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">القسم المتسبب:</td>
                            <td><strong class="text-dark">{{ $qualityIncident->responsibleDepartment?->name_ar ?? 'غير محدد' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">المرحلة / العملية:</td>
                            <td>{{ $qualityIncident->operation?->operation_name_snapshot ?? 'عام على امر الإنتاج' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المُبلغ:</td>
                            <td>{{ $qualityIncident->detectedByUser?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">قرار المعالجة:</td>
                            <td>
                                @if($qualityIncident->disposition)
                                    <span class="badge bg-primary fs-6">{{ $qualityIncident->disposition }}</span>
                                @else
                                    <span class="text-muted">لم يُحدد بعد</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">وصف العيب:</td>
                            <td class="bg-light p-2 rounded small text-dark">{{ $qualityIncident->description }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Disposition Decision Form -->
            @can('production.manage_quality')
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-gavel me-1"></i> اتخاذ قرار المعالجة (Disposition)</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('production.quality-incidents.disposition', $qualityIncident) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label required">اختيار قرار المعالجة المعتمد</label>
                                <select name="disposition" class="form-select" required>
                                    <option value="REPAIR" {{ $qualityIncident->disposition === 'REPAIR' ? 'selected' : '' }}>إصلاح بسيط بالورشة (REPAIR)</option>
                                    <option value="REWORK" {{ $qualityIncident->disposition === 'REWORK' ? 'selected' : '' }}>إعادة تصنيع وتعديل (REWORK)</option>
                                    <option value="REMANUFACTURE" {{ $qualityIncident->disposition === 'REMANUFACTURE' ? 'selected' : '' }}>إعادة إنتاج قطاع/قطعة بالكامل (REMANUFACTURE)</option>
                                    <option value="SCRAP" {{ $qualityIncident->disposition === 'SCRAP' ? 'selected' : '' }}>تخريب وإتلاف خامات (SCRAP / WASTE)</option>
                                    <option value="ACCEPT_AS_IS" {{ $qualityIncident->disposition === 'ACCEPT_AS_IS' ? 'selected' : '' }}>قبول المنتج بحالته دون تعديل (ACCEPT AS IS)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ملاحظات مبرر القرار والحل التوجيهي</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات وتوجيهات للورشة..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-check-circle me-1"></i> حفظ وتعميم قرار المعالجة
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <!-- Rework Actions & Waste Records Associated -->
        <div class="col-md-7">
            <!-- Rework Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-tools me-1"></i> أوامر وإجراءات إعادة التصنيع (Rework Actions)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم الإجراء</th>
                                    <th>نوع الإجراء</th>
                                    <th>القسم المكلف</th>
                                    <th>الكمية</th>
                                    <th>الحالة</th>
                                    <th class="text-end">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($qualityIncident->reworkActions as $rework)
                                    <tr>
                                        <td class="fw-bold font-monospace">{{ $rework->rework_number }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $rework->action_type }}</span></td>
                                        <td>{{ $rework->assignedDepartment?->name_ar }}</td>
                                        <td class="fw-bold">{{ $rework->quantity }} قطعة</td>
                                        <td>
                                            @if($rework->status === 'COMPLETED')
                                                <span class="badge bg-success">مكتمل ومُنجز</span>
                                            @else
                                                <span class="badge bg-warning text-dark">قيد الإنجاز</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($rework->status !== 'COMPLETED' && auth()->user()->can('production.manage_rework'))
                                                <form action="{{ route('production.rework.complete', $rework) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check me-1"></i> إنجاز الإجراء
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">لا توجد أوامر إعادة تصنيع مسجلة لهذا البلاغ.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Waste Records Associated -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-dumpster me-1"></i> سجلات الهدر والتلف الناتجة عن البلاغ (Analytical Waste)</h6>
                    <a href="{{ route('production.waste.create', ['production_order_id' => $qualityIncident->production_order_id, 'quality_incident_id' => $qualityIncident->id]) }}" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-plus me-1"></i> تسجيل كمية هدر
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
                                    <th>السبب</th>
                                    <th>التكلفة التقديرية</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($qualityIncident->wasteRecords as $waste)
                                    <tr>
                                        <td class="fw-bold font-monospace">{{ $waste->waste_number }}</td>
                                        <td>{{ $waste->material?->name_ar }}</td>
                                        <td class="fw-bold text-danger">{{ (float)$waste->quantity }} {{ $waste->material?->baseUnit?->name_ar }}</td>
                                        <td>{{ $waste->wasteReason?->name_ar }}</td>
                                        <td class="fw-bold text-dark">
                                            @can('costing.view')
                                                {{ number_format($waste->total_cost, 2) }} ر.س
                                            @else
                                                <span class="text-muted small">سري</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">لا توجد كميات هدر تالفة مسجلة لهذا البلاغ.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
