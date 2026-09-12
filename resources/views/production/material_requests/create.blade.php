@extends('layouts.app')

@section('title', 'إنشاء طلب خامات إنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">طلب خامات إنتاج جديد</h4>
            <p class="text-muted mb-0 mt-1">أمر إنتاج: <span class="font-monospace fw-bold text-primary">{{ $productionOrder->production_order_number }}</span> - {{ $productionOrder->productModel?->name_ar ?? $productionOrder->custom_design_name }}</p>
        </div>
        <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة لأمر الإنتاج
        </a>
    </div>

    <form action="{{ route('production.material-requests.store') }}" method="POST">
        @csrf
        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">

        <div class="row g-4">
            <!-- Header Data -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-1"></i> بيانات الطلب والمستودع</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">المستودع الموجه له الطلب</label>
                            <select name="warehouse_id" class="form-select" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name_ar }} ({{ $wh->type }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">القسم الطالب</label>
                            <select name="requested_from_department_id" class="form-select">
                                <option value="">تحديد القسم الطالب...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ auth()->user()->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">تاريخ الطلب</label>
                            <input type="date" name="request_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات الطلب</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="أية ملاحظات إضافية لأمين المستودع..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lines Table -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-boxes-stacked me-1"></i> بنود المواد المطلوبة</h6>
                        <span class="badge bg-light text-dark border">مقتبسة من احتياج BOM / طلب إضافي</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="lines-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 35%;">المادة الخام</th>
                                        <th>لون القماش</th>
                                        <th>الكمية المطلوبة</th>
                                        <th>سبب الطلب</th>
                                        <th>الوحدة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($productionOrder->materialRequirements as $index => $req)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="lines[{{ $index }}][production_material_requirement_id]" value="{{ $req->id }}">
                                                <input type="hidden" name="lines[{{ $index }}][material_id]" value="{{ $req->material_id }}">
                                                <input type="hidden" name="lines[{{ $index }}][base_unit_id]" value="{{ $req->unit_id }}">
                                                <div class="fw-bold">{{ $req->material_name_snapshot }}</div>
                                                <small class="text-muted">مخطط BOM: {{ (float)$req->total_planned_quantity }} {{ $req->unit?->name_ar }}</small>
                                            </td>
                                            <td>
                                                @if($req->material?->category?->code === 'FABRIC')
                                                    <select name="lines[{{ $index }}][fabric_color_id]" class="form-select form-select-sm">
                                                        <option value="">تحديد اللون...</option>
                                                        @foreach($fabricColors as $fc)
                                                            <option value="{{ $fc->id }}" {{ $productionOrder->fabric_color_id == $fc->id ? 'selected' : '' }}>{{ $fc->color_name_ar }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" step="0.0001" name="lines[{{ $index }}][requested_quantity]" class="form-control form-control-sm" value="{{ (float)$req->total_planned_quantity }}" required min="0.0001">
                                            </td>
                                            <td>
                                                <select name="lines[{{ $index }}][request_reason]" class="form-select form-select-sm">
                                                    <option value="PLANNED_PRODUCTION" selected>إنتاج مخطط (PLANNED)</option>
                                                    <option value="ADDITIONAL_REQUIREMENT">احتياج إضافي (ADDITIONAL)</option>
                                                    <option value="REWORK">إعادة تصنيع (REWORK)</option>
                                                    <option value="REMANUFACTURE">إعادة إنتاج (REMANUFACTURE)</option>
                                                    <option value="CORRECTION">تصحيح (CORRECTION)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $req->unit?->name_ar }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                لا تتوفر وصفة رسمية مسجلة لهذا أمر الإنتاج. يمكنك إضافة سطر طلب عادي مخصص.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" name="action" value="draft" class="btn btn-outline-primary me-2">
                            <i class="fas fa-save me-1"></i> حفظ كمسودة
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i> تقديم للمستودع فوراً
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
