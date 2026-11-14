@props([
    'materials',
    'components',
    'units',
    'items' => [],
    'title' => 'بنود الخامات والمكونات',
    'hint' => null,
])

@php
    /** @var array<string, string> $lineErrors first message per `items.N.field` key, so each field can flag itself */
    $lineErrors = collect($errors->getMessages())
        ->filter(fn (array $messages, string $key): bool => str_starts_with($key, 'items.'))
        ->map(fn (array $messages): string => $messages[0])
        ->all();
    $initialItems = $items ?: [[
        'item_type' => 'MATERIAL',
        'material_id' => '',
        'semi_finished_component_id' => '',
        'quantity' => 1,
        'unit_id' => '',
        'waste_percentage' => 0,
        'notes' => '',
    ]];
@endphp

{{--
    Bill-of-materials line editor shared by recipe and template forms.
    Desktop: a compact grid with one header row. Below 992px each line becomes a labelled card,
    so nothing scrolls sideways on a phone. Field names are unchanged (items[N][field]).
    Put the form's submit buttons in the `actions` slot: they live inside this Alpine scope
    and are disabled while there are no lines.
--}}
<div x-data="bomEditor(@js(['items' => array_values($initialItems), 'errors' => $lineErrors]))">
    <section class="card border-0 shadow-sm rounded-3 mb-4 bom-editor" aria-labelledby="bom-heading">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div class="min-w-0">
                <h2 id="bom-heading" class="h6 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-list-ol text-warning" aria-hidden="true"></i>
                    <span>{{ $title }}</span>
                    <span class="status-chip status-chip-secondary" aria-live="polite"><span x-text="items.length"></span> بند</span>
                </h2>
                @if ($hint)
                    <p class="text-muted fs-7 mb-0">{{ $hint }}</p>
                @endif
            </div>
            <div class="bom-toolbar">
                <button type="button" @click="addRow('MATERIAL')" class="btn btn-sm btn-outline-primary fw-semibold">
                    <i class="fas fa-plus" aria-hidden="true"></i> مادة خام
                </button>
                <button type="button" @click="addRow('SEMI_FINISHED_COMPONENT')" class="btn btn-sm btn-outline-component">
                    <i class="fas fa-plus" aria-hidden="true"></i> مكون نصف مصنع
                </button>
            </div>
        </div>

        @error('items')
            <div class="alert alert-danger border-0 rounded-0 mb-0 fs-7" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}
            </div>
        @enderror

        <div class="bom-head" aria-hidden="true">
            <span>#</span>
            <span>نوع البند</span>
            <span>المادة الخام / المكون</span>
            <span>الكمية الصافية</span>
            <span>الوحدة</span>
            <span>الهدر %</span>
            <span>الكمية المخططة</span>
            <span>ملاحظات</span>
            <span></span>
        </div>

        <template x-for="(item, index) in items" :key="item.uid">
            <div class="bom-line" :class="{ 'has-error': lineHasError(index) }" role="group" :aria-label="`البند ${index + 1}`">
                <div class="bom-cell-index"><span class="bom-index" x-text="index + 1"></span></div>

                <div>
                    <label class="bom-field-label" :for="`bom-${item.uid}-type`">نوع البند</label>
                    <select :id="`bom-${item.uid}-type`" :name="`items[${index}][item_type]`" class="form-select form-select-sm" x-model="item.item_type">
                        <option value="MATERIAL">مادة خام</option>
                        <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع</option>
                    </select>
                </div>

                <div class="bom-cell-wide">
                    <label class="bom-field-label" :for="`bom-${item.uid}-ref`" x-text="item.item_type === 'MATERIAL' ? 'المادة الخام' : 'المكون نصف المصنع'"></label>
                    <div x-show="item.item_type === 'MATERIAL'">
                        <select :id="item.item_type === 'MATERIAL' ? `bom-${item.uid}-ref` : null" :name="`items[${index}][material_id]`" class="form-select form-select-sm"
                                :class="{ 'is-invalid': fieldError(index, 'material_id') }" x-model="item.material_id" @change="onMaterialSelect(item, $event)">
                            <option value="">اختر المادة الخام</option>
                            @foreach ($materials as $material)
                                <option value="{{ $material->id }}" data-unit="{{ $material->base_unit_id }}">{{ $material->name_ar }} ({{ $material->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="item.item_type === 'SEMI_FINISHED_COMPONENT'">
                        <select :id="item.item_type === 'SEMI_FINISHED_COMPONENT' ? `bom-${item.uid}-ref` : null" :name="`items[${index}][semi_finished_component_id]`" class="form-select form-select-sm"
                                :class="{ 'is-invalid': fieldError(index, 'semi_finished_component_id') }" x-model="item.semi_finished_component_id">
                            <option value="">اختر المكون نصف المصنع</option>
                            @foreach ($components as $component)
                                <option value="{{ $component->id }}">{{ $component->name_ar }} ({{ $component->component_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="small text-danger mt-1" x-show="fieldError(index, 'material_id') || fieldError(index, 'semi_finished_component_id')"
                         x-text="fieldError(index, 'material_id') || fieldError(index, 'semi_finished_component_id')"></div>
                </div>

                <div>
                    <label class="bom-field-label" :for="`bom-${item.uid}-qty`">الكمية الصافية</label>
                    <input type="number" step="0.0001" min="0.0001" inputmode="decimal" :id="`bom-${item.uid}-qty`" :name="`items[${index}][quantity]`"
                           class="form-control form-control-sm text-center fw-bold" :class="{ 'is-invalid': fieldError(index, 'quantity') }" x-model="item.quantity">
                    <div class="invalid-feedback" x-text="fieldError(index, 'quantity')"></div>
                </div>

                <div>
                    <label class="bom-field-label" :for="`bom-${item.uid}-unit`">الوحدة</label>
                    <select :id="`bom-${item.uid}-unit`" :name="`items[${index}][unit_id]`" class="form-select form-select-sm" :class="{ 'is-invalid': fieldError(index, 'unit_id') }" x-model="item.unit_id">
                        <option value="">الوحدة</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name_ar }} ({{ $unit->code }})</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" x-text="fieldError(index, 'unit_id')"></div>
                </div>

                <div>
                    <label class="bom-field-label" :for="`bom-${item.uid}-waste`">الهدر %</label>
                    <input type="number" step="0.1" min="0" max="100" inputmode="decimal" :id="`bom-${item.uid}-waste`" :name="`items[${index}][waste_percentage]`"
                           class="form-control form-control-sm text-center" :class="{ 'is-invalid': fieldError(index, 'waste_percentage') }" x-model="item.waste_percentage">
                    <div class="invalid-feedback" x-text="fieldError(index, 'waste_percentage')"></div>
                </div>

                <div>
                    <span class="bom-field-label">الكمية المخططة</span>
                    <div class="bom-planned" title="الكمية الصافية + نسبة الهدر">
                        <span class="qty qty-planned" x-text="formatQuantity(plannedQuantity(item))"></span>
                    </div>
                </div>

                <div class="bom-cell-wide">
                    <label class="bom-field-label" :for="`bom-${item.uid}-notes`">ملاحظات</label>
                    <input type="text" :id="`bom-${item.uid}-notes`" :name="`items[${index}][notes]`" class="form-control form-control-sm" x-model="item.notes" placeholder="اختياري">
                </div>

                <div class="bom-cell-remove">
                    <button type="button" @click="removeRow(index)" class="btn btn-outline-danger bom-remove" :aria-label="`حذف البند ${index + 1}`" title="حذف البند">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </template>

        <div class="bom-empty" x-show="items.length === 0" x-cloak role="status">
            <i class="fas fa-boxes-stacked fs-2 d-block mb-2 opacity-50" aria-hidden="true"></i>
            <p class="mb-3">لا توجد بنود بعد. أضف أول مادة خام أو مكون نصف مصنع.</p>
            <div class="empty-state-cta">
                <button type="button" @click="addRow('MATERIAL')" class="btn btn-outline-primary"><i class="fas fa-plus" aria-hidden="true"></i> إضافة مادة خام</button>
                <button type="button" @click="addRow('SEMI_FINISHED_COMPONENT')" class="btn btn-outline-component"><i class="fas fa-plus" aria-hidden="true"></i> إضافة مكون نصف مصنع</button>
            </div>
        </div>

        <div class="card-footer bg-white border-top py-2 fs-7 text-muted" x-show="items.length > 0">
            الكمية المخططة = الكمية الصافية × (1 + نسبة الهدر). الأسعار لا تُدخل هنا، وتُحسب لاحقاً من دفعات المخزون.
        </div>
    </section>

    {{ $actions ?? '' }}
</div>

@once
    @push('scripts')
        <script>
            function bomEditor(config) {
                let nextUid = 0;
                const normalize = (item) => ({
                    uid: ++nextUid,
                    item_type: item.item_type || 'MATERIAL',
                    material_id: item.material_id ? String(item.material_id) : '',
                    semi_finished_component_id: item.semi_finished_component_id ? String(item.semi_finished_component_id) : '',
                    // A new line starts at 1; a line coming back from validation keeps what was typed (even empty).
                    quantity: 'quantity' in item ? (item.quantity ?? '') : 1,
                    unit_id: item.unit_id ? String(item.unit_id) : '',
                    waste_percentage: 'waste_percentage' in item ? (item.waste_percentage ?? '') : 0,
                    notes: item.notes || '',
                });

                return {
                    items: (config.items || []).map(normalize),
                    errors: config.errors || {},
                    addRow(type = 'MATERIAL') {
                        this.items.push(normalize({ item_type: type }));
                        this.$nextTick(() => document.getElementById(`bom-${nextUid}-ref`)?.focus());
                    },
                    removeRow(index) {
                        this.items.splice(index, 1);
                        this.errors = {};
                    },
                    onMaterialSelect(item, event) {
                        const unitId = event.target.selectedOptions[0]?.dataset.unit;
                        if (unitId) {
                            item.unit_id = unitId;
                        }
                    },
                    fieldError(index, field) {
                        // The line is already identified by its card, so drop the "البند N:" prefix used in the page-level list.
                        return (this.errors[`items.${index}.${field}`] || '').replace(/^البند \d+:\s*/, '');
                    },
                    lineHasError(index) {
                        return Object.keys(this.errors).some((key) => key.startsWith(`items.${index}.`));
                    },
                    plannedQuantity(item) {
                        const quantity = parseFloat(item.quantity) || 0;
                        const waste = parseFloat(item.waste_percentage) || 0;
                        return quantity * (1 + waste / 100);
                    },
                    formatQuantity(value) {
                        return value.toLocaleString('en-US', { maximumFractionDigits: 4 });
                    },
                };
            }
        </script>
    @endpush
@endonce
