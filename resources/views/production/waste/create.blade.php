@extends('layouts.app')

@section('title', 'تسجيل كمية هدر - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">تسجيل كمية هدر / تلف مادة خام</h4>
            <p class="text-muted mb-0 mt-1">أمر إنتاج: <span class="font-monospace fw-bold text-primary">{{ $productionOrder->production_order_number }}</span> - {{ $productionOrder->productModel?->name_ar ?? $productionOrder->custom_design_name }}</p>
        </div>
        <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> إلغاء وعودة
        </a>
    </div>

    <form action="{{ route('production.waste.store') }}" method="POST">
        @csrf
        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">
        @if($qualityIncident)
            <input type="hidden" name="quality_incident_id" value="{{ $qualityIncident->id }}">
        @endif

        <div class="row g-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-dumpster me-1"></i> بيانات الكمية التالفة والمادة الخام</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">المادة الخام التالفة</label>
                                <select name="material_id" class="form-select" required>
                                    <option value="">تحديد الخامة...</option>
                                    @foreach($materials as $mat)
                                        <option value="{{ $mat->id }}">{{ $mat->name_ar }} ({{ $mat->baseUnit?->name_ar }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">لون القماش (إن وجد)</label>
                                <select name="fabric_color_id" class="form-select">
                                    <option value="">تحديد اللون...</option>
                                    @foreach($fabricColors as $fc)
                                        <option value="{{ $fc->id }}">{{ $fc->color_name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">الكمية التالفة / الهدر</label>
                                <input type="number" step="0.0001" name="quantity" class="form-control" required min="0.0001" placeholder="أدخل الكمية التالفة">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">وحدة القياس</label>
                                <select name="unit_id" class="form-select" required>
                                    @foreach($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">سبب الهدر التحليلي</label>
                                <select name="waste_reason_id" class="form-select" required>
                                    <option value="">تحديد السبب...</option>
                                    @foreach($reasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">تاريخ وساعة التلف</label>
                                <input type="datetime-local" name="occurred_at" class="form-control" value="{{ date('Y-m-d\TH:i') }}" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">القسم المكتشف للهدر</label>
                                <select name="detected_department_id" class="form-select">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ auth()->user()->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">القسم المتسبب بالهدر</label>
                                <select name="responsible_department_id" class="form-select">
                                    <option value="">تحديد القسم المسؤول...</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ربط بدفعة مخزونية محددة (اختياري للصرف)</label>
                            <select name="inventory_lot_id" class="form-select">
                                <option value="">تحديد دفعة اللوت المتأثرة إن عُرفت...</option>
                                @foreach($lots as $lot)
                                    <option value="{{ $lot->id }}">دفعة {{ $lot->lot_code }} - {{ $lot->material?->name_ar }} (متبقي {{ (float)$lot->remaining_quantity }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات وشرح الهدر</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات توضيحية حول ظروف وأسباب التلف..."></textarea>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-save me-1"></i> توثيق وتسجيل كمية الهدر
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
