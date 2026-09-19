@extends('layouts.app')

@section('title', 'إنشاء وصفة تصنيع جديدة (BOM)')

@section('content')
<div class="container-fluid px-4 py-4" x-data="recipeForm()">

    {{-- Header --}}
    <div class="mb-4">
        <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لقائمة الوصفات
        </a>
        <h1 class="h3 mb-1 text-dark fw-bold">إنشاء وصفة تصنيع جديدة (Bill of Materials)</h1>
        <p class="text-muted mb-0 fs-7">تعريف قائمة خامات التصنيع والمكونات الهيكلية للإصدار الأول المسودة (V1 Draft)</p>
    </div>

    {{-- Error Flash --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2">يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('recipes.store') }}" method="POST">
        @csrf

        {{-- Main Header Info Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="card-title mb-0 text-dark fw-bold"><i class="fas fa-info-circle text-warning me-2"></i> البيانات الأساسية للوصفة</h5>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label fw-bold fs-7">نوع الهدف المصنع (Target Type) <span class="text-danger">*</span></label>
                        <select name="target_type" class="form-select" x-model="targetType">
                            <option value="PRODUCT_CONFIGURATION">تكوين منتج نهائي (Product Configuration)</option>
                            <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع (Semi-Finished Component)</option>
                        </select>
                    </div>

                    <div class="col-md-5" x-show="targetType === 'PRODUCT_CONFIGURATION'">
                        <label class="form-label fw-bold fs-7">تكوين المنتج النهائي المستهدف <span class="text-danger">*</span></label>
                        <select name="product_configuration_id" class="form-select" x-model="productConfigId" @change="onConfigChange()">
                            <option value="">-- اختر التكوين المصنعي --</option>
                            @foreach($configurations as $cfg)
                                <option value="{{ $cfg->id }}" data-name="وصفة {{ $cfg->productModel?->name_ar }} {{ $cfg->width_cm }}×{{ $cfg->length_cm }} {{ $cfg->has_storage ? 'سحارة' : 'بدون تخزين' }}">
                                    {{ $cfg->productModel?->name_ar }} | {{ $cfg->width_cm }} × {{ $cfg->length_cm }} سم - {{ $cfg->has_storage ? 'سحارة' : 'بدون تخزين' }} ({{ $cfg->configuration_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5" x-show="targetType === 'SEMI_FINISHED_COMPONENT'">
                        <label class="form-label fw-bold fs-7">المكون نصف المصنع المستهدف <span class="text-danger">*</span></label>
                        <select name="semi_finished_component_id" class="form-select" x-model="componentId" @change="onComponentChange()">
                            <option value="">-- اختر المكون نصف المصنع --</option>
                            @foreach($components as $comp)
                                <option value="{{ $comp->id }}" data-name="وصفة مكون {{ $comp->name_ar }}">
                                    {{ $comp->name_ar }} ({{ $comp->component_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold fs-7">ملاحظات الإصدار الأول V1</label>
                        <input type="text" name="version_notes" class="form-control" placeholder="مثل: المواصفة الافتراضية لعقد 2026" value="إصدار مسودة أولي">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-bold fs-7">اسم الوصفة <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold" x-model="recipeName" placeholder="اسم الوصفة" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold fs-7">وصف أو ملاحظات فنية</label>
                        <input type="text" name="description" class="form-control" placeholder="ملاحظات فنية اختيارية">
                    </div>

                </div>
            </div>
        </div>

        {{-- Recipe Items Table Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                <div>
                    <h5 class="card-title mb-0 text-dark fw-bold"><i class="fas fa-list-ol text-warning me-2"></i> بنود خامات ومكونات الوصفة</h5>
                    <small class="text-muted">أدخل الكميات الصافية المطلوبة ونسبة الهدر المئوية إن وجدت (بدون أسعار)</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" @click="addRow('MATERIAL')" class="btn btn-sm btn-outline-primary fw-semibold">
                        <i class="fas fa-plus me-1"></i> إضافة مادة خام
                    </button>
                    <button type="button" @click="addRow('SEMI_FINISHED_COMPONENT')" class="btn btn-sm btn-outline-purple fw-semibold" style="color: #6f42c1; border-color: #6f42c1;">
                        <i class="fas fa-plus me-1"></i> إضافة مكون نصف مصنع
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 150px;">نوع البند</th>
                                <th>المادة الخام / المكون</th>
                                <th style="width: 120px;">الكمية الصافية</th>
                                <th style="width: 140px;">وحدة القياس</th>
                                <th style="width: 110px;">الهدر %</th>
                                <th style="width: 130px;">الكمية المخططة</th>
                                <th>ملاحظات</th>
                                <th style="width: 50px;" class="text-center">إلغاء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr>
                                    <td class="text-center fw-bold text-muted" x-text="index + 1"></td>

                                    {{-- Type selector --}}
                                    <td>
                                        <select :name="`items[${index}][item_type]`" class="form-select form-select-sm" x-model="item.item_type">
                                            <option value="MATERIAL">مادة خام</option>
                                            <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع</option>
                                        </select>
                                    </td>

                                    {{-- Material or Component Selector --}}
                                    <td>
                                        <div x-show="item.item_type === 'MATERIAL'">
                                            <select :name="`items[${index}][material_id]`" class="form-select form-select-sm" x-model="item.material_id" @change="onMaterialSelect(item)">
                                                <option value="">-- اختر المادة الخام --</option>
                                                @foreach($materials as $mat)
                                                    <option value="{{ $mat->id }}" data-unit="{{ $mat->base_unit_id }}">{{ $mat->name_ar }} ({{ $mat->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div x-show="item.item_type === 'SEMI_FINISHED_COMPONENT'">
                                            <select :name="`items[${index}][semi_finished_component_id]`" class="form-select form-select-sm" x-model="item.semi_finished_component_id">
                                                <option value="">-- اختر المكون نصف المصنع --</option>
                                                @foreach($components as $comp)
                                                    <option value="{{ $comp->id }}">{{ $comp->name_ar }} ({{ $comp->component_code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>

                                    {{-- Net Quantity --}}
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" :name="`items[${index}][quantity]`" class="form-control form-control-sm text-center fw-bold" x-model.number="item.quantity" placeholder="0.00">
                                    </td>

                                    {{-- Unit --}}
                                    <td>
                                        <select :name="`items[${index}][unit_id]`" class="form-select form-select-sm" x-model="item.unit_id">
                                            <option value="">-- الوحدة --</option>
                                            @foreach($units as $u)
                                                <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->code }})</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    {{-- Waste % --}}
                                    <td>
                                        <input type="number" step="0.1" min="0" max="100" :name="`items[${index}][waste_percentage]`" class="form-control form-control-sm text-center" x-model.number="item.waste_percentage" placeholder="0%">
                                    </td>

                                    {{-- Calculated Planned Quantity --}}
                                    <td class="text-center font-monospace fw-bold text-primary">
                                        <span x-text="calculatePlannedQuantity(item).toFixed(3)"></span>
                                    </td>

                                    {{-- Notes --}}
                                    <td>
                                        <input type="text" :name="`items[${index}][notes]`" class="form-control form-control-sm" x-model="item.notes" placeholder="ملاحظات البند...">
                                    </td>

                                    {{-- Remove --}}
                                    <td class="text-center">
                                        <button type="button" @click="removeRow(index)" class="btn btn-sm btn-link text-danger p-0" title="حذف">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="items.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted">
                                    لم يتم إضافة بنود حتى الآن. اضغط "إضافة مادة خام" أو "إضافة مكون نصف مصنع" للبدء.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-4 fw-bold" :disabled="items.length === 0">
                <i class="fas fa-save me-1"></i> حفظ مسودة الوصفة (Save Draft V1)
            </button>
        </div>
    </form>
</div>

<script>
function recipeForm() {
    return {
        targetType: 'PRODUCT_CONFIGURATION',
        productConfigId: '',
        componentId: '',
        recipeName: '',
        items: [
            { item_type: 'MATERIAL', material_id: '', semi_finished_component_id: '', quantity: 1, unit_id: '', waste_percentage: 0, notes: '' }
        ],
        onConfigChange() {
            const select = document.querySelector('select[name="product_configuration_id"]');
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.name) {
                this.recipeName = selectedOpt.dataset.name;
            }
        },
        onComponentChange() {
            const select = document.querySelector('select[name="semi_finished_component_id"]');
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.name) {
                this.recipeName = selectedOpt.dataset.name;
            }
        },
        onMaterialSelect(item) {
            // Automatically preselect base unit of material if available
            const select = event.target;
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                item.unit_id = selectedOpt.dataset.unit;
            }
        },
        addRow(type = 'MATERIAL') {
            this.items.push({
                item_type: type,
                material_id: '',
                semi_finished_component_id: '',
                quantity: 1,
                unit_id: '',
                waste_percentage: 0,
                notes: ''
            });
        },
        removeRow(index) {
            this.items.splice(index, 1);
        },
        calculatePlannedQuantity(item) {
            const qty = parseFloat(item.quantity) || 0;
            const waste = parseFloat(item.waste_percentage) || 0;
            return qty * (1 + (waste / 100));
        }
    };
}
</script>
@endsection
