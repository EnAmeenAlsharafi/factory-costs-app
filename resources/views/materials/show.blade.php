@extends('layouts.app')

@section('title', 'تفاصيل المادة الخام - ' . $material->name_ar)
@section('page-title', 'تفاصيل ومواصفات المادة الخام')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header -->
    @include('partials.page-header', [
        'title' => $material->name_ar,
        'subtitle' => 'كود المادة: ' . $material->code . ' | التصنيف: ' . ($material->category->name_ar ?? '-'),
        'icon' => 'fas fa-box-open',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'كتالوج المواد الخام', 'url' => route('materials.index')],
            ['title' => $material->code]
        ],
        'actionUrl' => route('materials.edit', $material),
        'actionText' => 'تعديل البيانات',
        'actionIcon' => 'fas fa-edit',
        'actionPermission' => 'materials.manage'
    ])

    <!-- Overview Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-info-circle text-primary me-2"></i>معلومات المادة الخام</h5>
            <div>
                @if ($material->is_active)
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 fs-7">
                        <i class="fas fa-check-circle me-1"></i> نشط
                    </span>
                @else
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1.5 fs-7">
                        <i class="fas fa-ban me-1"></i> معطل
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">كود المادة</span>
                    <code class="code-badge fs-6">{{ $material->code }}</code>
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">التصنيف</span>
                    <span class="fw-bold text-dark">{{ $material->category->name_ar ?? '-' }}</span>
                    <span class="badge bg-light text-muted border ms-1">{{ $material->category->code ?? '' }}</span>
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">الوحدة الأساسية (الصرف والإنتاج)</span>
                    <span class="fw-bold text-dark">{{ $material->baseUnit->name_ar ?? '-' }}</span>
                    <span class="text-muted fs-8">({{ $material->baseUnit->symbol ?? $material->baseUnit->code }})</span>
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">وحدة الشراء الافتراضية</span>
                    @if ($material->purchaseUnit)
                        <span class="fw-bold text-primary">{{ $material->purchaseUnit->name_ar }}</span>
                        <span class="text-muted fs-8">({{ $material->purchaseUnit->symbol ?? $material->purchaseUnit->code }})</span>
                    @else
                        <span class="text-muted fs-7">نفس الوحدة الأساسية</span>
                    @endif
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">الاسم بالإنجليزية</span>
                    <span dir="ltr" class="text-dark">{{ $material->name_en ?? '-' }}</span>
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">الحد الأدنى للمخزون</span>
                    <span class="fw-semibold text-dark">{{ number_format($material->min_stock_level, 2) }} {{ $material->baseUnit->symbol ?? '' }}</span>
                </div>

                <div class="col-md-3 col-6">
                    <span class="text-muted fs-8 d-block mb-1">نقطة إعادة الطلب</span>
                    <span class="fw-semibold text-primary">{{ number_format($material->reorder_point, 2) }} {{ $material->baseUnit->symbol ?? '' }}</span>
                </div>

                <div class="col-md-3 col-12">
                    <span class="text-muted fs-8 d-block mb-1">ملاحظات</span>
                    <span class="text-dark fs-7">{{ $material->notes ?? 'لا توجد ملاحظات مسجلة.' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Specifications Card -->
    @if ($material->category?->code === 'WOOD' && $material->woodSpec)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-tree text-success me-2"></i>المواصفات الفنية للأخشاب والألواح</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">نوع الخشب</span>
                        <span class="fw-bold text-dark">{{ $material->woodSpec->wood_type }}</span>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">الدرجة / الجودة</span>
                        <span class="fw-semibold text-dark">{{ $material->woodSpec->grade ?? '-' }}</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">السمك (مم)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->woodSpec->thickness_mm }} مم</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">العرض (سم)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->woodSpec->width_cm }} سم</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">الطول (سم)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->woodSpec->length_cm }} سم</span>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($material->category?->code === 'FOAM' && $material->foamSpec)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-cubes text-warning me-2"></i>المواصفات الفنية للإسفنج والكتل</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">نوع الإسفنج</span>
                        <span class="fw-bold text-dark">{{ $material->foamSpec->foam_type }}</span>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">الكثافة (كجم/م³)</span>
                        <span class="fw-semibold text-dark">{{ $material->foamSpec->density_kg_m3 ? number_format($material->foamSpec->density_kg_m3, 2) . ' كجم/م³' : '-' }}</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">السمك (مم)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->foamSpec->thickness_mm }} مم</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">الأبعاد الرقمية (سم)</span>
                        @if ($material->foamSpec->width_cm && $material->foamSpec->length_cm)
                            <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->foamSpec->width_cm }} × {{ $material->foamSpec->length_cm }} سم</span>
                        @else
                            <span class="text-muted fs-8">-</span>
                        @endif
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">القساوة / الصلابة</span>
                        <span class="badge bg-light text-dark border fs-7">{{ $material->foamSpec->hardness_rating ?? '-' }}</span>
                    </div>
                    @if ($material->foamSpec->block_dimensions)
                        <div class="col-12 border-top pt-2">
                            <span class="text-muted fs-8 d-block mb-1">وصف البلوك الإضافي</span>
                            <span class="text-dark fs-7">{{ $material->foamSpec->block_dimensions }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($material->category?->code === 'FABRIC' && $material->fabricSpec)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-scroll text-info me-2"></i>المواصفات الفنية للأقمشة والجلد</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">نوع القماش</span>
                        <span class="fw-bold text-dark">{{ $material->fabricSpec->fabric_type }}</span>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-8 d-block mb-1">عرض الرول (سم)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->fabricSpec->width_cm }} سم</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">النقش / النمط</span>
                        <span class="fw-semibold text-dark">{{ $material->fabricSpec->pattern_type ?? '-' }}</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">الوزن (GSM)</span>
                        <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->fabricSpec->weight_gsm ? number_format($material->fabricSpec->weight_gsm, 0) . ' gsm' : '-' }}</span>
                    </div>
                    <div class="col-md-2 col-4">
                        <span class="text-muted fs-8 d-block mb-1">التركيب الخارجي</span>
                        <span class="text-dark fs-7">{{ $material->fabricSpec->composition ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Fabric Colors Section (Only for Fabric materials) -->
    @if ($material->category?->code === 'FABRIC')
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-palette text-danger me-2"></i>درجات الألوان المتوفرة للقماش</h5>
                @can('materials.manage')
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#add-color-form">
                        <i class="fas fa-plus me-1"></i> إضافة لون جديد
                    </button>
                @endcan
            </div>
            <div class="card-body p-4">

                @can('materials.manage')
                    <div class="collapse mb-4" id="add-color-form">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold mb-3 text-primary fs-7">إضافة درجة لون جديدة</h6>
                            <form action="{{ route('fabric-colors.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="material_id" value="{{ $material->id }}">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <input type="text" name="color_code" class="form-control form-control-sm" placeholder="كود اللون *" required dir="ltr">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="color_name_ar" class="form-control form-control-sm" placeholder="اسم اللون (عربي) *" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" name="color_name_en" class="form-control form-control-sm" placeholder="الاسم بالإنجليزية" dir="ltr">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="color" name="hex_code" class="form-control form-control-sm form-control-color w-100" value="#336699" title="اختر رمز اللون">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" name="pattern" class="form-control form-control-sm" placeholder="النقشة">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">حفظ</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رمز اللون (HEX)</th>
                                <th>كود اللون</th>
                                <th>اسم اللون (عربي)</th>
                                <th>الاسم بالإنجليزية</th>
                                <th>النقشة</th>
                                <th>الحالة</th>
                                @can('materials.manage')
                                    <th class="text-center">إجراء</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($material->fabricColors as $color)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle border shadow-sm" style="width: 24px; height: 24px; background-color: {{ $color->hex_code ?? '#cccccc' }};"></span>
                                            <code class="code-badge fs-8">{{ $color->hex_code ?? '-' }}</code>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold text-dark">{{ $color->color_code }}</span></td>
                                    <td><span class="fw-semibold text-dark">{{ $color->color_name_ar }}</span></td>
                                    <td><span dir="ltr" class="text-muted fs-7">{{ $color->color_name_en ?? '-' }}</span></td>
                                    <td><span class="text-muted fs-8">{{ $color->pattern ?? '-' }}</span></td>
                                    <td>
                                        @if ($color->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fs-8">نشط</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-muted border px-2 py-0.5 fs-8">معطل</span>
                                        @endif
                                    </td>
                                    @can('materials.manage')
                                        <td class="text-center">
                                            <form action="{{ route('fabric-colors.destroy', $color) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger p-1" onclick="return confirm('حذف هذا اللون؟');" title="حذف">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        لا توجد درجات ألوان مسجلة لهذا القماش.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Linked Suppliers Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-truck text-secondary me-2"></i>الموردون المعتمدون للمادة الخام</h5>
            @can('materials.manage')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#add-supplier-form">
                    <i class="fas fa-plus me-1"></i> ربط مورد جديد
                </button>
            @endcan
        </div>
        <div class="card-body p-4">

            @can('materials.manage')
                <div class="collapse mb-4" id="add-supplier-form">
                    <div class="p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3 text-primary fs-7">ربط مورد معتمد بهذه المادة الخام</h6>
                        <form action="{{ route('material-suppliers.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="material_id" value="{{ $material->id }}">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <select name="supplier_id" class="form-select form-select-sm" required>
                                        <option value="">-- اختر المورد --</option>
                                        @foreach ($allSuppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->name_ar }} ({{ $sup->supplier_code ?? $sup->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="supplier_item_code" class="form-control form-control-sm" placeholder="كود المادة لدى المورد" dir="ltr">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="lead_time_days" class="form-control form-control-sm" placeholder="التوريد (أيام)" min="0">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" step="0.01" name="minimum_order_qty" class="form-control form-control-sm" placeholder="الحد الأدنى للطلب" min="0">
                                </div>
                                <div class="col-md-2 d-flex align-items-center">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_preferred_new" name="is_preferred" value="1">
                                        <label class="form-check-label fs-8 me-1" for="is_preferred_new">مفضل</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary ms-auto">ربط</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>كود المورد</th>
                            <th>اسم المورد</th>
                            <th>كود المادة لدى المورد</th>
                            <th>مدة التوريد (أيام)</th>
                            <th>الحد الأدنى للطلب</th>
                            <th>المورد المفضل</th>
                            @can('materials.manage')
                                <th class="text-center">إجراء</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($material->suppliers as $supplier)
                            <tr>
                                <td><code class="code-badge fs-8">{{ $supplier->supplier_code ?? $supplier->code }}</code></td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $supplier->name_ar ?? $supplier->name }}</span>
                                    @if ($supplier->commercial_name)
                                        <span class="text-muted fs-8 d-block">{{ $supplier->commercial_name }}</span>
                                    @endif
                                </td>
                                <td><span dir="ltr" class="text-dark font-monospace fs-7">{{ $supplier->pivot->supplier_item_code ?? '-' }}</span></td>
                                <td><span class="fw-semibold text-dark">{{ $supplier->pivot->lead_time_days ? $supplier->pivot->lead_time_days . ' يوم' : '-' }}</span></td>
                                <td><span class="fw-semibold text-dark">{{ $supplier->pivot->minimum_order_qty ? number_format($supplier->pivot->minimum_order_qty, 2) : '-' }}</span></td>
                                <td>
                                    @if ($supplier->pivot->is_preferred)
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1">
                                            <i class="fas fa-star text-warning me-1"></i> مفضل
                                        </span>
                                    @else
                                        <span class="text-muted fs-8">-</span>
                                    @endif
                                </td>
                                @can('materials.manage')
                                    <td class="text-center">
                                        <form action="{{ route('material-suppliers.destroy', [$material, $supplier->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger p-1" onclick="return confirm('إلغاء ربط هذا المورد؟');" title="إلغاء الربط">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    لا يوجد موردون مرتبطون بهذه المادة الخام حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Material Unit Conversions Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-exchange-alt text-primary me-2"></i>تحويلات الوحدات الخاصة بالمادة</h5>
            @can('materials.manage')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#add-conversion-form">
                    <i class="fas fa-plus me-1"></i> إضافة تحويل وحدات
                </button>
            @endcan
        </div>
        <div class="card-body p-4">

            @can('materials.manage')
                <div class="collapse mb-4" id="add-conversion-form">
                    <div class="p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3 text-primary fs-7">تعريف معامل تحويل وحدات جديد للمادة</h6>
                        <form action="{{ route('material-conversions.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="material_id" value="{{ $material->id }}">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label fs-8 mb-1">من وحدة</label>
                                    <select name="from_unit_id" class="form-select form-select-sm" required>
                                        <option value="">-- اختر الوحدة --</option>
                                        @foreach ($allUnits as $u)
                                            <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-8 mb-1">إلى وحدة (عادة الوحدة الأساسية)</label>
                                    <select name="to_unit_id" class="form-select form-select-sm" required>
                                        <option value="">-- اختر الوحدة --</option>
                                        @foreach ($allUnits as $u)
                                            <option value="{{ $u->id }}" {{ $u->id == $material->base_unit_id ? 'selected' : '' }}>
                                                {{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-8 mb-1">معامل التحويل (Conversion Factor)</label>
                                    <input type="number" step="0.0001" name="conversion_factor" class="form-control form-control-sm" placeholder="مثال: 50" required min="0.0001">
                                </div>
                                <div class="col-md-3 pt-3">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">حفظ معامل التحويل</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الوحدة المصدر (From)</th>
                            <th>معامل التحويل</th>
                            <th>الوحدة الهدف (To)</th>
                            <th>صيغة المعادلة</th>
                            @can('materials.manage')
                                <th class="text-center">إجراء</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($material->unitConversions as $conv)
                            <tr>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        {{ $conv->fromUnit->name_ar }} ({{ $conv->fromUnit->symbol ?? $conv->fromUnit->code }})
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary font-monospace fs-6">= {{ number_format($conv->conversion_factor, 4) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        {{ $conv->toUnit->name_ar }} ({{ $conv->toUnit->symbol ?? $conv->toUnit->code }})
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-8">
                                        1 {{ $conv->fromUnit->name_ar }} = {{ number_format($conv->conversion_factor, 4) }} {{ $conv->toUnit->name_ar }}
                                    </span>
                                </td>
                                @can('materials.manage')
                                    <td class="text-center">
                                        <form action="{{ route('material-conversions.destroy', $conv) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger p-1" onclick="return confirm('حذف هذا التحويل؟');" title="حذف">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    لا توجد تحويلات وحدات خاصة مسجلة لهذه المادة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
