@extends('layouts.app')

@section('title', 'تعديل بكج - ' . $bundle->name)

@section('content')
<div class="container-fluid px-4 py-4" x-data="bundleEditForm()">

    <div class="mb-4">
        <a href="{{ route('sales.bundles.index') }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لقائمة البكجات
        </a>
        <h1 class="h3 mb-1 text-dark fw-bold">تعديل بكج: {{ $bundle->name }}</h1>
        <p class="text-muted mb-0 fs-7">تعديل بيانات البكج ومكوناته</p>
    </div>

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

    <form action="{{ route('sales.bundles.update', $bundle) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold fs-7">اسم البكج <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold" value="{{ old('name', $bundle->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold fs-7">SKU البكج (اختياري)</label>
                        <input type="text" name="sales_sku" class="form-control" dir="ltr" value="{{ old('sales_sku', $bundle->sales_sku) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold fs-7">الحالة</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active', $bundle->is_active) ? 'selected' : '' }}>نشط</option>
                            <option value="0" {{ !old('is_active', $bundle->is_active) ? 'selected' : '' }}>معطل</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                <h5 class="card-title mb-0 text-dark fw-bold"><i class="fas fa-box-open text-warning me-2"></i> مكونات البكج</h5>
                <div class="d-flex gap-2">
                    <button type="button" @click="addRow('MANUFACTURED')" class="btn btn-sm btn-outline-primary fw-semibold"><i class="fas fa-plus me-1"></i> إضافة سرير مصنّع</button>
                    <button type="button" @click="addRow('READY_STOCK')" class="btn btn-sm btn-outline-success fw-semibold"><i class="fas fa-plus me-1"></i> إضافة منتج جاهز</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 150px;">نوع المكوّن</th>
                                <th>الموديل / المنتج</th>
                                <th style="width: 120px;">الكمية</th>
                                <th style="width: 50px;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in components" :key="index">
                                <tr>
                                    <td class="text-center fw-bold text-muted" x-text="index + 1"></td>
                                    <td>
                                        <select :name="`components[${index}][component_type]`" class="form-select form-select-sm" x-model="item.component_type">
                                            <option value="MANUFACTURED">سرير مصنّع</option>
                                            <option value="READY_STOCK">منتج جاهز</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div x-show="item.component_type === 'MANUFACTURED'">
                                            <select :name="`components[${index}][product_configuration_id]`" class="form-select form-select-sm" x-model="item.product_configuration_id" @change="onConfigChange(index, $event)">
                                                <option value="">-- اختر الموديل والمقاس --</option>
                                                @foreach($configurations as $cfg)
                                                    <option value="{{ $cfg->id }}" data-model-id="{{ $cfg->product_model_id }}">
                                                        {{ $cfg->productModel->name_ar }} — {{ $cfg->width_cm }}×{{ $cfg->length_cm }} {{ $cfg->has_storage ? '(بتخزين)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" :name="`components[${index}][product_model_id]`" x-model="item.product_model_id">
                                        </div>
                                        <div x-show="item.component_type === 'READY_STOCK'">
                                            <select :name="`components[${index}][material_id]`" class="form-select form-select-sm" x-model="item.material_id">
                                                <option value="">-- اختر المنتج الجاهز --</option>
                                                @foreach($materials as $mat)
                                                    <option value="{{ $mat->id }}">{{ $mat->sales_display_name ?: $mat->name_ar }} ({{ $mat->sales_sku ?: $mat->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" :name="`components[${index}][quantity]`" class="form-control form-control-sm text-center" x-model.number="item.quantity">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" @click="removeRow(index)" class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('sales.bundles.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-4 fw-bold" :disabled="components.length === 0">
                <i class="fas fa-save me-1"></i> حفظ التعديلات
            </button>
        </div>
    </form>
</div>

<script>
function bundleEditForm() {
    return {
        components: @json(old('components', $initialComponents)),
        addRow(type = 'MANUFACTURED') {
            this.components.push({ component_type: type, product_model_id: '', product_configuration_id: '', material_id: '', quantity: 1 });
        },
        removeRow(index) {
            this.components.splice(index, 1);
        },
        onConfigChange(index, event) {
            const select = event.target;
            const opt = select.options[select.selectedIndex];
            this.components[index].product_model_id = opt?.dataset.modelId || '';
        }
    };
}
</script>
@endsection
