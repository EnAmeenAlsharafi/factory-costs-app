@extends('layouts.app')

@section('title', 'تعديل مسودة الوصفة (V' . $version->version_number . ')')

@section('content')
<div class="container-fluid px-4 py-4" x-data="recipeEditForm()">

    {{-- Header --}}
    <div class="mb-4">
        <a href="{{ route('recipes.show', $recipe) }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لصفحة الوصفة {{ $recipe->recipe_code }}
        </a>
        <h1 class="h3 mb-1 text-dark fw-bold">تعديل مسودة الوصفة - الإصدار (V{{ $version->version_number }})</h1>
        <p class="text-muted mb-0 fs-7">الوصفة: {{ $recipe->name }} ({{ $recipe->recipe_code }})</p>
    </div>

    {{-- Flash Errors --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('recipes.versions.update', [$recipe, $version]) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Version Notes Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold fs-7">ملاحظات هذا الإصدار (V{{ $version->version_number }})</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes', $version->notes) }}" placeholder="ملاحظات حول هذا الإصدار...">
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Table Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                <div>
                    <h5 class="card-title mb-0 text-dark fw-bold"><i class="fas fa-list-ol text-warning me-2"></i> بنود الخامات والمكونات (V{{ $version->version_number }})</h5>
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
                                <th style="width: 50px;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr>
                                    <td class="text-center fw-bold text-muted" x-text="index + 1"></td>
                                    <td>
                                        <select :name="`items[${index}][item_type]`" class="form-select form-select-sm" x-model="item.item_type">
                                            <option value="MATERIAL">مادة خام</option>
                                            <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div x-show="item.item_type === 'MATERIAL'">
                                            <select :name="`items[${index}][material_id]`" class="form-select form-select-sm" x-model="item.material_id">
                                                <option value="">-- اختر المادة الخام --</option>
                                                @foreach($materials as $mat)
                                                    <option value="{{ $mat->id }}">{{ $mat->name_ar }} ({{ $mat->code }})</option>
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
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" :name="`items[${index}][quantity]`" class="form-control form-control-sm text-center fw-bold" x-model.number="item.quantity">
                                    </td>
                                    <td>
                                        <select :name="`items[${index}][unit_id]`" class="form-select form-select-sm" x-model="item.unit_id">
                                            <option value="">-- الوحدة --</option>
                                            @foreach($units as $u)
                                                <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->code }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.1" min="0" max="100" :name="`items[${index}][waste_percentage]`" class="form-control form-control-sm text-center" x-model.number="item.waste_percentage">
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-primary">
                                        <span x-text="calculatePlannedQuantity(item).toFixed(3)"></span>
                                    </td>
                                    <td>
                                        <input type="text" :name="`items[${index}][notes]`" class="form-control form-control-sm" x-model="item.notes">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" @click="removeRow(index)" class="btn btn-sm btn-link text-danger p-0">
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

        {{-- Form Actions --}}
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-outline-secondary px-4">إلغاء</a>
            <button type="submit" class="btn btn-warning px-4 fw-bold">
                <i class="fas fa-save me-1"></i> حفظ التحديثات المسودة
            </button>
        </div>
    </form>
</div>

<script>
function recipeEditForm() {
    return {
        items: @json($version->items->map(function($i) {
            return [
                'item_type' => $i->item_type,
                'material_id' => $i->material_id ? (string) $i->material_id : '',
                'semi_finished_component_id' => $i->semi_finished_component_id ? (string) $i->semi_finished_component_id : '',
                'quantity' => (float) $i->quantity,
                'unit_id' => (string) $i->unit_id,
                'waste_percentage' => (float) $i->waste_percentage,
                'notes' => $i->notes ?? '',
            ];
        })),
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
