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
        <!-- Fabric Catalog & Technical Specs Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-book-open text-primary me-2"></i>بيانات كتالوج ومواصفات القماش</h5>
                @if ($material->fabricSpec->supplier)
                    <a href="{{ route('suppliers.show', $material->fabricSpec->supplier) }}" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 fs-7 text-decoration-none">
                        <i class="fas fa-truck me-1"></i>المورد: {{ $material->fabricSpec->supplier->name }}
                    </a>
                @endif
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    @if ($material->fabricSpec->catalog_image_path)
                        <div class="col-md-3 col-12 text-center border-start">
                            <div class="position-relative d-inline-block">
                                <img src="{{ asset('storage/' . $material->fabricSpec->catalog_image_path) }}"
                                     alt="{{ $material->fabricSpec->catalog_name ?? $material->name_ar }}"
                                     class="img-fluid rounded-3 border shadow-sm"
                                     style="max-height: 180px; object-fit: cover;">
                                <span class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white px-2 py-0.5 rounded-bottom-3 fs-8">
                                    كتالوج {{ $material->fabricSpec->catalog_number }}
                                </span>
                            </div>
                        </div>
                    @endif

                    <div class="{{ $material->fabricSpec->catalog_image_path ? 'col-md-9 col-12' : 'col-12' }}">
                        <div class="row g-3">
                            <div class="col-md-4 col-6">
                                <span class="text-muted fs-8 d-block mb-1">المورد المعتمد للكتالوج</span>
                                <span class="fw-bold text-dark fs-6">{{ $material->fabricSpec->supplier?->name ?? 'غير محدد' }}</span>
                            </div>
                            <div class="col-md-4 col-6">
                                <span class="text-muted fs-8 d-block mb-1">رقم الكتالوج لدى المورد</span>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 fs-7 font-monospace px-2.5 py-1">
                                    {{ $material->fabricSpec->catalog_number ?? '-' }}
                                </span>
                            </div>
                            <div class="col-md-4 col-6">
                                <span class="text-muted fs-8 d-block mb-1">اسم الكتالوج التجاري</span>
                                <span class="fw-semibold text-dark">{{ $material->fabricSpec->catalog_name ?? '-' }}</span>
                            </div>

                            <div class="col-md-3 col-6">
                                <span class="text-muted fs-8 d-block mb-1">عائلة / نوع القماش</span>
                                <span class="fw-bold text-dark">{{ $material->fabricSpec->fabric_type }}</span>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted fs-8 d-block mb-1">عرض الرول (سم)</span>
                                <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->fabricSpec->width_cm }} سم</span>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted fs-8 d-block mb-1">النقش / النمط</span>
                                <span class="fw-semibold text-dark">{{ $material->fabricSpec->pattern_type ?? '-' }}</span>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted fs-8 d-block mb-1">الوزن (GSM)</span>
                                <span class="badge bg-light text-dark border font-monospace fs-7">{{ $material->fabricSpec->weight_gsm ? number_format($material->fabricSpec->weight_gsm, 0) . ' gsm' : '-' }}</span>
                            </div>

                            @if ($material->fabricSpec->composition)
                                <div class="col-md-6 col-12">
                                    <span class="text-muted fs-8 d-block mb-1">التركيب الخامي</span>
                                    <span class="text-dark fs-7">{{ $material->fabricSpec->composition }}</span>
                                </div>
                            @endif
                            @if ($material->fabricSpec->martindale_rub_count)
                                <div class="col-md-6 col-12">
                                    <span class="text-muted fs-8 d-block mb-1">مقاومة الاحتكاك (Martindale)</span>
                                    <span class="text-dark fs-7">{{ number_format($material->fabricSpec->martindale_rub_count) }} دورة</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Fabric Colors Section (Only for Fabric materials) -->
    @if ($material->category?->code === 'FABRIC')
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-1"><i class="fas fa-palette text-primary me-2"></i>درجات ألوان الكتالوج</h5>
                    <p class="text-muted fs-8 mb-0">يرتبط كل لون برقم داخلي (لخدمة العملاء) وكود مورد (للمشتريات والإنتاج).</p>
                </div>
                @can('materials.manage')
                    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#add-color-form">
                        <i class="fas fa-plus me-1"></i> إضافة لون جديد
                    </button>
                @endcan
            </div>
            <div class="card-body p-4">

                @can('materials.manage')
                    <div class="collapse mb-4" id="add-color-form">
                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold mb-3 text-primary fs-7"><i class="fas fa-plus-circle me-1"></i>إضافة درجة لون جديدة للكتالوج</h6>
                            <form action="{{ route('fabric-colors.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="material_id" value="{{ $material->id }}">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2 col-6">
                                        <label class="form-label fs-8 text-muted mb-1">الرقم الداخلي (خدمة العملاء) <span class="text-danger">*</span></label>
                                        <input type="text" name="color_code" class="form-control form-control-sm" placeholder="مثال: 1" required dir="ltr">
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label class="form-label fs-8 text-muted mb-1">كود المورد (المشتريات) <span class="text-danger">*</span></label>
                                        <input type="text" name="supplier_color_code" class="form-control form-control-sm" placeholder="مثال: {{ $material->fabricSpec?->catalog_number ? $material->fabricSpec->catalog_number . '-1' : '2020-1' }}" required dir="ltr">
                                    </div>
                                    <div class="col-md-3 col-12">
                                        <label class="form-label fs-8 text-muted mb-1">اسم اللون (اختياري)</label>
                                        <input type="text" name="color_name_ar" class="form-control form-control-sm" placeholder="مثال: رمادي فاتح">
                                    </div>
                                    <div class="col-md-1 col-3">
                                        <label class="form-label fs-8 text-muted mb-1">الرمز</label>
                                        <input type="color" name="hex_code" class="form-control form-control-sm form-control-color w-100 p-1" value="#6c757d" title="اختر رمز اللون">
                                    </div>
                                    <div class="col-md-2 col-5">
                                        <label class="form-label fs-8 text-muted mb-1">النقشة (اختياري)</label>
                                        <input type="text" name="pattern" class="form-control form-control-sm" placeholder="سادة">
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                            <i class="fas fa-check me-1"></i> حفظ اللون
                                        </button>
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
                                <th>معاينة</th>
                                <th>الرقم الداخلي (خدمة العملاء)</th>
                                <th>كود المورد والإنتاج</th>
                                <th>اسم اللون</th>
                                <th>النقشة</th>
                                <th>التوفر</th>
                                <th>الحالة</th>
                                @can('materials.manage')
                                    <th class="text-center">إجراءات</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($material->fabricColors as $color)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle border shadow-sm" style="width: 22px; height: 22px; background-color: {{ $color->hex_code ?? '#cccccc' }};"></span>
                                            @if ($color->hex_code)
                                                <code class="code-badge fs-8">{{ $color->hex_code }}</code>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace fs-7 px-2.5 py-1">
                                            {{ $color->color_code }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary font-monospace fs-7">
                                            {{ $color->supplier_color_code ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $color->color_name_ar }}</span>
                                        @if ($color->color_name_en)
                                            <span dir="ltr" class="text-muted fs-8 d-block">{{ $color->color_name_en }}</span>
                                        @endif
                                    </td>
                                    <td><span class="text-muted fs-8">{{ $color->pattern ?? '-' }}</span></td>
                                    <td>
                                        @if ($color->is_available)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fs-8">متوفر</span>
                                        @else
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-0.5 fs-8">غير متوفر</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($color->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fs-8">نشط</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-muted border px-2 py-0.5 fs-8">معطل</span>
                                        @endif
                                    </td>
                                    @can('materials.manage')
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <form action="{{ route('fabric-colors.toggle-status', $color) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="toggle_availability" value="1">
                                                    <button type="submit" class="btn btn-sm btn-light border p-1" title="{{ $color->is_available ? 'تعطيل التوفر' : 'تفعيل التوفر' }}">
                                                        <i class="fas fa-boxes-stacked {{ $color->is_available ? 'text-success' : 'text-muted' }}"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('fabric-colors.toggle-status', $color) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light border p-1" title="{{ $color->is_active ? 'تعطيل اللون' : 'تفعيل اللون' }}">
                                                        <i class="fas fa-power-off {{ $color->is_active ? 'text-success' : 'text-danger' }}"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('fabric-colors.destroy', $color) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light text-danger border p-1" onclick="return confirm('هل أنت متأكد من حذف هذا اللون؟ لا يمكن حذفه إذا كان مستخدماً في عمليات تشغيلية.');" title="حذف">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        لا توجد درجات ألوان مسجلة لهذا الكتالوج حتى الآن.
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
                                            <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->supplier_code ?? $sup->code }}){{ $sup->commercial_name && $sup->commercial_name !== $sup->name ? ' - ' . $sup->commercial_name : '' }}</option>
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
