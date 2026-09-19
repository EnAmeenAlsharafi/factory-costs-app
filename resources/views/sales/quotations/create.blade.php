@extends('layouts.app')

@section('title', 'إنشاء عرض سعر جديد')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">إنشاء عرض سعر جديد</h1>
            <p class="text-muted mb-0 fs-7">إدخال بيانات عرض السعر التجاري والبنود المطلوبة من العميل</p>
        </div>
        <a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة لعروض الأسعار
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

    <form action="{{ route('sales.quotations.store') }}" method="POST" id="quotationForm">
        @csrf

        {{-- Header Information Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-header bg-light py-3">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-info-circle text-primary me-2"></i> بيانات العرض الأساسية</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
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

                    <div class="col-md-4">
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
                        <label class="form-label fw-semibold">تاريخ الإصدار <span class="text-danger">*</span></label>
                        <input type="date" name="quotation_date" class="form-control @error('quotation_date') is-invalid @enderror" value="{{ old('quotation_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ الصلاحية</label>
                        <input type="date" name="valid_until" class="form-control @error('valid_until') is-invalid @enderror" value="{{ old('valid_until') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">ملاحظات وشروط العرض</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="أدخل أي شروط أو ملاحظات خاصة بعرض السعر...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quotation Lines Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-list text-primary me-2"></i> بنود عرض السعر</h5>
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
                    إجمالي عرض السعر: <span id="grand-total">0.00</span> ر.س
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('sales.quotations.index') }}" class="btn btn-light px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-5 fw-bold">
                <i class="fas fa-save me-1"></i> حفظ عرض السعر
            </button>
        </div>
    </form>
</div>

