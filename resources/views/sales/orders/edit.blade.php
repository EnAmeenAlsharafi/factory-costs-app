@extends('layouts.app')

@section('title', 'تعديل طلب عميل ' . $order->order_number)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">تعديل طلب عميل: {{ $order->order_number }}</h1>
            <p class="text-muted mb-0 fs-7">تحديث بيانات الطلب التجاري وتعديل المواصفات والبنود</p>
        </div>
        <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> التراجع والألغاء
        </a>
    </div>

    {{-- Post Approval Warning Banner --}}
    @if($order->status === 'APPROVED_FOR_PRODUCTION')
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fs-3 me-3 text-warning"></i>
                <div>
                    <h6 class="fw-bold mb-1">تنبيه هامة: هذا الطلب معتمد رسمياً للإنتاج حالياً!</h6>
                    <p class="mb-0 fs-7">
                        حفظ أي تعديلات على هذا الطلب سوف يسجل تغييراً في <strong>سجل التدقيق</strong> وسوف يعيد حالة الطلب تلقائياً إلى 
                        <span class="badge bg-dark text-warning">بانتظار مراجعة الإنتاج</span> لإعادة اعتماده فنياً من جديد.
                    </p>
                </div>
            </div>
        </div>
    @endif

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

    <form action="{{ route('sales.orders.update', $order) }}" method="POST" id="orderForm">
        @csrf
        @method('PUT')

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
                                <option value="{{ $customer->id }}" {{ old('customer_id', $order->customer_id) == $customer->id ? 'selected' : '' }}>
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
                                <option value="{{ $channel->id }}" {{ old('sales_channel_id', $order->sales_channel_id) == $channel->id ? 'selected' : '' }}>
                                    {{ $channel->name_ar }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">رقم أمر شراء العميل (PO)</label>
                        <input type="text" name="external_order_reference" class="form-control @error('external_order_reference') is-invalid @enderror" value="{{ old('external_order_reference', $order->external_order_reference) }}" placeholder="مثال: PO-9982">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ الطلب <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control @error('order_date') is-invalid @enderror" value="{{ old('order_date', $order->order_date ? $order->order_date->format('Y-m-d') : '') }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">تاريخ التسليم المتوقع</label>
                        <input type="date" name="requested_delivery_date" class="form-control @error('requested_delivery_date') is-invalid @enderror" value="{{ old('requested_delivery_date', $order->requested_delivery_date ? $order->requested_delivery_date->format('Y-m-d') : '') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">أولوية الطلب <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            <option value="NORMAL" {{ old('priority', $order->priority) == 'NORMAL' ? 'selected' : '' }}>عادية (Normal)</option>
                            <option value="URGENT" {{ old('priority', $order->priority) == 'URGENT' ? 'selected' : '' }}>عاجلة (Urgent)</option>
                            <option value="VIP" {{ old('priority', $order->priority) == 'VIP' ? 'selected' : '' }}>VIP عالية الأهمية</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">ملاحظات ووصايا الطلب</label>
                        <textarea name="commercial_notes" class="form-control" rows="2" placeholder="أدخل أي تعليمات تسليم أو ملاحظات خاصة بالطلب...">{{ old('commercial_notes', $order->commercial_notes) }}</textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary">سبب التعديل (يسجل في التغييرات)</label>
                        <input type="text" name="change_notes" class="form-control" placeholder="سبب تعديل هذا الطلب...">
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
                    <table class="table table-bordered align-middle mb-0" id="lines-table">
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
                            {{-- Lines rendered via script --}}
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
            <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-light px-4">إلغاء</a>
            <button type="submit" class="btn btn-primary px-5 fw-bold">
                <i class="fas fa-save me-1"></i> تحديث الطلب
            </button>
        </div>
    </form>
</div>

@php
$dbLinesData = $order->lines->map(function($l) {
    $cfg = $l->productConfiguration;
    $cfgStorage = ($cfg && $cfg->has_storage) ? 'بتخزين' : 'بدون تخزين';
    $cfgLabel = $cfg ? "{$cfg->width_cm}×{$cfg->length_cm} — {$cfgStorage}" : '';

    return [
        'id' => $l->id,
        'product_model_id' => $l->product_model_id,
        'product_model_label' => $l->productModel ? $l->productModel->name_ar : '',
        'product_model_code' => $l->productModel ? $l->productModel->model_code : '',
        'custom_design' => $l->custom_design,
        'custom_design_name' => $l->custom_design_name,
        'product_configuration_id' => $l->product_configuration_id,
        'product_configuration_label' => $cfgLabel,
        'requested_width_cm' => $l->requested_width_cm,
        'requested_length_cm' => $l->requested_length_cm,
        'fabric_supplier_id' => $l->fabric_supplier_id,
        'fabric_supplier_label' => $l->fabricSupplier ? $l->fabricSupplier->name : '',
        'fabric_supplier_code' => $l->fabricSupplier ? $l->fabricSupplier->supplier_code : '',
        'fabric_material_id' => $l->fabric_material_id,
        'fabric_material_label' => $l->fabricMaterial ? $l->fabricMaterial->name_ar : '',
        'fabric_material_code' => $l->fabricMaterial ? $l->fabricMaterial->code : '',
        'fabric_color_code' => $l->fabric_color_code,
        'quantity' => $l->quantity,
        'unit_price' => $l->unit_price,
        'notes' => $l->notes,
    ];
})->values()->all();

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
    $materials = !empty($materialIds) ? \App\Models\Material::whereIn('id', $materialIds)->get()->keyBy('id') : collect();

    foreach ($rawOldLines as $line) {
        $mid = $line['product_model_id'] ?? null;
        $cid = $line['product_configuration_id'] ?? null;
        $sid = $line['fabric_supplier_id'] ?? null;
        $fid = $line['fabric_material_id'] ?? null;

        $m = $mid ? $models->get($mid) : null;
        $c = $cid ? $configs->get($cid) : null;
        $s = $sid ? $suppliers->get($sid) : null;
        $f = $fid ? $materials->get($fid) : null;

        $storageLabel = ($c && $c->has_storage) ? 'بتخزين' : 'بدون تخزين';
        $cLabel = $c ? "{$c->width_cm}×{$c->length_cm} — {$storageLabel}" : '';

        $oldLinesData[] = array_merge($line, [
            'product_model_label' => $line['product_model_label'] ?? ($m ? $m->name_ar : ''),
            'product_model_code' => $line['product_model_code'] ?? ($m ? $m->model_code : ''),
            'product_configuration_label' => $line['product_configuration_label'] ?? $cLabel,
            'fabric_supplier_label' => $line['fabric_supplier_label'] ?? ($s ? $s->name : ''),
            'fabric_supplier_code' => $line['fabric_supplier_code'] ?? ($s ? $s->supplier_code : ''),
            'fabric_material_label' => $line['fabric_material_label'] ?? ($f ? $f->name_ar : ''),
            'fabric_material_code' => $line['fabric_material_code'] ?? ($f ? $f->code : ''),
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
    const dbLines = {!! json_encode($dbLinesData, JSON_UNESCAPED_UNICODE) !!};

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
        const supplierId = data.fabric_supplier_id || '';
        const supplierLabel = data.fabric_supplier_label || '';
        const fabricId = data.fabric_material_id || '';
        const fabricLabel = data.fabric_material_label || '';
        const colorCode = data.fabric_color_code || '';
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
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_width_cm]" class="form-control form-control-sm width-input" value="${data.requested_width_cm || ''}" placeholder="عرض سم">
                    </div>
                    <div class="col-6">
                        <input type="number" step="0.1" name="lines[${lineIndex}][requested_length_cm]" class="form-control form-control-sm length-input" value="${data.requested_length_cm || ''}" placeholder="طول سم">
                    </div>
                </div>
            </td>
            <td>
                <div class="fabric-spec-box">
                    <div class="typeahead-container supplier-wrapper" @click.outside="closeDropdown" x-data="typeaheadSelect({
                        name: 'lines[${lineIndex}][fabric_supplier_id]',
                        endpoint: '/api/search/suppliers',
                        placeholder: 'ابحث عن مورد القماش...',
                        initialId: '${supplierId}',
                        initialLabel: '${supplierLabel.replace(/'/g, "\\'")}'
                    })">
                        <input type="hidden" name="lines[${lineIndex}][fabric_supplier_id]" :value="selectedId" class="supplier-id-input">
                        <input type="hidden" name="lines[${lineIndex}][fabric_supplier_label]" :value="selectedLabel" class="supplier-label-input">
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
                                   placeholder="ابحث عن مورد القماش...">
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

                    <div class="typeahead-container fabric-wrapper" @click.outside="closeDropdown" x-data="typeaheadSelect({
                        name: 'lines[${lineIndex}][fabric_material_id]',
                        endpoint: '/api/search/fabric-materials',
                        placeholder: 'ابحث عن نوع القماش...',
                        disabledPlaceholder: 'اختر مورد القماش أولاً',
                        initialId: '${fabricId}',
                        initialLabel: '${fabricLabel.replace(/'/g, "\\'")}',
                        requireParent: true,
                        parentParam: 'supplier_id',
                        parentSelector: '.supplier-id-input'
                    })">
                        <input type="hidden" name="lines[${lineIndex}][fabric_material_id]" :value="selectedId" class="fabric-id-input">
                        <input type="hidden" name="lines[${lineIndex}][fabric_material_label]" :value="selectedLabel" class="fabric-label-input">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control form-control-sm"
                                   x-ref="inputBox"
                                   x-model="searchQuery"
                                   :disabled="isDisabled"
                                   @focus="onFocus"
                                   @input.debounce.250ms="onInput"
                                   @keydown.arrow-down.prevent="navigateDown"
                                   @keydown.arrow-up.prevent="navigateUp"
                                   @keydown.enter.prevent.stop="selectHighlighted"
                                   @keydown.escape="closeDropdown"
                                   :placeholder="placeholderText">
                            <button class="btn btn-outline-secondary btn-sm" type="button" x-show="selectedId && !isDisabled" @click="clearSelection" tabindex="-1">
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
                            <div x-show="!loading && results.length === 0" class="p-2 text-center fs-8">
                                <template x-if="hasSupplierFabrics === false">
                                    <span class="text-danger fw-medium"><i class="fas fa-exclamation-triangle me-1"></i> لا توجد أنواع قماش مرتبطة بهذا المورد في النظام. يرجى اختيار مورد أقمشة آخر أو ربط المورد بالخامة.</span>
                                </template>
                                <template x-if="hasSupplierFabrics !== false && hasSearched">
                                    <span class="text-muted">لا توجد نتائج مطابقة</span>
                                </template>
                                <template x-if="hasSupplierFabrics !== false && !hasSearched">
                                    <span class="text-muted">ابدأ بالكتابة للبحث...</span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <input type="text" name="lines[${lineIndex}][fabric_color_code]" class="form-control form-control-sm color-code-input" value="${colorCode.replace(/"/g, '&quot;')}" placeholder="رقم / كود اللون (مثال: 204)">
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
                <input type="text" name="lines[${lineIndex}][notes]" class="form-control form-control-sm" value="${(data.notes || '').replace(/"/g, '&quot;')}" placeholder="أي تفاصيل...">
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
            } else if (container.classList.contains('supplier-wrapper')) {
                const supLabelInp = tr.querySelector('.supplier-label-input');
                if (supLabelInp) supLabelInp.value = item.label || '';

                const fabricComp = tr.querySelector('.fabric-wrapper');
                if (fabricComp) {
                    const compData = window.Alpine && typeof window.Alpine.$data === 'function'
                        ? window.Alpine.$data(fabricComp)
                        : (fabricComp._x_dataStack ? fabricComp._x_dataStack[0] : null);
                    if (compData) {
                        compData.clearSelection(false);
                        compData.checkDisabled();
                        compData.fetchResults('');
                    }
                    const fabricInput = fabricComp.querySelector('input[type="text"]');
                    if (fabricInput) {
                        setTimeout(() => {
                            fabricInput.focus();
                        }, 60);
                    }
                }
                const colorInp = tr.querySelector('.color-code-input');
                if (colorInp) colorInp.value = '';
            } else if (container.classList.contains('fabric-wrapper')) {
                const matLabelInp = tr.querySelector('.fabric-label-input');
                if (matLabelInp) matLabelInp.value = item.label || '';
            }
        });

        tr.addEventListener('typeahead-clear', function (e) {
            const container = e.target.closest('.typeahead-container');
            if (!container) return;

            if (container.classList.contains('model-wrapper')) {
                const modelLabelInp = tr.querySelector('.model-label-input');
                if (modelLabelInp) modelLabelInp.value = '';
                loadConfigurationsForModel(tr, null);
            } else if (container.classList.contains('supplier-wrapper')) {
                const supLabelInp = tr.querySelector('.supplier-label-input');
                if (supLabelInp) supLabelInp.value = '';

                const fabricComp = tr.querySelector('.fabric-wrapper');
                if (fabricComp) {
                    const compData = window.Alpine && typeof window.Alpine.$data === 'function'
                        ? window.Alpine.$data(fabricComp)
                        : (fabricComp._x_dataStack ? fabricComp._x_dataStack[0] : null);
                    if (compData) {
                        compData.clearSelection(false);
                        compData.checkDisabled();
                    }
                }
                const colorInp = tr.querySelector('.color-code-input');
                if (colorInp) colorInp.value = '';
            } else if (container.classList.contains('fabric-wrapper')) {
                const matLabelInp = tr.querySelector('.fabric-label-input');
                if (matLabelInp) matLabelInp.value = '';
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

    const initialLines = (oldLines && oldLines.length > 0) ? oldLines : dbLines;
    if (initialLines && initialLines.length > 0) {
        initialLines.forEach(line => addLine(line));
    } else {
        addLine();
    }
});
</script>
@endsection
