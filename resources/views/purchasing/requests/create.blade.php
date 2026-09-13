@extends('layouts.app')

@section('title', 'إنشاء طلب شراء جديد')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus-circle me-2 text-primary"></i>إنشاء طلب شراء خامات (Purchase Request)
            </h1>
            <p class="text-muted small mb-0">توصيف الاحتياجات من الخامات ومستلزمات الإنتاج وإرسالها للمراجعة</p>
        </div>
        <a href="{{ route('purchasing.requests.index') }}" class="btn btn-outline-secondary rounded-pill px-4">إلغاء والعودة</a>
    </div>

    <form action="{{ route('purchasing.requests.store') }}" method="POST" x-data="purchaseRequestForm()">
        @csrf

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark">البيانات الأساسية لطلب الشراء</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">المستودع المستهدف *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">اختر المستودع...</option>
                            @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name_ar }} ({{ $w->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">القسم الطالب</label>
                        <select name="department_id" class="form-select">
                            <option value="">اختر القسم (اختياري)...</option>
                            @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">درجة الأولوية *</label>
                        <select name="priority" class="form-select" required>
                            <option value="NORMAL">عادي (Normal)</option>
                            <option value="URGENT">عاجل (Urgent)</option>
                            <option value="CRITICAL">حرج / طارئ (Critical)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">تاريخ الطلب المطلوبة به الخامات</label>
                        <input type="date" name="required_by_date" class="form-control" value="{{ now()->addDays(7)->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-bold">مبرر وأسباب طلب الشراء</label>
                        <input type="text" name="justification" class="form-control" placeholder="مثال: تغطية احتياج طلبية فندق، نقص مخزون خشب الزان...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Lines -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">بنود المواد والخامات المطلوبة</h6>
                <button type="button" @click="addLine()" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i>إضافة بند خامة
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light small">
                        <tr>
                            <th width="30%">المادة / الخامة *</th>
                            <th width="20%">لون القماش (إن وجد)</th>
                            <th width="15%">الكمية المطلوبة *</th>
                            <th width="20%">المورد المفضل (اختياري)</th>
                            <th width="10%" class="text-center">حذف</th>
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
                                    <input type="number" :name="'lines['+index+'][fabric_color_id]'" class="form-control form-control-sm" placeholder="معرف اللون إن وجد">
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][requested_quantity]'" class="form-control form-control-sm" x-model="line.requested_quantity" required>
                                </td>
                                <td>
                                    <select :name="'lines['+index+'][preferred_supplier_id]'" class="form-select form-select-sm">
                                        <option value="">لا يوجد مورد محدد...</option>
                                        @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                        @endforeach
                                    </select>
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
                <button type="submit" name="action" value="draft" class="btn btn-secondary rounded-pill px-4 me-2">
                    <i class="fas fa-save me-1"></i>حفظ كمسودة
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-paper-plane me-1"></i>حفظ وتقديم للمراجعة
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function purchaseRequestForm() {
    return {
        lines: [
            { material_id: '', fabric_color_id: '', requested_quantity: 1 }
        ],
        addLine() {
            this.lines.push({ material_id: '', fabric_color_id: '', requested_quantity: 1 });
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