@php
$rawOldLines = old('lines', []);
$oldLinesData = [];
if (!empty($rawOldLines)) {
    $modelIds = collect($rawOldLines)->pluck('product_model_id')->filter()->unique()->all();
    $models = !empty($modelIds) ? \App\Models\ProductModel::whereIn('id', $modelIds)->get()->keyBy('id') : collect();

    $configIds = collect($rawOldLines)->pluck('product_configuration_id')->filter()->unique()->all();
    $configs = !empty($configIds) ? \App\Models\ProductConfiguration::whereIn('id', $configIds)->get()->keyBy('id') : collect();

    foreach ($rawOldLines as $line) {
        $mid = $line['product_model_id'] ?? null;
        $cid = $line['product_configuration_id'] ?? null;

        $m = $mid ? $models->get($mid) : null;
        $c = $cid ? $configs->get($cid) : null;

        $storageLabel = ($c && $c->has_storage) ? 'بتخزين' : 'بدون تخزين';
        $cLabel = $c ? "{$c->width_cm}×{$c->length_cm} — {$storageLabel}" : '';

        $oldLinesData[] = array_merge($line, [
            'product_model_label' => $line['product_model_label'] ?? ($m ? $m->name_ar : ''),
            'product_configuration_label' => $line['product_configuration_label'] ?? $cLabel,
        ]);
    }
}
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {
    const linesContainer = document.getElementById('lines-container');
    const addLineBtn = document.getElementById('add-line-btn');
    const lineCountSpan = document.getElementById('line-count');
    const grandTotalSpan = document.getElementById('grand-total');

    const oldLines = {!! json_encode($oldLinesData, JSON_UNESCAPED_UNICODE) !!};

    let lineIndex = 0;

    function loadConfigurationsForModel(tr, modelId, selectedConfigId = null) {
        const configSelect = tr.querySelector('.config-select');
        const configLabelInp = tr.querySelector('.config-label-input');
        const widthInp = tr.querySelector('.width-input');
        const lengthInp = tr.querySelector('.length-input');
        if (!configSelect) return;

        configSelect.innerHTML = '<option value="">اختر المقاس...</option>';
        if (!modelId) {
            configSelect.disabled = true;
            if (configLabelInp) configLabelInp.value = '';
            return;
        }

        configSelect.disabled = true;
        configSelect.innerHTML = '<option value="">جاري تحميل المقاسات...</option>';

        fetch(`/api/search/product-configurations?model_id=${encodeURIComponent(modelId)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(configs => {
            configSelect.innerHTML = '<option value="">اختر المقاس...</option>';
            configSelect.disabled = false;
            let matched = false;
            configs.forEach(cfg => {
                const opt = document.createElement('option');
                opt.value = cfg.id;
                opt.textContent = cfg.label + (cfg.code ? ` — ${cfg.code}` : '');
                opt.dataset.width = cfg.width_cm;
                opt.dataset.length = cfg.length_cm;
                opt.dataset.label = cfg.label;
                if (selectedConfigId && String(cfg.id) === String(selectedConfigId)) {
                    opt.selected = true;
                    matched = true;
                    if (configLabelInp) configLabelInp.value = cfg.label;
                    if (widthInp && !widthInp.value) widthInp.value = cfg.width_cm;
                    if (lengthInp && !lengthInp.value) lengthInp.value = cfg.length_cm;
                }
                configSelect.appendChild(opt);
            });

            if (!matched && selectedConfigId) {
                configSelect.value = selectedConfigId;
            }
        })
        .catch(err => {
            console.error('Failed to load configurations', err);
            configSelect.innerHTML = '<option value="">تعذر تحميل المقاسات</option>';
            configSelect.disabled = false;
        });
    }

    function addLine(data = {}) {
        lineIndex++;
        const tr = document.createElement('tr');
        tr.dataset.index = lineIndex;

        const modelId = data.product_model_id || '';
        const modelLabel = data.product_model_label || '';
        const configId = data.product_configuration_id || '';
        const configLabel = data.product_configuration_label || '';
        const isCustom = data.custom_design ? 'checked' : '';
        const customName = data.custom_design_name || '';
        const customNameDisplay = data.custom_design ? '' : 'd-none';

        tr.innerHTML = `
            <td class="text-center font-monospace line-num text-muted"></td>
            <td>
                <div class="typeahead-container model-wrapper mb-1" @click.outside="closeDropdown" x-data="typeaheadSelect({
                    name: 'lines[${lineIndex}][product_model_id]',
                    endpoint: '/api/search/product-models',
                    placeholder: 'ابحث عن الموديل (كود / اسم)...',
                    initialId: '${modelId}',
                    initialLabel: '${modelLabel.replace(/'/g, "\\'")}'
                })">
                    <input type="hidden" name="lines[${lineIndex}][product_model_id]" :value="selectedId" class="model-id-input">
                    <input type="hidden" name="lines[${lineIndex}][product_model_label]" :value="selectedLabel" class="model-label-input">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm"
                               x-ref="inputBox"
                               x-model="searchQuery"
                               @focus="onFocus"
                               @input.debounce.250ms="onInput"
                               @keydown.arrow-down.prevent="navigateDown"
                               @keydown.arrow-up.prevent="navigateUp"
                               @keydown.enter.prevent.stop="selectHighlighted"
                               @keydown.escape="closeDropdown"
                               placeholder="ابحث عن الموديل (كود / اسم)...">
                        <button class="btn btn-outline-secondary btn-sm" type="button" x-show="selectedId" @click="clearSelection" tabindex="-1">
                            <i class="fas fa-times fs-8"></i>
                        </button>
                    </div>
                    <div class="typeahead-dropdown" x-show="isOpen" :style="dropdownStyle" @mousedown.prevent x-cloak>
                        <div x-show="loading" class="p-2 text-center text-muted fs-8">
                            <i class="fas fa-spinner fa-spin me-1"></i> جاري البحث...
                        </div>
                        <template x-for="(item, index) in results" :key="item.id">
                            <div class="typeahead-item"
                                 :class="{ 'active': index === highlightedIndex }"
                                 @mousedown.prevent="selectItem(item)">
                                <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                                    <span class="text-truncate fw-medium" dir="rtl" x-text="item.label"></span>
                                    <span class="code-badge text-nowrap" dir="ltr" x-text="item.code"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="!loading && hasSearched && results.length === 0" class="p-2 text-center text-muted fs-8">
                            لا توجد نتائج مطابقة
                        </div>
                        <div x-show="!loading && !hasSearched && results.length === 0" class="p-2 text-center text-muted fs-8">
                            ابدأ بالكتابة للبحث...
                        </div>
                    </div>
                </div>

                <div class="form-check form-check-inline mt-1">
                    <input class="form-check-input custom-checkbox" type="checkbox" name="lines[${lineIndex}][custom_design]" value="1" id="custom_${lineIndex}" ${isCustom}>
                    <label class="form-check-label fs-8 text-secondary" for="custom_${lineIndex}">تصميم خاص حسب الطلب</label>
                </div>
                <input type="text" name="lines[${lineIndex}][custom_design_name]" class="form-control form-control-sm custom-name-input ${customNameDisplay} mt-1" value="${customName.replace(/"/g, '&quot;')}" placeholder="اسم التصميم الخاص...">
            </td>
            <td>
                <select name="lines[${lineIndex}][product_configuration_id]" class="form-select form-select-sm config-select mb-1" ${!modelId ? 'disabled' : ''}>
                    <option value="">اختر المقاس...</option>
                    ${configId ? `<option value="${configId}" selected>${configLabel || 'المقاس المختار'}</option>` : ''}
                </select>
                <input type="hidden" name="lines[${lineIndex}][product_configuration_label]" class="config-label-input" value="${configLabel.replace(/"/g, '&quot;')}">

                <div class="row g-1">
                    <div class="col-6">
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_width_cm]" class="form-control form-control-sm width-input" value="${data.requested_width_cm || ''}" placeholder="العرض سم">
                    </div>
                    <div class="col-6">
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_length_cm]" class="form-control form-control-sm length-input" value="${data.requested_length_cm || ''}" placeholder="الطول سم">
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
                <input type="text" name="lines[${lineIndex}][notes]" class="form-control form-control-sm" value="${(data.notes || '').replace(/"/g, '&quot;')}" placeholder="أي تفاصيل إضافية...">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn" title="حذف البند"><i class="fas fa-trash"></i></button>
            </td>
        `;

        linesContainer.appendChild(tr);

        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(tr);
        }

        const configSelect = tr.querySelector('.config-select');
        const configLabelInp = tr.querySelector('.config-label-input');
        const widthInput = tr.querySelector('.width-input');
        const lengthInput = tr.querySelector('.length-input');
        const customCheck = tr.querySelector('.custom-checkbox');
        const customNameInput = tr.querySelector('.custom-name-input');
        const qtyInput = tr.querySelector('.qty-input');
        const priceInput = tr.querySelector('.price-input');
        const removeBtn = tr.querySelector('.remove-line-btn');

        // Clean event delegation for typeahead selection changes
        tr.addEventListener('typeahead-select', function (e) {
            const container = e.target.closest('.typeahead-container');
            const item = e.detail?.item;
            if (!container || !item) return;

            if (container.classList.contains('model-wrapper')) {
                const modelLabelInp = tr.querySelector('.model-label-input');
                if (modelLabelInp) modelLabelInp.value = item.label || '';
                loadConfigurationsForModel(tr, item.id);
            }
        });

        tr.addEventListener('typeahead-clear', function (e) {
            const container = e.target.closest('.typeahead-container');
            if (!container) return;

            if (container.classList.contains('model-wrapper')) {
                const modelLabelInp = tr.querySelector('.model-label-input');
                if (modelLabelInp) modelLabelInp.value = '';
                loadConfigurationsForModel(tr, null);
            }
        });

        if (modelId) {
            loadConfigurationsForModel(tr, modelId, configId);
        }

        configSelect.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                if (configLabelInp) configLabelInp.value = selectedOpt.dataset.label || selectedOpt.textContent;
                if (selectedOpt.dataset.width && widthInput) widthInput.value = selectedOpt.dataset.width;
                if (selectedOpt.dataset.length && lengthInput) lengthInput.value = selectedOpt.dataset.length;
            } else {
                if (configLabelInp) configLabelInp.value = '';
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
                if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                    window.Alpine.destroyTree(tr);
                }
                tr.remove();
                renumberLines();
                updateGrandTotal();
            } else {
                alert('يجب إبقاء بند واحد على الأقل في عرض السعر.');
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

    if (oldLines && oldLines.length > 0) {
        oldLines.forEach(line => addLine(line));
    } else {
        addLine();
    }
});
</script>
@endsection
