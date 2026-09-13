@extends('layouts.app')

@section('title', 'تسجيل عرض سعر مورد جديدة')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus-circle me-2 text-primary"></i>تسجيل عرض سعر مورد (Supplier Quotation)
            </h1>
            <p class="text-muted small mb-0">إدخال أسعار وحدات الشراء ومعاملات التحويل ومواعيد التوريد المقدمة من المورد</p>
        </div>
        <a href="{{ route('purchasing.quotations.index') }}" class="btn btn-outline-secondary rounded-pill px-4">إلغاء والعودة</a>
    </div>

    <form action="{{ route('purchasing.quotations.store') }}" method="POST" x-data="supplierQuotationForm()">
        @csrf
        @if($rfq)<input type="hidden" name="purchase_rfq_id" value="{{ $rfq->id }}">@endif

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark">بيانات المورد وعرض السعر</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">المورد *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">اختر المورد...</option>
                            @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">الرقم المرجعي لعرض المورد (إن وجد)</label>
                        <input type="text" name="supplier_reference" class="form-control" placeholder="مثال: QT-SUP-8090">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">تاريخ العرض *</label>
                        <input type="date" name="quotation_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">تاريخ صلاحية العرض</label>
                        <input type="date" name="valid_until" class="form-control" value="{{ now()->addDays(30)->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">مدة التوريد (بالأيام)</label>
                        <input type="number" name="lead_time_days" class="form-control" placeholder="5 أيام">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">شروط الدفع والتسليم</label>
                        <input type="text" name="payment_terms" class="form-control" placeholder="نقداً، 30 يوم، 50% مقدم...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lines -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">أسعار المواد والخامات المسعرة بعرض المورد</h6>
                <button type="button" @click="addLine()" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i>إضافة خامة مسعرة
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light small">
                        <tr>
                            <th width="25%">المادة / الخامة *</th>
                            <th width="15%">وحدة الشراء للمورد *</th>
                            <th width="15%">معامل التحويل للوحدة الأساسية *</th>
                            <th width="15%">الكمية المعروضة *</th>
                            <th width="15%">سعر وحدة الشراء (SAR) *</th>
                            <th width="15%">إجمالي البند (SAR)</th>
                            <th width="5%" class="text-center">حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr>
                                <td>
                                    <select :name="'lines['+index+'][material_id]'" class="form-select form-select-sm" x-model="line.material_id" required>
                                        <option value="">اختر الخامة...</option>
                                        @foreach($materials as $mat)
                                        <option value="{{ $mat->id }}">{{ $mat->name_ar }} ({{ $mat->code }}) - [{{ $mat->unitOfMeasure?->name_ar }}]</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select :name="'lines['+index+'][purchase_unit_id]'" class="form-select form-select-sm" x-model="line.purchase_unit_id" required>
                                        <option value="">وحدة الشراء...</option>
                                        @foreach($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][conversion_factor]'" class="form-control form-control-sm" x-model="line.conversion_factor" required placeholder="مثال: 50 للمتر بالربطة">
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][quoted_quantity]'" class="form-control form-control-sm" x-model="line.quoted_quantity" required>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" :name="'lines['+index+'][unit_price]'" class="form-control form-control-sm" x-model="line.unit_price" required>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark" x-text="(line.quoted_quantity * line.unit_price).toFixed(2)"></span> SAR
                                </td>
                                <td class="text-center">
                                    <button type="button" @click="removeLine(index)" class="btn btn-sm btn-outline-danger border-0">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white p-3 text-end">
                <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold">
                    <i class="fas fa-save me-1"></i>حفظ عرض سعر المورد
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function supplierQuotationForm() {
    return {
        lines: [
            { material_id: '', purchase_unit_id: '', conversion_factor: 1, quoted_quantity: 1, unit_price: 0 }
        ],
        addLine() {
            this.lines.push({ material_id: '', purchase_unit_id: '', conversion_factor: 1, quoted_quantity: 1, unit_price: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        }
    };
}
</script>
@endsection
