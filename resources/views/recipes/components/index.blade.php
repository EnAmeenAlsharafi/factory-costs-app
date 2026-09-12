@extends('layouts.app')

@section('title', 'المكونات نصف المصنعة')

@section('content')
<div class="container-fluid px-4 py-4" x-data="{ showCreateModal: false, showEditModal: false, editComponent: {} }">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">المكونات نصف المصنعة (Semi-Finished Components)</h1>
            <p class="text-muted mb-0 fs-7">تعريف أجزاء الإنتاج التجميعية المعاد استخدامها (مثل بوكسات الأسرّة العادية والسحارة)</p>
        </div>
        <div>
            @can('semi_finished_components.manage')
                <button type="button" @click="showCreateModal = true" class="btn btn-warning px-3 fw-semibold">
                    <i class="fas fa-plus me-1"></i> إضافة مكون نصف مصنع
                </button>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('recipes.components.index') }}" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="بحث باسم المكون أو كود SFC..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('recipes.components.index') }}" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Components Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">كود المكون</th>
                            <th>اسم المكون</th>
                            <th>الأبعاد سم</th>
                            <th>سحارة (تخزين)</th>
                            <th>الوصفة التصنيعية الخاصة</th>
                            <th>الحالة</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($components as $comp)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $comp->component_code }}</td>
                                <td class="fw-bold text-dark">{{ $comp->name_ar }}</td>
                                <td>
                                    @if($comp->width_cm && $comp->length_cm)
                                        <span class="badge bg-light text-dark border">{{ $comp->width_cm }} × {{ $comp->length_cm }} سم</span>
                                    @else
                                        <span class="text-muted fs-7">غير محدد</span>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->has_storage)
                                        <span class="badge bg-warning text-dark">سحارة</span>
                                    @else
                                        <span class="badge bg-light text-muted border">عادي</span>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->recipe && $comp->recipe->currentApprovedVersion)
                                        <a href="{{ route('recipes.show', $comp->recipe) }}" class="badge bg-success text-decoration-none">
                                            <i class="fas fa-scroll me-1"></i> {{ $comp->recipe->recipe_code }} (V{{ $comp->recipe->currentApprovedVersion->version_number }})
                                        </a>
                                    @elseif($comp->recipe)
                                        <a href="{{ route('recipes.show', $comp->recipe) }}" class="badge bg-warning text-dark text-decoration-none">
                                            <i class="fas fa-edit me-1"></i> مسودة قيد الاعتماد
                                        </a>
                                    @else
                                        <span class="text-muted fs-7">لا توجد وصفة معرفة بعد</span>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">معطل</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @can('semi_finished_components.manage')
                                        <button type="button" @click="editComponent = {{ json_encode($comp) }}; showEditModal = true" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="fas fa-edit me-1"></i> تعديل
                                        </button>
                                        <form action="{{ route('recipes.components.toggle-status', $comp) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $comp->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $comp->is_active ? 'تعطيل' : 'تفعيل' }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-cubes fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد مكونات نصف مصنعة معرفة حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($components->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $components->links() }}
            </div>
        @endif
    </div>

    {{-- Create Modal --}}
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" x-show="showCreateModal" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('recipes.components.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus me-1"></i> إضافة مكون نصف مصنع جديد</h5>
                        <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">اسم المكون (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control" placeholder="مثل: بوكس 160×200 عادي" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">اسم المكون (بالإنجليزية)</label>
                            <input type="text" name="name_en" class="form-control" placeholder="English Name">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-7">العرض سم</label>
                                <input type="number" step="0.1" name="width_cm" class="form-control" placeholder="160.0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-7">الطول سم</label>
                                <input type="number" step="0.1" name="length_cm" class="form-control" placeholder="200.0">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="has_storage" value="1" id="createHasStorage">
                            <label class="form-check-label fw-bold fs-7" for="createHasStorage">يحتوي سحارة / تخزين هيدروليكي</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات المواصفات..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" @click="showCreateModal = false">إلغاء</button>
                        <button type="submit" class="btn btn-warning fw-bold">حفظ المكون</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" x-show="showEditModal" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form :action="`/recipes/components/${editComponent.id}`" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-edit me-1"></i> تعديل بيانات المكون نصف المصنع</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showEditModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">اسم المكون (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control" x-model="editComponent.name_ar" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">اسم المكون (بالإنجليزية)</label>
                            <input type="text" name="name_en" class="form-control" x-model="editComponent.name_en">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-7">العرض سم</label>
                                <input type="number" step="0.1" name="width_cm" class="form-control" x-model="editComponent.width_cm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-7">الطول سم</label>
                                <input type="number" step="0.1" name="length_cm" class="form-control" x-model="editComponent.length_cm">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="has_storage" value="1" :checked="editComponent.has_storage">
                            <label class="form-check-label fw-bold fs-7">يحتوي سحارة / تخزين هيدروليكي</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-7">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2" x-model="editComponent.notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" @click="showEditModal = false">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold">تحديث البيانات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
