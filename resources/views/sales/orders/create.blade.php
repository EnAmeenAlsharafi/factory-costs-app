@extends('layouts.app')

@section('title', 'إنشاء طلب عميل جديد')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">إنشاء طلب عميل جديد</h1>
            <p class="text-muted mb-0 fs-7">إدخال طلب بيع تجاري وتوصيف البنود والمواصفات المطلوبة</p>
        </div>
        <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة لطلبات العملاء
        </a>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-1"></i> يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('sales.orders.store') }}" method="POST" id="orderForm">
        @csrf

        {{-- Header Information Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-header bg-light py-3">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-info-circle text-primary me-2"></i> بيانات الطلب الأساسية</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">العميل <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">-- اختر العميل --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }} ({{ $customer->phone }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">قناة البيع <span class="text-danger">*</span></label>
                        <select name="sales_channel_id" class="form-select @error('sales_channel_id') is-invalid @enderror" required>
                            <option value="">-- اختر قناة البيع --</option>
                            @foreach($salesChannels as $channel)
                                <option value="{{ $channel->id }}" {{ old('sales_channel_id') == $channel->id ? 'selected' : '' }}>
                                    {{ $channel->name_ar }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">رقم أمر شراء العميل (PO)</label>
                        <input type="text" name="customer_po_number" class="form-control @error('customer_po_number') is-invalid @enderror" value="{{ old('customer_po_number') }}" placeholder="مثال: PO-9982">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ الطلب <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control @error('order_date') is-invalid @enderror" value="{{ old('order_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ التسليم المتوقع</label>
                        <input type="date" name="promised_delivery_date" class="form-control @error('promised_delivery_date') is-invalid @enderror" value="{{ old('promised_delivery_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">ملاحظات ووصايا الطلب</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="أدخل أي تعليمات تسليم أو ملاحظات خاصة بالطلب...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Order Lines Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-list text-primary me-2"></i> بنود طلب العميل</h5>
                <button type="button" class="btn btn-sm btn-success" id="add-line-btn">
                    <i class="fas fa-plus me-1"></i> إضافة بند جديد
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="lines-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="min-width: 200px;">الموديل / والتصميم</th>
                                <th style="min-width: 180px;">التكوين / المقاس المطلوب (عرض × طول)</th>
                                <th style="width: 100px;">الكمية</th>
                                <th style="width: 140px;">سعر الوحدة (ر.س)</th>
                                <th style="width: 140px;">المجموع</th>
                                <th style="min-width: 150px;">ملاحظات البند</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="lines-container">
                            {{-- Lines inserted dynamically via JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <div class="fw-bold text-dark fs-6">
                    عدد البنود: <span id="line-count">0</span>
                </div>
                <div class="fw-bold text-primary fs-5">
                    إجمالي مبلغ الطلب: <span id="grand-total">0.00</span> ر.س
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('sales.orders.index') }}" class="btn btn-light px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-5 fw-bold">
                <i class="fas fa-save me-1"></i> حفظ الطلب كمسودة
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const linesContainer = document.getElementById('lines-container');
    const addLineBtn = document.getElementById('add-line-btn');
    const lineCountSpan = document.getElementById('line-count');
    const grandTotalSpan = document.getElementById('grand-total');

    const productModels = @json($productModels);

    let lineIndex = 0;

    function addLine(data = {}) {
        lineIndex++;
        const tr = document.createElement('tr');
        tr.dataset.index = lineIndex;

        let modelsOptions = `<option value="">-- تصميم خاص / بدون موديل --</option>`;
        productModels.forEach(m => {
            modelsOptions += `<option value="${m.id}">${m.name_ar} (${m.model_code})</option>`;
        });

        tr.innerHTML = `
            <td class="text-center font-monospace line-num"></td>
            <td>
                <select name="lines[${lineIndex}][product_model_id]" class="form-select form-select-sm model-select mb-1">
                    ${modelsOptions}
                </select>
                <div class="form-check form-check-inline">
                    <input class="form-check-input custom-checkbox" type="checkbox" name="lines[${lineIndex}][custom_design]" value="1" id="custom_${lineIndex}">
                    <label class="form-check-label fs-8" for="custom_${lineIndex}">تصميم خاص حسب الطلب</label>
                </div>
                <input type="text" name="lines[${lineIndex}][custom_design_name]" class="form-control form-control-sm custom-name-input d-none mt-1" placeholder="اسم التصميم الخاص...">
            </td>
            <td>
                <select name="lines[${lineIndex}][product_configuration_id]" class="form-select form-select-sm config-select mb-1">
                    <option value="">-- مقاس غير محدد --</option>
                </select>
                <div class="row g-1">
                    <div class="col-6">
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_width_cm]" class="form-control form-control-sm" placeholder="العرض سم">
                    </div>
                    <div class="col-6">
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_length_cm]" class="form-control form-control-sm" placeholder="الطول سم">
                    </div>
                </div>
            </td>
            <td>
                <input type="number" min="1" name="lines[${lineIndex}][quantity]" class="form-control form-control-sm qty-input" value="${data.quantity || 1}" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="lines[${lineIndex}][unit_price]" class="form-control form-control-sm price-input" value="${data.unit_price || 0.00}" required>
            </td>
            <td class="fw-bold text-dark text-end line-total">
                0.00 ر.س
            </td>
            <td>
                <input type="text" name="lines[${lineIndex}][notes]" class="form-control form-control-sm" placeholder="أي تفاصيل إضافية...">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn"><i class="fas fa-trash"></i></button>
            </td>
        `;

        linesContainer.appendChild(tr);

        const modelSelect = tr.querySelector('.model-select');
        const configSelect = tr.querySelector('.config-select');
        const customCheck = tr.querySelector('.custom-checkbox');
        const customNameInput = tr.querySelector('.custom-name-input');
        const qtyInput = tr.querySelector('.qty-input');
        const priceInput = tr.querySelector('.price-input');
        const removeBtn = tr.querySelector('.remove-line-btn');

        modelSelect.addEventListener('change', function () {
            const selectedModelId = parseInt(this.value);
            configSelect.innerHTML = '<option value="">-- مقاس غير محدد --</option>';
            if (selectedModelId) {
                const model = productModels.find(m => m.id === selectedModelId);
                if (model && model.configurations) {
                    model.configurations.forEach(cfg => {
                        configSelect.innerHTML += `<option value="${cfg.id}">${cfg.width_cm} × ${cfg.length_cm} سم ${cfg.has_storage ? '(سحارة)' : ''}</option>`;
                    });
                }
            }
        });

        customCheck.addEventListener('change', function () {
            if (this.checked) {
                customNameInput.classList.remove('d-none');
            } else {
                customNameInput.classList.add('d-none');
                customNameInput.value = '';
            }
        });

        function updateLineTotal() {
            const q = parseFloat(qtyInput.value) || 0;
            const p = parseFloat(priceInput.value) || 0;
            const total = q * p;
            tr.querySelector('.line-total').textContent = total.toFixed(2) + ' ر.س';
            updateGrandTotal();
        }

        qtyInput.addEventListener('input', updateLineTotal);
        priceInput.addEventListener('input', updateLineTotal);

        removeBtn.addEventListener('click', function () {
            if (linesContainer.children.length > 1) {
                tr.remove();
                renumberLines();
                updateGrandTotal();
            } else {
                alert('يجب إبقاء بند واحد على الأقل في الطلب.');
            }
        });

        renumberLines();
        updateLineTotal();
    }

    function renumberLines() {
        const rows = linesContainer.querySelectorAll('tr');
        rows.forEach((row, idx) => {
            row.querySelector('.line-num').textContent = idx + 1;
        });
        lineCountSpan.textContent = rows.length;
    }

    function updateGrandTotal() {
        let total = 0;
        const rows = linesContainer.querySelectorAll('tr');
        rows.forEach(row => {
            const q = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const p = parseFloat(row.querySelector('.price-input')?.value) || 0;
            total += (q * p);
        });
        grandTotalSpan.textContent = total.toFixed(2);
    }

    addLineBtn.addEventListener('click', () => addLine());

    addLine();
});
</script>
@endsection
