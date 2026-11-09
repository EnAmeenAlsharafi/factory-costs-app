@extends('layouts.app')

@section('title', 'تفاصيل المادة الخام - ' . $material->name_ar)
@section('page-title', 'تفاصيل ومواصفات المادة الخام')

@section('content')
@php
    $spec = $material->category?->code === 'FABRIC' ? $material->fabricSpec : null;
    $unitSymbol = $material->baseUnit->symbol ?? '';
    $colors = $material->fabricColors->sortBy('color_code', SORT_NATURAL)->values();
    $availableColors = $colors->where('is_active', true)->where('is_available', true)->count();
    $decimal = fn ($value, int $places = 2) => rtrim(rtrim(number_format((float) $value, $places, '.', ''), '0'), '.');
@endphp
<div class="d-flex flex-column gap-4">

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

    @if ($spec)
        {{-- Fabric catalogue: the cover photo beside what identifies the catalogue. --}}
        <section class="card border-0 shadow-sm" aria-labelledby="catalog-heading">
            <div class="card-body p-3 p-md-4">
                <div class="catalog-hero">
                    <figure class="catalog-hero-cover mb-0">
                        @if ($spec->catalog_image_url)
                            <a href="{{ $spec->catalog_image_url }}" target="_blank" rel="noopener" aria-label="عرض صورة الكتالوج بالحجم الكامل">
                                <img src="{{ $spec->catalog_image_url }}" alt="غلاف كتالوج {{ $spec->catalog_name ?? $material->name_ar }}" loading="lazy">
                            </a>
                            <figcaption>كتالوج <bdi>{{ $spec->catalog_number ?? '—' }}</bdi></figcaption>
                        @else
                            <div class="catalog-hero-empty">
                                <i class="fas fa-image fs-3 mb-2" aria-hidden="true"></i>
                                <span>لا توجد صورة للكتالوج</span>
                                @can('materials.manage')
                                    <a href="{{ route('materials.edit', $material) }}#catalog_image" class="small mt-1">إضافة صورة</a>
                                @endcan
                            </div>
                        @endif
                    </figure>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h2 id="catalog-heading" class="h5 fw-bold mb-0">كتالوج {{ $spec->catalog_name ?? $spec->fabric_type }}</h2>
                            @if ($material->is_active)
                                <span class="status-chip status-chip-success"><i class="fas fa-circle-check" aria-hidden="true"></i> نشط</span>
                            @else
                                <span class="status-chip status-chip-danger"><i class="fas fa-ban" aria-hidden="true"></i> معطل</span>
                            @endif
                            @if ($material->is_sellable_ready_product)
                                <span class="status-chip status-chip-info"><i class="fas fa-store" aria-hidden="true"></i> منتج جاهز للبيع</span>
                            @endif
                        </div>
                        <dl class="row g-3 mb-0 facts-list">
                            <div class="col-6 col-lg-4"><dt>المورد</dt><dd>@if ($spec->supplier)<a href="{{ route('suppliers.show', $spec->supplier) }}" class="fw-bold text-decoration-none">{{ $spec->supplier->name }}</a>@else<span class="text-muted">غير محدد</span>@endif</dd></div>
                            <div class="col-6 col-lg-4"><dt>رقم الكتالوج لدى المورد</dt><dd class="fs-5 fw-bold"><bdi>{{ $spec->catalog_number ?? '—' }}</bdi></dd></div>
                            <div class="col-6 col-lg-4"><dt>درجات الألوان</dt><dd><strong>{{ $colors->count() }}</strong> <span class="text-muted small">({{ $availableColors }} متوفرة)</span></dd></div>
                            <div class="col-6 col-lg-4"><dt>نوع القماش</dt><dd>{{ $spec->fabric_type ?: '—' }}</dd></div>
                            <div class="col-6 col-lg-4"><dt>عرض الرول</dt><dd>@if ($spec->width_cm)<bdi>{{ $decimal($spec->width_cm) }}</bdi> سم @else — @endif</dd></div>
                            <div class="col-6 col-lg-4"><dt>النقش / النمط</dt><dd>{{ $spec->pattern_type ?: '—' }}</dd></div>
                            <div class="col-6 col-lg-4"><dt>الوزن</dt><dd>@if ($spec->weight_gsm)<bdi>{{ number_format($spec->weight_gsm) }}</bdi> غم/م² @else — @endif</dd></div>
                            <div class="col-6 col-lg-4"><dt>التركيب الخامي</dt><dd>{{ $spec->composition ?: '—' }}</dd></div>
                            <div class="col-6 col-lg-4"><dt>مقاومة الاحتكاك</dt><dd>@if ($spec->martindale_rub_count)<bdi>{{ number_format($spec->martindale_rub_count) }}</bdi> دورة @else — @endif</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Stock and units. --}}
    <section class="card border-0 shadow-sm" aria-labelledby="stock-heading">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 id="stock-heading" class="h6 fw-bold text-dark mb-0"><i class="fas fa-warehouse text-primary me-2" aria-hidden="true"></i>{{ $spec ? 'المخزون والوحدات' : 'معلومات المادة الخام' }}</h2>
            @unless ($spec)
                <div class="d-flex flex-wrap gap-2">
                    @if ($material->is_sellable_ready_product)
                        <span class="status-chip status-chip-info"><i class="fas fa-store" aria-hidden="true"></i> منتج جاهز للبيع @if ($material->sales_sku)<bdi>{{ $material->sales_sku }}</bdi>@endif</span>
                    @endif
                    @if ($material->is_active)
                        <span class="status-chip status-chip-success"><i class="fas fa-circle-check" aria-hidden="true"></i> نشط</span>
                    @else
                        <span class="status-chip status-chip-danger"><i class="fas fa-ban" aria-hidden="true"></i> معطل</span>
                    @endif
                </div>
            @endunless
        </div>
        <div class="card-body p-4">
            <dl class="row g-3 mb-0 facts-list">
                <div class="col-6 col-md-3"><dt>كود المادة</dt><dd><bdi class="fw-bold">{{ $material->code }}</bdi></dd></div>
                <div class="col-6 col-md-3"><dt>التصنيف</dt><dd>{{ $material->category->name_ar ?? '—' }}</dd></div>
                <div class="col-6 col-md-3"><dt>وحدة الصرف والإنتاج</dt><dd>{{ $material->baseUnit->name_ar ?? '—' }} @if ($unitSymbol)<span class="text-muted small">({{ $unitSymbol }})</span>@endif</dd></div>
                <div class="col-6 col-md-3"><dt>وحدة الشراء</dt><dd>{{ $material->purchaseUnit->name_ar ?? 'نفس وحدة الصرف' }}</dd></div>
                <div class="col-6 col-md-3"><dt>الحد الأدنى للمخزون</dt><dd><bdi>{{ $decimal($material->min_stock_level) }}</bdi> {{ $unitSymbol }}</dd></div>
                <div class="col-6 col-md-3"><dt>نقطة إعادة الطلب</dt><dd><bdi>{{ $decimal($material->reorder_point) }}</bdi> {{ $unitSymbol }}</dd></div>
                <div class="col-6 col-md-3"><dt>الاسم بالإنجليزية</dt><dd><bdi>{{ $material->name_en ?: '—' }}</bdi></dd></div>
                <div class="col-6 col-md-3"><dt>ملاحظات</dt><dd>{{ $material->notes ?: '—' }}</dd></div>
            </dl>
        </div>
    </section>

    @if ($material->category?->code === 'WOOD' && $material->woodSpec)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h2 class="h6 fw-bold text-dark mb-0"><i class="fas fa-tree text-success me-2"></i>المواصفات الفنية للأخشاب والألواح</h2>
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
                <h2 class="h6 fw-bold text-dark mb-0"><i class="fas fa-cubes text-warning me-2"></i>المواصفات الفنية للإسفنج والكتل</h2>
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
    @endif

    @if ($material->category?->code === 'FABRIC')
        {{-- Catalogue colours as swatches: the internal number customer service quotes, and the supplier code purchasing orders. --}}
        <section class="card border-0 shadow-sm" aria-labelledby="colors-heading">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h2 id="colors-heading" class="h6 fw-bold text-dark mb-1"><i class="fas fa-palette text-primary me-2" aria-hidden="true"></i>درجات ألوان الكتالوج ({{ $colors->count() }})</h2>
                    <p class="text-muted small mb-0">لكل لون رقم داخلي تستخدمه خدمة العملاء، وكود المورد للمشتريات والإنتاج.</p>
                </div>
                @can('materials.manage')
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#add-color-form" aria-expanded="false" aria-controls="add-color-form">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> إضافة لون
                    </button>
                @endcan
            </div>
            <div class="card-body p-4">
                @can('materials.manage')
                    <div class="collapse mb-4" id="add-color-form">
                        <form action="{{ route('fabric-colors.store') }}" method="POST" class="p-3 bg-light rounded-3 border">
                            @csrf
                            <input type="hidden" name="material_id" value="{{ $material->id }}">
                            <div class="row g-3 align-items-end">
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1" for="new_color_code">الرقم الداخلي <span class="text-danger">*</span></label>
                                    <input type="text" id="new_color_code" name="color_code" class="form-control" placeholder="مثال: 1" required dir="ltr">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small mb-1" for="new_supplier_color_code">كود المورد <span class="text-danger">*</span></label>
                                    <input type="text" id="new_supplier_color_code" name="supplier_color_code" class="form-control" placeholder="مثال: {{ $spec?->catalog_number ? $spec->catalog_number . '-1' : '2020-1' }}" required dir="ltr">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1" for="new_color_name">اسم اللون</label>
                                    <input type="text" id="new_color_name" name="color_name_ar" class="form-control" placeholder="مثال: رمادي فاتح">
                                </div>
                                <div class="col-4 col-md-1">
                                    <label class="form-label small mb-1" for="new_color_hex">اللون</label>
                                    <input type="color" id="new_color_hex" name="hex_code" class="form-control form-control-color w-100 p-1" value="#6c757d">
                                </div>
                                <div class="col-8 col-md-3">
                                    <label class="form-label small mb-1" for="new_color_pattern">النقشة</label>
                                    <input type="text" id="new_color_pattern" name="pattern" class="form-control" placeholder="سادة">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1" aria-hidden="true"></i> حفظ اللون</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endcan

                @if ($colors->isEmpty())
                    <div class="d-flex flex-column align-items-center text-center text-muted py-4">
                        <i class="fas fa-palette fs-3 mb-2" aria-hidden="true"></i>
                        لا توجد درجات ألوان لهذا الكتالوج بعد. أضف الألوان ليتمكن البيع والبوابة من اختيارها.
                    </div>
                @else
                    <ul class="swatch-grid list-unstyled mb-0">
                        @foreach ($colors as $color)
                            <li class="swatch-card {{ $color->is_active ? '' : 'is-inactive' }}">
                                <span class="swatch-chip" style="background-color: {{ $color->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span>
                                <div class="swatch-body">
                                    <div class="d-flex align-items-baseline justify-content-between gap-2">
                                        <span class="swatch-number" title="الرقم الداخلي">رقم <bdi>{{ $color->color_code }}</bdi></span>
                                        @if (! $color->is_active)
                                            <span class="status-chip status-chip-secondary">معطل</span>
                                        @elseif ($color->is_available)
                                            <span class="status-chip status-chip-success">متوفر</span>
                                        @else
                                            <span class="status-chip status-chip-warning">غير متوفر</span>
                                        @endif
                                    </div>
                                    <div class="fw-semibold text-truncate">{{ $color->color_name_ar ?: 'بدون اسم' }}</div>
                                    <div class="small text-muted">كود المورد: <bdi class="text-dark fw-semibold">{{ $color->supplier_color_code ?? '—' }}</bdi>@if ($color->pattern) · {{ $color->pattern }}@endif</div>
                                    @can('materials.manage')
                                        <div class="swatch-actions">
                                            <form action="{{ route('fabric-colors.toggle-status', $color) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="toggle_availability" value="1">
                                                <button type="submit" class="btn btn-sm btn-light border">{{ $color->is_available ? 'إيقاف التوفر' : 'إتاحة اللون' }}</button>
                                            </form>
                                            <form action="{{ route('fabric-colors.toggle-status', $color) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light border">{{ $color->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                                            </form>
                                            <form action="{{ route('fabric-colors.destroy', $color) }}" method="POST" class="ms-auto">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light border text-danger" onclick="return confirm('حذف هذا اللون؟ لا يمكن حذفه إذا كان مستخدماً في طلبات أو مخزون.');" aria-label="حذف اللون {{ $color->color_code }}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    <!-- Linked Suppliers Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h2 class="h6 fw-bold text-dark mb-0"><i class="fas fa-truck text-secondary me-2"></i>الموردون المعتمدون للمادة الخام</h2>
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
                                    <select name="supplier_id" class="form-select form-select-sm" required aria-label="المورد">
                                        <option value="">-- اختر المورد --</option>
                                        @foreach ($allSuppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->supplier_code ?? $sup->code }}){{ $sup->commercial_name && $sup->commercial_name !== $sup->name ? ' - ' . $sup->commercial_name : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="supplier_item_code" class="form-control form-control-sm" placeholder="كود المادة لدى المورد" aria-label="كود المادة لدى المورد" dir="ltr">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="lead_time_days" class="form-control form-control-sm" placeholder="التوريد (أيام)" aria-label="مدة التوريد بالأيام" min="0">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" step="0.01" name="minimum_order_qty" class="form-control form-control-sm" placeholder="الحد الأدنى للطلب" aria-label="الحد الأدنى للطلب" min="0">
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
                                            <button type="submit" class="btn btn-sm btn-light text-danger p-1" onclick="return confirm('إلغاء ربط هذا المورد؟');" title="إلغاء الربط" aria-label="إلغاء ربط المورد">
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
            <h2 class="h6 fw-bold text-dark mb-0"><i class="fas fa-exchange-alt text-primary me-2"></i>تحويلات الوحدات الخاصة بالمادة</h2>
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
                                    <select name="from_unit_id" class="form-select form-select-sm" required aria-label="من وحدة">
                                        <option value="">-- اختر الوحدة --</option>
                                        @foreach ($allUnits as $u)
                                            <option value="{{ $u->id }}">{{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-8 mb-1">إلى وحدة (عادة الوحدة الأساسية)</label>
                                    <select name="to_unit_id" class="form-select form-select-sm" required aria-label="إلى وحدة">
                                        <option value="">-- اختر الوحدة --</option>
                                        @foreach ($allUnits as $u)
                                            <option value="{{ $u->id }}" {{ $u->id == $material->base_unit_id ? 'selected' : '' }}>
                                                {{ $u->name_ar }} ({{ $u->symbol ?? $u->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-8 mb-1">معامل التحويل</label>
                                    <input type="number" step="0.0001" name="conversion_factor" class="form-control form-control-sm" aria-label="معامل التحويل" placeholder="مثال: 50" required min="0.0001">
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
                            <th>الوحدة المصدر</th>
                            <th>معامل التحويل</th>
                            <th>الوحدة الهدف</th>
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
                                            <button type="submit" class="btn btn-sm btn-light text-danger p-1" onclick="return confirm('حذف هذا التحويل؟');" title="حذف" aria-label="حذف التحويل">
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
