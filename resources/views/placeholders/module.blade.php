@extends('layouts.app')

@section('title', $title . ' - مصنع مفروشات سدير')
@section('page-title', $title)

@section('content')
<div class="d-flex flex-column gap-4">
    
    <!-- Page Header & Action Toolbar -->
    <div class="card bg-white p-4 border-0 shadow-sm rounded-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-warning text-dark font-monospace">STAGE 1 SHELL</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary">وحدة مستقبلية (Stage 2+)</span>
                </div>
                <h4 class="fw-bold text-dark mb-1">{{ $title }}</h4>
                <p class="text-muted mb-0 fs-7">تم تجهيز واجهة هذه الوحدة بالهيكل البصري القياسي والمعايير التصميمية المعتمدة لتكون جاهزة للتطوير في المراحل القادمة.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3 fs-7" onclick="alert('سيتم تفعيل هذه الخاصية في المرحلة الثانية (Stage 2).')">
                    <i class="fas fa-file-export me-1"></i> تصدير البيانات
                </button>
                <button type="button" class="btn btn-factory-warning rounded-3 px-4 fs-7" onclick="alert('واجهة إضافة البيانات مجهزة وستكون متاحة في المرحلة التالية.')">
                    <i class="fas fa-plus me-1"></i> إضافة جديدة
                </button>
            </div>
        </div>
    </div>

    <!-- Reusable Table Preview Component -->
    <div class="card bg-white border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fas fa-table me-2 text-warning"></i> معاينة جدول البيانات القياسي (Reusable Table Component)
            </h6>
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-sm" placeholder="تصفية البيانات..." style="width: 200px;">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th># المعرف</th>
                        <th>البيان / الاسم الداخلي</th>
                        <th>النوع / القسم</th>
                        <th>الحالة الميدانية</th>
                        <th>تاريخ الإدخال</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold text-dark">#SAD-1001</td>
                        <td>
                            <div class="fw-semibold">نموذج سرير مبلان (Milan Bed Model)</div>
                            <small class="text-muted">قياس مرجعي 160×200 سم - يتوفر بخيار تخزين</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">موديل داخلي</span></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1">نشط بالإنتاج</span></td>
                        <td>10 سبتمبر 2026</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="معاينة"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="تعديل"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-light border text-danger" title="حذف" 
                                    @click="$dispatch('open-confirm-modal', { title: 'حذف العنصر', message: 'هل أنت تأكد من رغبتك في حذف هذا العنصر المعاين؟', actionUrl: '#' })">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">#SAD-1002</td>
                        <td>
                            <div class="fw-semibold">نموذج سرير أفالون (Avalon Bed Model)</div>
                            <small class="text-muted">قياس مرجعي 180×200 سم - بوكس قياسي مشاع</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">موديل داخلي</span></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1">نشط بالإنتاج</span></td>
                        <td>10 سبتمبر 2026</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="معاينة"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="تعديل"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-light border text-danger" title="حذف"
                                    @click="$dispatch('open-confirm-modal', { title: 'حذف العنصر', message: 'هل أنت تأكد من رغبتك في حذف هذا العنصر المعاين؟', actionUrl: '#' })">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">#SAD-1003</td>
                        <td>
                            <div class="fw-semibold">قماش مخمل رويال (Lot #8821)</div>
                            <small class="text-muted">مستورد من المورد A - بالمتر الطولي</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">مادة خام</span></td>
                        <td><span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2.5 py-1">منخفض المخزون</span></td>
                        <td>08 سبتمبر 2026</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="معاينة"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-light border text-secondary me-1" title="تعديل"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-light border text-danger" title="حذف"
                                    @click="$dispatch('open-confirm-modal', { title: 'حذف العنصر', message: 'هل أنت تأكد من رغبتك في حذف هذا العنصر المعاين؟', actionUrl: '#' })">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">عرض 1 إلى 3 من أصل 3 سجلات تجريبية</small>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">السابق</a></li>
                    <li class="page-item active"><a class="page-link bg-warning text-dark border-warning" href="#">1</a></li>
                    <li class="page-item disabled"><a class="page-link" href="#">التالي</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Reusable Form Preview Component -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4">
        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
            <i class="fas fa-edit me-2 text-warning"></i> معاينة نموذج الإدخال القياسي (Reusable Form Component)
        </h6>
        <form onsubmit="event.preventDefault(); alert('هذا النموذج نموذج بصري توضيحي للمرحلة الأولى.');">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">الاسم أو البيان القياسي <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" placeholder="أدخل الاسم التجاري أو المسمى الداخلي">
                </div>
                <div class="col-md-6">
                    <label class="form-label">القسم المسؤول / الفئة</label>
                    <select class="form-select">
                        <option value="">اختر القسم...</option>
                        <option value="carpentry">النجارة (Carpentry)</option>
                        <option value="upholstery">التنجيد (Upholstery)</option>
                        <option value="warehouse">المستودع والمواد (Warehouse)</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">ملاحظات ومواصفات الفنية</label>
                    <textarea class="form-control" rows="3" placeholder="ملاحظات الإنتاج أو قياسات العميل المطلوبة..."></textarea>
                </div>
                <div class="col-md-12 d-flex justify-content-end gap-2 mt-4">
                    <button type="reset" class="btn btn-light border px-4">إعادة ضبط</button>
                    <button type="submit" class="btn btn-factory-primary px-4">حفظ النموذج</button>
                </div>
            </div>
        </form>
    </div>

</div>
@endsection
