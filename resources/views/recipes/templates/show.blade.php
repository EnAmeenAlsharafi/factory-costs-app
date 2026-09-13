@extends('layouts.app')

@section('title', 'تفاصيل القالب - ' . $template->template_code)

@section('content')
<div class="container-fluid px-4 py-4" x-data="{ showRecipeModal: false }">

    <div class="mb-3">
        <a href="{{ route('recipes.templates.index') }}" class="text-decoration-none text-muted fs-7">
            <i class="fas fa-arrow-right me-1"></i> قائمة قوالب التصنيع
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="badge bg-dark font-monospace fs-6 mb-2 px-3 py-2">{{ $template->template_code }}</span>
                    <h2 class="h3 mb-2 text-dark fw-bold">{{ $template->name_ar }}</h2>
                    <p class="text-muted mb-0 fs-7">{{ $template->description ?: 'لا يوجد وصف مدون' }}</p>
                </div>
                <div>
                    @can('recipes.manage')
                        <button type="button" @click="showRecipeModal = true" class="btn btn-warning px-3 fw-bold">
                            <i class="fas fa-magic me-1"></i> إنشاء وصفة جديدة من هذا القالب
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom-0">
            <h5 class="card-title mb-0 text-dark fw-bold"><i class="fas fa-list-ol text-warning me-2"></i> بنود خامات القالب المسبقة</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;" class="ps-3 text-center">#</th>
                            <th>نوع البند</th>
                            <th>المادة الخام / المكون</th>
                            <th>الكمية الصافية</th>
                            <th>الوحدة</th>
                            <th>الهدر %</th>
                            <th>الكمية المخططة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($template->items as $index => $item)
                            <tr>
                                <td class="ps-3 text-center text-muted fw-bold">{{ $index + 1 }}</td>
                                <td>
                                    @if($item->item_type === 'MATERIAL')
                                        <span class="badge bg-info text-dark">مادة خام</span>
                                    @else
                                        <span class="badge bg-purple text-white" style="background-color: #6f42c1;">مكون نصف مصنع</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->item_type === 'MATERIAL' && $item->material)
                                        <div class="fw-bold text-dark">{{ $item->material->name_ar }}</div>
                                        <small class="text-muted font-monospace">{{ $item->material->code }}</small>
                                    @elseif($item->item_type === 'SEMI_FINISHED_COMPONENT' && $item->semiFinishedComponent)
                                        <div class="fw-bold text-dark">{{ $item->semiFinishedComponent->name_ar }}</div>
                                        <small class="text-muted font-monospace">{{ $item->semiFinishedComponent->component_code }}</small>
                                    @endif
                                </td>
                                <td class="font-monospace fw-bold fs-6">{{ number_format($item->quantity, 4) }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $item->unit?->name_ar }} ({{ $item->unit?->code }})</span></td>
                                <td>
                                    @if($item->waste_percentage > 0)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ number_format($item->waste_percentage, 1) }}%</span>
                                    @else
                                        <span class="text-muted">0%</span>
                                    @endif
                                </td>
                                <td class="font-monospace fw-bold text-primary fs-6">{{ number_format($item->planned_quantity, 4) }}</td>
                                <td class="fs-7 text-muted">{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">لا توجد بنود مسجلة في هذا القالب.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: Create Recipe from Template --}}
    <div class="modal fade show" tabindex="-1" style="background: rgba(0,0,0,0.5);" x-show="showRecipeModal" :class="{ 'd-block': showRecipeModal }" @click.self="showRecipeModal = false" @keydown.escape.window="showRecipeModal = false" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('recipes.templates.create-recipe', $template) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="fas fa-magic me-1"></i> إنشاء وصفة جديدة من القالب</h5>
                        <button type="button" class="btn-close" @click="showRecipeModal = false"></button>
                    </div>
                    <div class="modal-body" x-data="{ targetType: 'PRODUCT_CONFIGURATION' }">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">نوع الهدف المصنع</label>
                            <select name="target_type" class="form-select" x-model="targetType">
                                <option value="PRODUCT_CONFIGURATION">تكوين منتج نهائي</option>
                                <option value="SEMI_FINISHED_COMPONENT">مكون نصف مصنع</option>
                            </select>
                        </div>

                        <div class="mb-3" x-show="targetType === 'PRODUCT_CONFIGURATION'">
                            <label class="form-label fw-bold fs-7">تكوين المنتج النهائي</label>
                            <select name="product_configuration_id" class="form-select">
                                <option value="">-- اختر التكوين المصنعي --</option>
                                @foreach($configurations as $cfg)
                                    <option value="{{ $cfg->id }}">{{ $cfg->productModel?->name_ar }} | {{ $cfg->width_cm }} × {{ $cfg->length_cm }} سم ({{ $cfg->has_storage ? 'سحارة' : 'بدون تخزين' }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" x-show="targetType === 'SEMI_FINISHED_COMPONENT'">
                            <label class="form-label fw-bold fs-7">المكون نصف المصنع</label>
                            <select name="semi_finished_component_id" class="form-select">
                                <option value="">-- اختر المكون نصف المصنع --</option>
                                @foreach($components as $comp)
                                    <option value="{{ $comp->id }}">{{ $comp->name_ar }} ({{ $comp->component_code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">اسم الوصفة الجديدة <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="وصفة مستنسخة من {{ $template->name_ar }}" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" @click="showRecipeModal = false">إلغاء</button>
                        <button type="submit" class="btn btn-warning fw-bold">توليد مسودة الوصفة V1</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
