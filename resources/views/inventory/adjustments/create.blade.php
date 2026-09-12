@extends('layouts.app')

@section('title', 'إنشاء تسوية مخزنية جديدة')

@section('content')
<div class="container-fluid px-4 py-3" x-data="inventoryAdjustmentForm()">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.adjustments.index') }}" class="text-decoration-none">تسويات المخزون</a></li>
                    <li class="breadcrumb-item active" aria-current="page">تسوية جديدة</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">إنشاء تسوية مخزنية جديدة (Stock Adjustment)</h1>
        </div>
        <div>
            <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary">
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

    <form action="{{ route('inventory.adjustments.store') }}" method="POST">
        @csrf

        <!-- Header Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-sliders-h text-primary me-2"></i>بيانات التسوية الأساسية</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="warehouse_id" class="form-label fw-semibold">المستودع المستهدف <span class="text-danger">*</span></label>
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
                        <label for="reason_id" class="form-label fw-semibold">سبب التسوية / الجرد <span class="text-danger">*</span></label>
                        <select name="reason_id" id="reason_id" class="form-select @error('reason_id') is-invalid @enderror" required>
                            <option value="">-- اختر سبب التسوية --</option>
                            @foreach($reasons as $reason)
                                <option value="{{ $reason->id }}" {{ old('reason_id') == $reason->id ? 'selected' : '' }}>
                                    {{ $reason->name_ar }} ({{ $reason->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('reason_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="adjustment_date" class="form-label fw-semibold">تاريخ التسوية <span class="text-danger">*</span></label>
                        <input type="date" name="adjustment_date" id="adjustment_date" class="form-control @error('adjustment_date') is-invalid @enderror" value="{{ old('adjustment_date', date('Y-m-d')) }}" required>
                        @error('adjustment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label fw-semibold">تفاصيل وملاحظات عملية التسوية والجرد</label>
                        <input type="text" name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}" placeholder="أدخل تفاصيل التلف، الهدر، أو الفروقات الجردية المستكشفة...">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i>بنود التسويات (إضافة أو خفض كميات)</h5>
                <button type="button" @click="addLine()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> إضافة بند تسوية
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 15%;">نوع التسوية <span class="text-danger">*</span></th>
                                <th style="width: 35%;">الدفعة / المادة الخاملة <span class="text-danger">*</span></th>
                                <th style="width: 15%;">الكمية <span class="text-danger">*</span></th>
                                <th style="width: 15%;">تكلفة الوحدة (عند الزيادة)</th>
                                <th style="width: 15%;">ملاحظات البند</th>
                                <th style="width: 5%;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td>
                                        <select :name="`items[${index}][adjustment_type]`" x-model="line.adjustment_type" class="form-select form-select-sm" required>
                                            <option value="ADJUSTMENT_OUT">تخفيض / عجز (ADJUSTMENT_OUT)</option>
                                            <option value="ADJUSTMENT_IN">إضافة / فائض (ADJUSTMENT_IN)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <template x-if="line.adjustment_type === 'ADJUSTMENT_OUT'">
                                            <select :name="`items[${index}][lot_id]`" x-model="line.lot_id" class="form-select form-select-sm" required>
                                                <option value="">-- اختر الدفعة المراد تخفيضها --</option>
                                                @foreach($lots as $lot)
                                                    <option value="{{ $lot->id }}">
                                                        {{ $lot->lot_code }} | {{ $lot->material->name_ar }} (متاح: {{ number_format($lot->remaining_quantity, 2) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </template>
                                        <template x-if="line.adjustment_type === 'ADJUSTMENT_IN'">
                                            <select :name="`items[${index}][lot_id]`" x-model="line.lot_id" class="form-select form-select-sm">
                                                <option value="">-- إنشاء دفعة جديدة تلقائياً --</option>
                                                @foreach($lots as $lot)
                                                    <option value="{{ $lot->id }}">
                                                        زيادة بالدفعة: {{ $lot->lot_code }} ({{ $lot->material->name_ar }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </template>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" :name="`items[${index}][quantity]`" x-model.number="line.quantity" class="form-control form-control-sm fw-mono text-center" min="0.0001" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.000001" :name="`items[${index}][unit_cost]`" x-model.number="line.unit_cost" class="form-control form-control-sm fw-mono text-end" min="0" :disabled="line.adjustment_type === 'ADJUSTMENT_OUT'">
                                    </td>
                                    <td>
                                        <input type="text" :name="`items[${index}][notes]`" x-model="line.notes" class="form-control form-control-sm" placeholder="سبب البند...">
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
            <button type="submit" name="action" value="post" class="btn btn-danger px-4">
                <i class="fas fa-check-circle me-1"></i> حفظ وترحيل وتعديل المخزون فوراً (Post)
            </button>
        </div>

    </form>
</div>

<script>
function inventoryAdjustmentForm() {
    return {
        lines: [
            { adjustment_type: 'ADJUSTMENT_OUT', lot_id: '', quantity: 1, unit_cost: 0, notes: '' }
        ],
        addLine() {
            this.lines.push({ adjustment_type: 'ADJUSTMENT_OUT', lot_id: '', quantity: 1, unit_cost: 0, notes: '' });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        }
    }
}
</script>
@endsection
