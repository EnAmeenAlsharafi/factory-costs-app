@extends('layouts.app')

@section('title', 'إنشاء سند إرجاع مواد للمخزن')

@section('content')
<div class="container-fluid px-4 py-3" x-data="materialReturnForm()">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.returns.index') }}" class="text-decoration-none">سندات الإرجاع</a></li>
                    <li class="breadcrumb-item active" aria-current="page">سند جديد</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">إنشاء سند إرجاع مواد للمخزن (Material Return)</h1>
        </div>
        <div>
            <a href="{{ route('inventory.returns.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i> إلغاء
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <form action="{{ route('inventory.returns.store') }}" method="POST">
        @csrf

        <!-- Header Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-undo text-primary me-2"></i>بيانات السند الأساسية</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="warehouse_id" class="form-label fw-semibold">المستودع المستقبل للمواد <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name_ar }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="department_id" class="form-label fw-semibold">القسم المرجع <span class="text-danger">*</span></label>
                        <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                            <option value="">-- اختر القسم --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name_ar }} ({{ $dept->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="return_date" class="form-label fw-semibold">تاريخ الإرجاع <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" id="return_date" class="form-control @error('return_date') is-invalid @enderror" value="{{ old('return_date', date('Y-m-d')) }}" required>
                        @error('return_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="issue_id" class="form-label fw-semibold">مرجع سند الصرف الأصلي (اختياري)</label>
                        <select name="issue_id" id="issue_id" class="form-select @error('issue_id') is-invalid @enderror">
                            <option value="">-- اختياري: غير مرتبط بسند معين --</option>
                            @foreach($issueLines as $issueLine)
                                <option value="{{ $issueLine->id }}" {{ old('issue_line_id') == $issueLine->id ? 'selected' : '' }}>
                                    {{ $issueLine->issue->issue_number }} | {{ $issueLine->material->name_ar }} (المصروف: {{ $issueLine->issued_quantity }})
                                </option>
                            @endforeach
                        </select>
                        @error('issue_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="notes" class="form-label fw-semibold">سبب / ملاحظات الإرجاع</label>
                        <input type="text" name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}" placeholder="أدخل سبب إرجاع المواد المخزنية...">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i>بنود المواد المرجعة للدفعات الأصيلة</h5>
                <button type="button" @click="addLine()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> إضافة بند إرجاع
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 40%;">الدفعة الأصيلة والمادة الخام <span class="text-danger">*</span></th>
                                <th style="width: 15%;">الكمية المرجعة <span class="text-danger">*</span></th>
                                <th style="width: 20%;">وحدة القياس <span class="text-danger">*</span></th>
                                <th style="width: 20%;">تكلفة الوحدة المسجلة بالدفعة</th>
                                <th style="width: 5%;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td>
                                        <select :name="`items[${index}][lot_id]`" x-model="line.lot_id" @change="onLotChange(index)" class="form-select form-select-sm" required>
                                            <option value="">-- اختر الدفعة المراد زيادة رصيدها --</option>
                                            @foreach($lots as $lot)
                                                <option value="{{ $lot->id }}" data-unit-cost="@can('costing.view'){{ $lot->unit_cost }}@else{{ 0 }}@endcan" data-unit-id="{{ $lot->material->base_unit_id }}">
                                                    {{ $lot->lot_code }} | {{ $lot->material->name_ar }} @can('costing.view')(تكلفة: {{ number_format($lot->unit_cost, 2) }} ر.س)@endcan
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" :name="`items[${index}][quantity]`" x-model.number="line.quantity" class="form-control form-control-sm fw-mono text-center" min="0.0001" required>
                                    </td>
                                    <td>
                                        <select :name="`items[${index}][unit_id]`" x-model="line.unit_id" class="form-select form-select-sm" required>
                                            <option value="">-- اختر الوحدة --</option>
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name_ar }} ({{ $unit->code }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-end fw-mono text-muted">
                                        <span x-text="formatNumber(line.unit_cost)"></span> ر.س
                                    </td>
                                    <td class="text-center">
                                        <button type="button" @click="removeLine(index)" class="btn btn-sm btn-outline-danger border-0" :disabled="lines.length === 1">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Submission Actions -->
        <div class="d-flex justify-content-end gap-3 mb-5">
            <button type="submit" name="action" value="draft" class="btn btn-outline-primary px-4">
                <i class="fas fa-save me-1"></i> حفظ كـ مسودة (Draft)
            </button>
            <button type="submit" name="action" value="post" class="btn btn-success px-4">
                <i class="fas fa-check-circle me-1"></i> حفظ وترحيل للمخزون فوراً (Post)
            </button>
        </div>

    </form>
</div>

<script>
function materialReturnForm() {
    return {
        lines: [
            { lot_id: '', quantity: 1, unit_id: '', unit_cost: 0 }
        ],
        addLine() {
            this.lines.push({ lot_id: '', quantity: 1, unit_id: '', unit_cost: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        onLotChange(index) {
            const selectEl = event.target;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            if (selectedOption && selectedOption.dataset) {
                const unitCost = parseFloat(selectedOption.dataset.unitCost) || 0;
                const unitId = selectedOption.dataset.unitId || '';

                this.lines[index].unit_cost = unitCost;
                this.lines[index].unit_id = unitId;
            } else {
                this.lines[index].unit_cost = 0;
            }
        },
        formatNumber(val) {
            return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
@endsection
