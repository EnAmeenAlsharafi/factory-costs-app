@extends('layouts.app')

@section('title', 'إنشاء سند استلام مواد جديد')

@section('content')
<div class="container-fluid px-4 py-3" x-data="materialReceiptForm()">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.index') }}" class="text-decoration-none">سندات الاستلام</a></li>
                    <li class="breadcrumb-item active" aria-current="page">سند جديد</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">إنشاء سند استلام مواد خام (Material Receipt)</h1>
        </div>
        <div>
            <a href="{{ route('inventory.receipts.index') }}" class="btn btn-outline-secondary">
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

    <form action="{{ route('inventory.receipts.store') }}" method="POST">
        @csrf

        <!-- Header Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-file-invoice text-primary me-2"></i>بيانات السند الأساسية</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="supplier_id" class="form-label fw-semibold">المورد <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">-- اختر المورد --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }} ({{ $supplier->supplier_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="warehouse_id" class="form-label fw-semibold">مستودع الاستلام <span class="text-danger">*</span></label>
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
                        <label for="receipt_date" class="form-label fw-semibold">تاريخ الاستلام <span class="text-danger">*</span></label>
                        <input type="date" name="receipt_date" id="receipt_date" class="form-control @error('receipt_date') is-invalid @enderror" value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                        @error('receipt_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="supplier_invoice_number" class="form-label fw-semibold">رقم فاتورة / شحنة المورد</label>
                        <input type="text" name="supplier_invoice_number" id="supplier_invoice_number" class="form-control @error('supplier_invoice_number') is-invalid @enderror" value="{{ old('supplier_invoice_number') }}" placeholder="مثال: INV-99081">
                        @error('supplier_invoice_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="notes" class="form-label fw-semibold">ملاحظات السند</label>
                        <input type="text" name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}" placeholder="أي ملاحظات إضافية...">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i>بنود المواد المستلمة</h5>
                <button type="button" @click="addLine()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> إضافة مادة
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 22%;">المادة الخام <span class="text-danger">*</span></th>
                                <th style="width: 14%;">رقم / كود اللون</th>
                                <th style="width: 11%;">الكمية <span class="text-danger">*</span></th>
                                <th style="width: 13%;">وحدة القياس <span class="text-danger">*</span></th>
                                <th style="width: 12%;">سعر الوحدة (ر.س) <span class="text-danger">*</span></th>
                                <th style="width: 13%;">رقم الدفعة (Lot)</th>
                                <th style="width: 11%;">الإجمالي</th>
                                <th style="width: 4%;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td>
                                        <select :name="`items[${index}][material_id]`" x-model="line.material_id" @change="onMaterialChange(index)" class="form-select form-select-sm" required>
                                            <option value="">-- اختر المادة --</option>
                                            @foreach($materials as $mat)
                                                <option value="{{ $mat->id }}" 
                                                        data-base-unit="{{ $mat->base_unit_id }}" 
                                                        data-purchase-unit="{{ $mat->purchase_unit_id ?? $mat->base_unit_id }}"
                                                        data-is-fabric="{{ (strtoupper($mat->category?->code ?? '') === 'FABRIC') ? '1' : '0' }}"
                                                        data-colors="{{ json_encode($mat->fabricColors->pluck('color_code')->toArray()) }}">
                                                    {{ $mat->name_ar }} ({{ $mat->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <div x-show="line.is_fabric">
                                            <input type="text" 
                                                   :name="`items[${index}][fabric_color_code]`" 
                                                   x-model="line.fabric_color_code" 
                                                   :list="`colors-list-${index}`"
                                                   class="form-control form-control-sm fw-mono border-primary" 
                                                   placeholder="مثال: 204" 
                                                   :required="line.is_fabric">
                                            <datalist :id="`colors-list-${index}`">
                                                <template x-for="col in line.available_colors" :key="col">
                                                    <option :value="col"></option>
                                                </template>
                                            </datalist>
                                        </div>
                                        <div x-show="!line.is_fabric" class="text-center text-muted fs-7">
                                            <span class="badge bg-light text-muted border-0">—</span>
                                        </div>
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
                                    <td>
                                        <input type="number" step="0.000001" :name="`items[${index}][unit_cost]`" x-model.number="line.unit_cost" class="form-control form-control-sm fw-mono text-end" min="0" required>
                                    </td>
                                    <td>
                                        <input type="text" :name="`items[${index}][lot_reference]`" x-model="line.lot_reference" class="form-control form-control-sm fw-mono" placeholder="تلقائي إن ترك فارغاً">
                                    </td>
                                    <td class="text-end fw-mono fw-bold">
                                        <span x-text="formatNumber(line.quantity * line.unit_cost)"></span> ر.س
                                    </td>
                                    <td class="text-center">
                                        <button type="button" @click="removeLine(index)" class="btn btn-sm btn-outline-danger border-0" :disabled="lines.length === 1">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="6" class="text-end fw-bold">إجمالي المبلغ:</td>
                                <td class="text-end fw-mono fw-bold text-primary fs-6">
                                    <span x-text="formatNumber(calculateTotal())"></span> ر.س
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
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
function materialReceiptForm() {
    return {
        lines: [
            { material_id: '', fabric_color_code: '', is_fabric: false, available_colors: [], quantity: 1, unit_id: '', unit_cost: 0, lot_reference: '' }
        ],
        addLine() {
            this.lines.push({ material_id: '', fabric_color_code: '', is_fabric: false, available_colors: [], quantity: 1, unit_id: '', unit_cost: 0, lot_reference: '' });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        onMaterialChange(index) {
            const selectEl = event.target;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            if (selectedOption && selectedOption.dataset) {
                const purchaseUnit = selectedOption.dataset.purchaseUnit || selectedOption.dataset.baseUnit;
                if (purchaseUnit) {
                    this.lines[index].unit_id = purchaseUnit;
                }
                const isFabric = selectedOption.dataset.isFabric === '1';
                this.lines[index].is_fabric = isFabric;
                try {
                    this.lines[index].available_colors = JSON.parse(selectedOption.dataset.colors || '[]');
                } catch (e) {
                    this.lines[index].available_colors = [];
                }
                if (!isFabric) {
                    this.lines[index].fabric_color_code = '';
                }
            } else {
                this.lines[index].is_fabric = false;
                this.lines[index].available_colors = [];
                this.lines[index].fabric_color_code = '';
            }
        },
        calculateTotal() {
            return this.lines.reduce((sum, line) => {
                const qty = parseFloat(line.quantity) || 0;
                const cost = parseFloat(line.unit_cost) || 0;
                return sum + (qty * cost);
            }, 0);
        },
        formatNumber(val) {
            return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
@endsection
