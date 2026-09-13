@extends('layouts.app')

@section('title', 'إصدار أمر شراء جديد')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus-circle me-2 text-primary"></i>إصدار أمر شراء تجاري (Purchase Order)
            </h1>
            <p class="text-muted small mb-0">تحديد المورد وتثبيت بنود خامات أمر الشراء والأسعار التجارية المعتمدة</p>
        </div>
        <a href="{{ route('purchasing.orders.index') }}" class="btn btn-outline-secondary rounded-pill px-4">إلغاء والعودة</a>
    </div>

    <form action="{{ route('purchasing.orders.store') }}" method="POST" x-data="purchaseOrderForm()">
        @csrf
        @if($purchaseRequest)<input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">@endif
        @if($supplierQuotation)<input type="hidden" name="supplier_quotation_id" value="{{ $supplierQuotation->id }}">@endif

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark">البيانات التجارية والشحن</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">المورد *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">اختر المورد...</option>
                            @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ ($supplierQuotation && $supplierQuotation->supplier_id == $sup->id) ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">المستودع المستهدف لاستلام الشحنة *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">اختر المستودع...</option>
                            @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ ($purchaseRequest && $purchaseRequest->warehouse_id == $w->id) ? 'selected' : '' }}>
                                {{ $w->name_ar }} ({{ $w->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">تاريخ أمر الشراء *</label>
                        <input type="date" name="order_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">تاريخ التسليم المتوقع للمصنع</label>
                        <input type="date" name="expected_delivery_date" class="form-control" value="{{ now()->addDays(7)->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">الرقم المرجعي عند المورد</label>
                        <input type="text" name="supplier_reference" class="form-control" value="{{ $supplierQuotation?->supplier_reference }}" placeholder="رقم العقد أو العرض...">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">شروط الدفع والتسليم</label>
                        <input type="text" name="payment_terms" class="form-control" value="{{ $supplierQuotation?->payment_terms }}" placeholder="نقداً عند الاستلام...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lines -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">بنود أمر الشراء والأسعار المعتمدة</h6>
                <button type="button" @click="addLine()" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i>إضافة بند خامة
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light small">
                        <tr>
                            <th width="25%">المادة / الخامة *</th>
                            <th width="15%">وحدة الشراء للمورد *</th>
                            <th width="15%">معامل التحويل *</th>
                            <th width="15%">الكمية المطلوبة *</th>
                            <th width="15%">سعر الوحدة التجاري (SAR) *</th>
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
                                        <option value="">الوحدة...</option>
                                        @foreach($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][conversion_factor]'" class="form-control form-control-sm" x-model="line.conversion_factor" required>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][ordered_quantity]'" class="form-control form-control-sm" x-model="line.ordered_quantity" required>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" :name="'lines['+index+'][unit_price]'" class="form-control form-control-sm" x-model="line.unit_price" required>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark" x-text="(line.ordered_quantity * line.unit_price).toFixed(2)"></span> SAR
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
                @can('purchasing.approve_po')
                <button type="submit" name="action" value="approve" class="btn btn-success rounded-pill px-5 fw-bold">
                    <i class="fas fa-check-circle me-1"></i>حفظ واعتماد أمر الشراء مباشرة
                </button>
                @endcan
            </div>
        </div>
    </form>
</div>

<script>
function purchaseOrderForm() {
    return {
        lines: [
            @if($supplierQuotation)
                @foreach($supplierQuotation->lines as $line)
                { material_id: '{{ $line->material_id }}', purchase_unit_id: '{{ $line->purchase_unit_id }}', conversion_factor: {{ $line->conversion_factor }}, ordered_quantity: {{ $line->quoted_quantity }}, unit_price: {{ $line->unit_price }} },
                @endforeach
            @elseif($purchaseRequest)
                @foreach($purchaseRequest->lines as $line)
                { material_id: '{{ $line->material_id }}', purchase_unit_id: '{{ $line->preferred_purchase_unit_id ?? $line->base_unit_id }}', conversion_factor: 1, ordered_quantity: {{ $line->requested_quantity }}, unit_price: 0 },
                @endforeach
            @else
                { material_id: '', purchase_unit_id: '', conversion_factor: 1, ordered_quantity: 1, unit_price: 0 }
            @endif
        ],
        addLine() {
            this.lines.push({ material_id: '', purchase_unit_id: '', conversion_factor: 1, ordered_quantity: 1, unit_price: 0 });
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
