@extends('layouts.app')

@section('title', 'تسجيل بلاغ جودة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="page-header-card d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">تسجيل بلاغ جودة / عيب إنتاجي جديد</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">أمر إنتاج: <span class="font-monospace fw-bold text-primary">{{ $productionOrder->production_order_number }}</span> - {{ $productionOrder->productModel?->name_ar ?? $productionOrder->custom_design_name }}</p>
        </div>
        <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> إلغاء وعودة
        </a>
    </div>

    <form action="{{ route('production.quality-incidents.store') }}" method="POST">
        @csrf
        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-circle me-1"></i> تفاصيل حالة الجودة / العيب</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">المرحلة / العملية المرتبطة بالعيب (اختياري)</label>
                            <select name="production_order_operation_id" class="form-select">
                                <option value="">غير مخصصة لمرحلة بعينها (العيب عام على المنتج)</option>
                                @foreach($productionOrder->operations as $op)
                                    <option value="{{ $op->id }}">{{ $op->sequence_number }}. {{ $op->operation_name_snapshot }} - {{ $op->workCenter?->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">نوع العيب / المشكلة</label>
                                <select name="incident_type" class="form-select" required>
                                    <option value="WRONG_DIMENSION">خطأ في المقاسات / الأبعاد</option>
                                    <option value="WRONG_FABRIC">خطأ في نوع القماش</option>
                                    <option value="WRONG_COLOR">خطأ في اللون / الطلاء</option>
                                    <option value="MATERIAL_DAMAGE">تلف أو عيب خامة</option>
                                    <option value="WORKMANSHIP_DEFECT">عيب تصنيع / نجارة / تنجيد</option>
                                    <option value="LOADING_DAMAGE">تلف مناولة وتحميل</option>
                                    <option value="MISSING_COMPONENT">نقص مكون أو قطاع</option>
                                    <option value="ASSEMBLY_ERROR">خطأ تجميع أو تركيب</option>
                                    <option value="OTHER">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">درجة الأهمية / الخاطرة</label>
                                <select name="severity" class="form-select" required>
                                    <option value="LOW">منخفضة</option>
                                    <option value="MEDIUM" selected>متوسطة</option>
                                    <option value="HIGH">عالية</option>
                                    <option value="CRITICAL">حرجة</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">الكمية المتأثرة (قطع)</label>
                                <input type="number" name="affected_quantity" class="form-control" value="1" min="1" max="{{ $productionOrder->released_quantity }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">قسم اكتشاف العيب</label>
                                <select name="detected_department_id" class="form-select" required>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ auth()->user()->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">القسم المتسبب / المسؤول</label>
                            <select name="responsible_department_id" class="form-select">
                                <option value="">تحديد القسم المسؤول إن عُرف...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">وصف تفصيلي للعيب والملاحظات</label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="اشرح تفاصيل المشكلة أو العيب المكتشف بدقة..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optional Initial Disposition -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-gavel me-1"></i> قرار المعالجة المبدئي (اختياري)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">قرار المعالجة</label>
                            <select name="disposition" class="form-select">
                                <option value="">ترك القرار لمسؤول الجودة / مدير الإنتاج لاحقاً</option>
                                <option value="REPAIR">إصلاح بسيط بالورشة</option>
                                <option value="REWORK">إعادة تصنيع وتعديل</option>
                                <option value="REMANUFACTURE">إعادة تصنيع القطعة بالكامل</option>
                                <option value="SCRAP">تخريب وإتلاف خامة</option>
                                <option value="ACCEPT_AS_IS">قبول المنتج بحالته</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات مبرر القرار</label>
                            <textarea name="disposition_notes" class="form-control" rows="3" placeholder="ملاحظات توضيحية لسبب القرار..."></textarea>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" class="btn btn-danger btn-lg w-100">
                            <i class="fas fa-save me-1"></i> تسجيل بلاغ الجودة
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
