@extends('layouts.app')

@section('title', 'إنشاء طلب عميل جديد')
@section('page-title', 'طلب عميل جديد')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="record-header mb-4">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-light border record-header-back" aria-label="العودة لطلبات العملاء" title="العودة لطلبات العملاء">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">إنشاء طلب عميل جديد</h1>
            <p class="text-muted mb-0 fs-7">إدخال طلب بيع تجاري وتوصيف البنود والمواصفات المطلوبة</p>
        </div>
    </div>

    {{-- Validation errors are shown once by the layout flash partial. --}}

    <form action="{{ route('sales.orders.store') }}" method="POST" id="orderForm" data-unsaved-warning>
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
                        <input type="text" name="external_order_reference" class="form-control @error('external_order_reference') is-invalid @enderror" value="{{ old('external_order_reference') }}" placeholder="مثال: PO-9982">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ الطلب <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control @error('order_date') is-invalid @enderror" value="{{ old('order_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ التسليم المتوقع</label>
                        <input type="date" name="requested_delivery_date" class="form-control @error('requested_delivery_date') is-invalid @enderror" value="{{ old('requested_delivery_date') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">أولوية الطلب <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            <option value="NORMAL" {{ old('priority') == 'NORMAL' ? 'selected' : '' }}>عادية (Normal)</option>
                            <option value="URGENT" {{ old('priority') == 'URGENT' ? 'selected' : '' }}>عاجلة (Urgent)</option>
                            <option value="VIP" {{ old('priority') == 'VIP' ? 'selected' : '' }}>VIP عالية الأهمية</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">ملاحظات ووصايا الطلب التجاري</label>
                        <textarea name="commercial_notes" class="form-control" rows="2" placeholder="أدخل أي تعليمات تسليم أو ملاحظات خاصة بالطلب...">{{ old('commercial_notes') }}</textarea>
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
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-bordered align-middle mb-0 table-stack-sm" id="lines-table">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th style="width: 35px;" class="text-center">#</th>
                                <th style="min-width: 220px;">الموديل / التصميم</th>
                                <th style="min-width: 190px;">التكوين / المقاس</th>
                                <th style="min-width: 300px;">مواصفات القماش (المورد / الخامة / اللون)</th>
                                <th style="width: 80px;">الكمية</th>
                                <th style="width: 110px;">سعر الوحدة</th>
                                <th style="width: 110px;">المجموع</th>
                                <th style="min-width: 120px;">ملاحظات البند</th>
                                <th style="width: 45px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="lines-container">
                            {{-- Lines inserted dynamically via JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-success w-100 d-md-none" id="add-line-btn-mobile">
                    <i class="fas fa-plus" aria-hidden="true"></i> إضافة بند جديد
                </button>
                <div class="fw-bold text-dark fs-6">
                    عدد البنود: <span id="line-count">0</span>
                </div>
                <div class="fw-bold text-primary fs-5">
                    إجمالي مبلغ الطلب: <span id="grand-total">0.00</span> ر.س
                </div>
            </div>
        </div>

        <x-mobile-action-bar>
            <a href="{{ route('sales.orders.index') }}" class="btn btn-light border px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-5 fw-bold">
                <i class="fas fa-save" aria-hidden="true"></i> حفظ
            </button>
        </x-mobile-action-bar>
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

    $supplierIds = collect($rawOldLines)->pluck('fabric_supplier_id')->filter()->unique()->all();
    $suppliers = !empty($supplierIds) ? \App\Models\Supplier::whereIn('id', $supplierIds)->get()->keyBy('id') : collect();

    $materialIds = collect($rawOldLines)->pluck('fabric_material_id')->filter()->unique()->all();
    $materials = !empty($materialIds) ? \App\Models\Material::with('fabricSpec.supplier')->whereIn('id', $materialIds)->get()->keyBy('id') : collect();

    $colorIds = collect($rawOldLines)->pluck('fabric_color_id')->filter()->unique()->all();
    $colors = !empty($colorIds) ? \App\Models\FabricColor::whereIn('id', $colorIds)->get()->keyBy('id') : collect();

    foreach ($rawOldLines as $line) {
        $mid = $line['product_model_id'] ?? null;
        $cid = $line['product_configuration_id'] ?? null;
        $sid = $line['fabric_supplier_id'] ?? null;
        $fid = $line['fabric_material_id'] ?? null;
        $clid = $line['fabric_color_id'] ?? null;

        $m = $mid ? $models->get($mid) : null;
        $c = $cid ? $configs->get($cid) : null;
        $f = $fid ? $materials->get($fid) : null;
        $s = $sid ? $suppliers->get($sid) : ($f?->fabricSpec?->supplier ?? null);
        $col = $clid ? $colors->get($clid) : null;

        $storageLabel = ($c && $c->has_storage) ? 'بتخزين' : 'بدون تخزين';
        $cLabel = $c ? "{$c->width_cm}×{$c->length_cm} — {$storageLabel}" : '';

        $oldLinesData[] = array_merge($line, [
            'product_model_label' => $line['product_model_label'] ?? ($m ? $m->name_ar : ''),
            'product_configuration_label' => $line['product_configuration_label'] ?? $cLabel,
            'fabric_supplier_id' => $line['fabric_supplier_id'] ?? ($s ? $s->id : ''),
            'fabric_supplier_label' => $line['fabric_supplier_label'] ?? ($s ? $s->name : ''),
            'fabric_material_label' => $line['fabric_material_label'] ?? ($f ? $f->name_ar : ''),
            'catalog_number' => $f?->fabricSpec?->catalog_number ?? '',
            'fabric_color_id' => $line['fabric_color_id'] ?? ($col ? $col->id : ''),
            'fabric_color_code' => $line['fabric_color_code'] ?? ($col ? $col->color_code : ''),
            'fabric_supplier_color_code' => $line['fabric_supplier_color_code'] ?? ($col ? $col->supplier_color_code : ''),
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
                // If old selection was custom or not in active configs
                configSelect.value = selectedConfigId;
            }
        })
        .catch(err => {
            console.error('Failed to load configurations', err);
            configSelect.innerHTML = '<option value="">تعذر تحميل المقاسات</option>';
            configSelect.disabled = false;
        });
    }

    function loadColorsForFabric(tr, fabricId, selectedColorId = null, initialColorCode = null, initialSupplierColorCode = null) {
        const colorSelect = tr.querySelector('.color-select');
        const colorCodeInput = tr.querySelector('.color-code-input');
        const supplierColorCodeInput = tr.querySelector('.supplier-color-code-input');
        if (!colorSelect) return;

        if (!fabricId) {
            colorSelect.innerHTML = '<option value="">اختر القماش أولاً...</option>';
            colorSelect.disabled = true;
            if (colorCodeInput) colorCodeInput.value = '';
            if (supplierColorCodeInput) supplierColorCodeInput.value = '';
            return;
        }

        colorSelect.disabled = true;
        colorSelect.innerHTML = '<option value="">جاري تحميل درجات الألوان...</option>';

        fetch(`/api/search/fabric-materials/${fabricId}/colors`)
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(colors => {
                colorSelect.innerHTML = '<option value="">-- اختر درجة اللون --</option>';
                let foundMatch = false;

                colors.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.dataset.code = c.color_code || '';
                    opt.dataset.supplierCode = c.supplier_color_code || '';
                    opt.textContent = c.label;

                    if (selectedColorId && String(selectedColorId) === String(c.id)) {
                        opt.selected = true;
                        foundMatch = true;
                    } else if (!selectedColorId && initialColorCode && String(initialColorCode) === String(c.color_code)) {
                        opt.selected = true;
                        foundMatch = true;
                    }

                    colorSelect.appendChild(opt);
                });

                colorSelect.disabled = false;

                if (foundMatch) {
                    const sel = colorSelect.options[colorSelect.selectedIndex];
                    if (colorCodeInput && sel) colorCodeInput.value = sel.dataset.code || '';
                    if (supplierColorCodeInput && sel) supplierColorCodeInput.value = sel.dataset.supplierCode || '';
                } else if (!selectedColorId && !initialColorCode && colors.length === 1) {
                    colorSelect.selectedIndex = 1;
                    const sel = colorSelect.options[1];
                    if (colorCodeInput && sel) colorCodeInput.value = sel.dataset.code || '';
                    if (supplierColorCodeInput && sel) supplierColorCodeInput.value = sel.dataset.supplierCode || '';
                }
            })
            .catch(err => {
                console.error('Error fetching colors:', err);
                colorSelect.innerHTML = '<option value="">تعذر تحميل الألوان</option>';
                colorSelect.disabled = false;
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
        const supplierId = data.fabric_supplier_id || '';
        const supplierLabel = data.fabric_supplier_label || '';
        const fabricId = data.fabric_material_id || '';
        const fabricLabel = data.fabric_material_label || '';
        const catalogNumber = data.catalog_number || '';
        const colorId = data.fabric_color_id || '';
        const colorCode = data.fabric_color_code || '';
        const supplierColorCode = data.fabric_supplier_color_code || '';
        const isCustom = data.custom_design ? 'checked' : '';
        const customName = data.custom_design_name || '';
        const customNameDisplay = data.custom_design ? '' : 'd-none';

        tr.innerHTML = `
            <td class="text-center font-monospace text-muted stack-head">
                <span class="min-w-0 text-truncate">
                    <span class="d-md-none">البند </span><span class="line-num"></span><span class="line-model-title d-md-none fw-bold text-dark"></span>
                </span>
                <span class="d-md-none d-flex gap-1 flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary duplicate-line-btn" aria-label="تكرار البند" title="تكرار البند"><i class="fas fa-copy" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-line-btn" aria-expanded="true" aria-label="طي أو توسيع البند" title="طي / توسيع"><i class="fas fa-chevron-up" aria-hidden="true"></i></button>
                </span>
            </td>
            <td data-label="الموديل / التصميم *">
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
                        <input type="text" inputmode="search" class="form-control form-control-sm"
                               x-ref="inputBox"
                               x-model="searchQuery"
                               role="combobox" aria-autocomplete="list" autocomplete="off" enterkeyhint="search"
                               :aria-expanded="isOpen.toString()" :aria-controls="listboxId" :aria-activedescendant="activeOptionId"
                               aria-label="الموديل"
                               @focus="onFocus"
                               @input.debounce.250ms="onInput"
                               @keydown.arrow-down.prevent="navigateDown"
                               @keydown.arrow-up.prevent="navigateUp"
                               @keydown.enter.prevent.stop="selectHighlighted"
                               @keydown.escape="closeDropdown"
                               placeholder="ابحث عن الموديل (كود / اسم)...">
                        <button class="btn btn-outline-secondary btn-sm" type="button" x-show="selectedId" @click="clearSelection" aria-label="مسح الموديل المختار">
                            <i class="fas fa-times fs-8" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="typeahead-dropdown" x-show="isOpen" :style="dropdownStyle" :class="{ 'is-above': dropUp }" :id="listboxId" role="listbox" aria-label="نتائج الموديلات" @mousedown.prevent x-cloak>
                        <div x-show="loading" class="typeahead-status" role="status">
                            <i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i> جاري البحث...
                        </div>
                        <template x-for="(item, index) in results" :key="item.id">
                            <div class="typeahead-item" role="option" :id="optionId(index)" :aria-selected="(index === highlightedIndex).toString()"
                                 :class="{ 'active': index === highlightedIndex }"
                                 @mousedown.prevent="selectItem(item)">
                                <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                                    <span class="text-truncate fw-medium" dir="rtl" x-text="item.label"></span>
                                    <span class="code-badge text-nowrap" dir="ltr" x-text="item.code"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="!loading && errorMessage" class="typeahead-status is-error" role="alert">
                            <span x-text="errorMessage"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" @mousedown.prevent="retry()">إعادة المحاولة</button>
                        </div>
                        <div x-show="!loading && !errorMessage && hasSearched && results.length === 0" class="typeahead-status">
                            لا توجد موديلات مطابقة. جرّب الكود أو جزءاً من الاسم.
                        </div>
                        <div x-show="!loading && !errorMessage && !hasSearched && results.length === 0" class="typeahead-status">
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
            <td data-label="التكوين / المقاس (سم)">
                <select name="lines[${lineIndex}][product_configuration_id]" class="form-select form-select-sm config-select mb-1" aria-label="التكوين / المقاس" ${!modelId ? 'disabled' : ''}>
                    <option value="">اختر المقاس...</option>
                    ${configId ? `<option value="${configId}" selected>${configLabel || 'المقاس المختار'}</option>` : ''}
                </select>
                <input type="hidden" name="lines[${lineIndex}][product_configuration_label]" class="config-label-input" value="${configLabel.replace(/"/g, '&quot;')}">

                <div class="row g-1">
                    <div class="col-6">
                        <input type="number" step="0.1" inputmode="decimal" name="lines[${lineIndex}][requested_width_cm]" class="form-control form-control-sm width-input" value="${data.requested_width_cm || ''}" placeholder="عرض سم" aria-label="العرض بالسنتيمتر">
                    </div>
                    <div class="col-6">
                        <input type="number" step="0.1" inputmode="decimal" name="lines[${lineIndex}][requested_length_cm]" class="form-control form-control-sm length-input" value="${data.requested_length_cm || ''}" placeholder="طول سم" aria-label="الطول بالسنتيمتر">
                    </div>
                </div>
            </td>
            <td data-label="القماش (المورد / الخامة / اللون)">
                <div class="fabric-spec-box d-flex flex-column gap-1">
                    <div class="typeahead-container fabric-wrapper" @click.outside="closeDropdown" x-data="typeaheadSelect({
                        name: 'lines[${lineIndex}][fabric_material_id]',
                        endpoint: '/api/search/fabric-materials',
                        placeholder: 'ابحث عن خامة القماش أو الكتالوج...',
                        initialId: '${fabricId}',
                        initialLabel: '${fabricLabel.replace(/'/g, "\\'")}'
                    })">
                        <input type="hidden" name="lines[${lineIndex}][fabric_material_id]" :value="selectedId" class="fabric-id-input">
                        <input type="hidden" name="lines[${lineIndex}][fabric_material_label]" :value="selectedLabel" class="fabric-label-input">
                        <div class="input-group input-group-sm">
                            <input type="text" inputmode="search" class="form-control form-control-sm"
                                   x-ref="inputBox"
                                   x-model="searchQuery"
                                   role="combobox" aria-autocomplete="list" autocomplete="off" enterkeyhint="search"
                                   :aria-expanded="isOpen.toString()" :aria-controls="listboxId" :aria-activedescendant="activeOptionId"
                                   aria-label="خامة القماش"
                                   @focus="onFocus"
                                   @input.debounce.250ms="onInput"
                                   @keydown.arrow-down.prevent="navigateDown"
                                   @keydown.arrow-up.prevent="navigateUp"
                                   @keydown.enter.prevent.stop="selectHighlighted"
                                   @keydown.escape="closeDropdown"
                                   placeholder="ابحث عن خامة القماش أو الكتالوج...">
                            <button class="btn btn-outline-secondary btn-sm" type="button" x-show="selectedId" @click="clearSelection" aria-label="مسح القماش المختار">
                                <i class="fas fa-times fs-8" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="typeahead-dropdown" x-show="isOpen" :style="dropdownStyle" :class="{ 'is-above': dropUp }" :id="listboxId" role="listbox" aria-label="نتائج الأقمشة" @mousedown.prevent x-cloak>
                            <div x-show="loading" class="typeahead-status" role="status">
                                <i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i> جاري البحث...
                            </div>
                            <template x-for="(item, index) in results" :key="item.id">
                                <div class="typeahead-item" role="option" :id="optionId(index)" :aria-selected="(index === highlightedIndex).toString()"
                                     :class="{ 'active': index === highlightedIndex }"
                                     @mousedown.prevent="selectItem(item)">
                                    <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                                        <div class="d-flex flex-column text-truncate">
                                            <span class="text-truncate fw-medium" dir="rtl" x-text="item.label"></span>
                                            <small class="text-muted fs-8" x-show="item.supplier_name">
                                                المورد: <span x-text="item.supplier_name"></span>
                                                <span x-show="item.catalog_number">(كتالوج: #<span x-text="item.catalog_number"></span>)</span>
                                            </small>
                                        </div>
                                        <span class="code-badge text-nowrap" dir="ltr" x-text="item.code"></span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="!loading && errorMessage" class="typeahead-status is-error" role="alert">
                                <span x-text="errorMessage"></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" @mousedown.prevent="retry()">إعادة المحاولة</button>
                            </div>
                            <div x-show="!loading && !errorMessage && hasSearched && results.length === 0" class="typeahead-status">
                                لا توجد أقمشة مطابقة. جرّب رقم الكتالوج أو اسم المورد.
                            </div>
                            <div x-show="!loading && !errorMessage && !hasSearched && results.length === 0" class="typeahead-status">
                                ابدأ بالكتابة للبحث...
                            </div>
                        </div>
                    </div>

                    <!-- Auto-inferred Supplier and Catalog Info -->
                    <input type="hidden" name="lines[${lineIndex}][fabric_supplier_id]" class="supplier-id-input" value="${supplierId}">
                    <input type="hidden" name="lines[${lineIndex}][fabric_supplier_label]" class="supplier-label-input" value="${supplierLabel}">

                    <div class="fabric-inferred-info d-flex flex-wrap align-items-center gap-1 fs-8 ${fabricId ? '' : 'd-none'}">
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-truck me-1 text-muted"></i>المورد: <strong class="inferred-supplier-name">${supplierLabel || '—'}</strong>
                        </span>
                        <span class="badge bg-light text-primary border catalog-info-badge ${catalogNumber ? '' : 'd-none'}">
                            <i class="fas fa-book me-1"></i>كتالوج: <strong class="inferred-catalog-number">${catalogNumber || '—'}</strong>
                        </span>
                    </div>

                    <!-- Color Select Dropdown -->
                    <div>
                        <select name="lines[${lineIndex}][fabric_color_id]" class="form-select form-select-sm color-select" aria-label="درجة اللون" ${!fabricId ? 'disabled' : ''}>
                            <option value="">${fabricId ? '-- اختر درجة اللون --' : 'اختر القماش أولاً...'}</option>
                            ${colorId ? `<option value="${colorId}" selected data-code="${colorCode}" data-supplier-code="${supplierColorCode}">${colorCode} ${supplierColorCode ? '(' + supplierColorCode + ')' : ''}</option>` : ''}
                        </select>
                        <input type="hidden" name="lines[${lineIndex}][fabric_color_code]" class="color-code-input" value="${colorCode}">
                        <input type="hidden" name="lines[${lineIndex}][fabric_supplier_color_code]" class="supplier-color-code-input" value="${supplierColorCode}">
                    </div>
                </div>
            </td>
            <td data-label="الكمية *" class="stack-half">
                <input type="number" min="1" step="1" inputmode="numeric" name="lines[${lineIndex}][quantity]" class="form-control form-control-sm qty-input" value="${data.quantity || 1}" required aria-label="الكمية">
            </td>
            <td data-label="سعر الوحدة *" class="stack-half">
                <input type="number" step="0.01" min="0" inputmode="decimal" name="lines[${lineIndex}][unit_price]" class="form-control form-control-sm price-input" value="${data.unit_price || 0.00}" required aria-label="سعر الوحدة">
            </td>
            <td data-label="المجموع" class="fw-bold text-dark text-end line-total">
                0.00 ر.س
            </td>
            <td data-label="ملاحظات البند">
                <input type="text" name="lines[${lineIndex}][notes]" class="form-control form-control-sm line-notes-input" value="${(data.notes || '').replace(/"/g, '&quot;')}" placeholder="أي تفاصيل..." aria-label="ملاحظات البند">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn" title="حذف البند" aria-label="حذف البند"><i class="fas fa-trash" aria-hidden="true"></i><span class="d-md-none"> حذف البند</span></button>
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
                setLineTitle(tr, item.label);
                loadConfigurationsForModel(tr, item.id);
            } else if (container.classList.contains('fabric-wrapper')) {
                const matLabelInp = tr.querySelector('.fabric-label-input');
                if (matLabelInp) matLabelInp.value = item.label || '';

                const supIdInp = tr.querySelector('.supplier-id-input');
                const supLabelInp = tr.querySelector('.supplier-label-input');
                const infoBox = tr.querySelector('.fabric-inferred-info');
                const supText = tr.querySelector('.inferred-supplier-name');
                const catBadge = tr.querySelector('.catalog-info-badge');
                const catText = tr.querySelector('.inferred-catalog-number');

                if (supIdInp) supIdInp.value = item.supplier_id || '';
                if (supLabelInp) supLabelInp.value = item.supplier_name || '';
                if (supText) supText.textContent = item.supplier_name || '—';
                if (catText) catText.textContent = item.catalog_number || '—';

                if (infoBox) infoBox.classList.remove('d-none');
                if (catBadge) {
                    if (item.catalog_number) {
                        catBadge.classList.remove('d-none');
                    } else {
                        catBadge.classList.add('d-none');
                    }
                }

                loadColorsForFabric(tr, item.id);
            }
        });

        tr.addEventListener('typeahead-clear', function (e) {
            const container = e.target.closest('.typeahead-container');
            if (!container) return;

            if (container.classList.contains('model-wrapper')) {
                const modelLabelInp = tr.querySelector('.model-label-input');
                if (modelLabelInp) modelLabelInp.value = '';
                setLineTitle(tr, '');
                loadConfigurationsForModel(tr, null);
            } else if (container.classList.contains('fabric-wrapper')) {
                const matLabelInp = tr.querySelector('.fabric-label-input');
                if (matLabelInp) matLabelInp.value = '';

                const supIdInp = tr.querySelector('.supplier-id-input');
                const supLabelInp = tr.querySelector('.supplier-label-input');
                const infoBox = tr.querySelector('.fabric-inferred-info');

                if (supIdInp) supIdInp.value = '';
                if (supLabelInp) supLabelInp.value = '';
                if (infoBox) infoBox.classList.add('d-none');

                loadColorsForFabric(tr, null);
            }
        });

        const colorSelect = tr.querySelector('.color-select');
        if (colorSelect) {
            colorSelect.addEventListener('change', function () {
                const sel = this.options[this.selectedIndex];
                const colorCodeInp = tr.querySelector('.color-code-input');
                const supColorCodeInp = tr.querySelector('.supplier-color-code-input');
                if (sel && sel.value) {
                    if (colorCodeInp) colorCodeInp.value = sel.dataset.code || '';
                    if (supColorCodeInp) supColorCodeInp.value = sel.dataset.supplierCode || '';
                } else {
                    if (colorCodeInp) colorCodeInp.value = '';
                    if (supColorCodeInp) supColorCodeInp.value = '';
                }
            });
        }

        if (modelId) {
            loadConfigurationsForModel(tr, modelId, configId);
        }

        if (fabricId) {
            loadColorsForFabric(tr, fabricId, colorId, colorCode, supplierColorCode);
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
                alert('يجب إبقاء بند واحد على الأقل في الطلب.');
            }
        });

        // Mobile card tools: collapse a finished line, or duplicate it for a similar bed.
        const collapseBtn = tr.querySelector('.collapse-line-btn');
        collapseBtn?.addEventListener('click', function () {
            const collapsed = tr.classList.toggle('is-collapsed');
            this.setAttribute('aria-expanded', String(!collapsed));
            this.querySelector('i')?.classList.toggle('fa-chevron-up', !collapsed);
            this.querySelector('i')?.classList.toggle('fa-chevron-down', collapsed);
        });
        tr.querySelector('.duplicate-line-btn')?.addEventListener('click', function () {
            const colorOpt = colorSelect?.options[colorSelect.selectedIndex];
            const newTr = addLine({
                product_model_id: tr.querySelector('.model-id-input')?.value || '',
                product_model_label: tr.querySelector('.model-label-input')?.value || '',
                product_configuration_id: configSelect.value || '',
                product_configuration_label: configLabelInp?.value || '',
                requested_width_cm: widthInput.value,
                requested_length_cm: lengthInput.value,
                custom_design: customCheck.checked,
                custom_design_name: customNameInput.value,
                fabric_supplier_id: tr.querySelector('.supplier-id-input')?.value || '',
                fabric_supplier_label: tr.querySelector('.supplier-label-input')?.value || '',
                fabric_material_id: tr.querySelector('.fabric-id-input')?.value || '',
                fabric_material_label: tr.querySelector('.fabric-label-input')?.value || '',
                catalog_number: tr.querySelector('.inferred-catalog-number')?.textContent.replace('—', '').trim() || '',
                fabric_color_id: colorSelect?.value || '',
                fabric_color_code: colorOpt?.dataset.code || '',
                fabric_supplier_color_code: colorOpt?.dataset.supplierCode || '',
                quantity: qtyInput.value,
                unit_price: priceInput.value,
                notes: tr.querySelector('.line-notes-input')?.value || '',
            });
            newTr.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        setLineTitle(tr, modelLabel);
        renumberLines();
        updateLineTotal();

        return tr;
    }

    function setLineTitle(tr, label) {
        const title = tr.querySelector('.line-model-title');
        if (title) {
            title.textContent = label ? ` — ${label}` : '';
        }
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
    document.getElementById('add-line-btn-mobile')?.addEventListener('click', () => {
        addLine().scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    if (oldLines && oldLines.length > 0) {
        oldLines.forEach(line => addLine(line));
    } else {
        addLine();
    }
});
</script>
@endsection
