@extends('layouts.app')

@section('title', 'تفاصيل موديل المصنع - ' . $model->name_ar)

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.models.index') }}" class="text-decoration-none">موديلات المصنع</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $model->model_code }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0 text-dark">{{ $model->name_ar }}</h1>
                <span class="badge bg-primary bg-opacity-10 text-primary fw-mono px-3 py-2 fs-7">{{ $model->model_code }}</span>
                @if($model->is_active)
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 fs-7">نشط (Active)</span>
                @else
                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-2 fs-7">معطل (Inactive)</span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('products.manage')
                <a href="{{ route('products.models.edit', $model) }}" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> تعديل الموديل
                </a>
            @endcan
            <a href="{{ route('products.models.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- Model Details Header Card -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 text-center d-flex flex-column justify-content-center align-items-center">
                    @if($model->reference_image_path)
                        <img src="{{ asset('storage/' . $model->reference_image_path) }}" alt="{{ $model->name_ar }}" class="img-fluid rounded border shadow-sm object-fit-cover w-100" style="max-height: 250px;">
                    @else
                        <div class="bg-light rounded border d-flex flex-column align-items-center justify-content-center text-muted p-5 w-100" style="min-height: 200px;">
                            <i class="fas fa-bed display-3 mb-2"></i>
                            <span class="fs-7">لا توجد صورة مرجعية مرفقة للموديل</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-info-circle text-primary me-2"></i>معلومات الهوية المصنعية</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">الكود المصنعي الثابت:</span>
                            <span class="fw-mono fw-bold text-primary fs-6">{{ $model->model_code }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">الاسم بالعربية:</span>
                            <span class="fw-bold text-dark fs-6">{{ $model->name_ar }}</span>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted d-block fs-7">الاسم بالإنجليزية:</span>
                            <span class="fw-bold text-dark dir-ltr">{{ $model->name_en ?? '-' }}</span>
                        </div>

                        <div class="col-12">
                            <span class="text-muted d-block fs-7">الوصف الفني:</span>
                            <span class="text-dark">{{ $model->description ?? 'لا يوجد وصف مُسجل' }}</span>
                        </div>

                        <div class="col-12">
                            <span class="text-muted d-block fs-7">ملاحظات الهندسية والتصنيع:</span>
                            <span class="text-dark">{{ $model->design_notes ?? 'لا توجد ملاحظات هندسية' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: Standard Manufacturing Configurations -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-ruler-combined text-primary me-2"></i>تكوينات التصنيع القياسية (Product Configurations)</h5>
                <small class="text-muted">المقاسات وخيارات الهيكل المصنعي (مثل التخزين / السحارة) المتاحة لهذا الموديل</small>
            </div>
            @can('products.manage')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addConfigurationModal">
                    <i class="fas fa-plus me-1"></i> إضافة تكوين تصنيعي
                </button>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">كود التكوين</th>
                            <th>المقاس المصنعي (العرض × الطول)</th>
                            <th>خيار التخزين (السحارة)</th>
                            <th>اسم التكوين العرضي</th>
                            <th class="text-center">الحالة</th>
                            @can('products.manage')
                                <th class="text-center pe-3">الإجراءات</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($model->configurations as $cfg)
                            <tr>
                                <td class="ps-3 fw-mono fw-bold text-primary">{{ $cfg->configuration_code }}</td>
                                <td>
                                    <span class="fw-bold text-dark fs-6">{{ number_format($cfg->width_cm, 0) }} × {{ number_format($cfg->length_cm, 0) }} سم</span>
                                    @if($cfg->standardBedSize)
                                        <small class="badge bg-light text-secondary border ms-1">{{ $cfg->standardBedSize->name_ar }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($cfg->has_storage)
                                        <span class="badge bg-warning bg-opacity-10 text-dark fw-bold px-3 py-1"><i class="fas fa-box-archive me-1"></i> يتضمن تخزين (سحارة)</span>
                                    @else
                                        <span class="badge bg-light text-muted border px-3 py-1">بدون تخزين (عادي)</span>
                                    @endif
                                </td>
                                <td>{{ $cfg->configuration_name ?? '-' }}</td>
                                <td class="text-center">
                                    @if($cfg->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                @can('products.manage')
                                    <td class="text-center pe-3">
                                        <form action="{{ route('products.configurations.toggle-status', $cfg) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $cfg->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $cfg->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="fas {{ $cfg->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    لا توجد تكوينات تصنيعية قياسية مضافة لهذا الموديل حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Customer Product Aliases -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-tags text-primary me-2"></i>مسميات المنتج لدى العملاء (Customer Product Aliases)</h5>
                <small class="text-muted">ربط اسم الموديل الداخلي بمسميات متجر سدير والعملاء التجاريين والموزعين</small>
            </div>
            @can('products.manage')
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAliasModal">
                    <i class="fas fa-plus me-1"></i> ربط مسمى عميل جديد
                </button>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">اسم العميل / المتجر</th>
                            <th>اسم المنتج لدى العميل</th>
                            <th>كود المنتج لدى العميل</th>
                            <th class="text-center">الافتراضي للعميل</th>
                            <th class="text-center">الحالة</th>
                            @can('products.manage')
                                <th class="text-center pe-3">الإجراءات</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($model->aliases as $alias)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark">{{ $alias->customer->name_ar }}</span>
                                    <small class="badge bg-light text-muted border ms-1">{{ $alias->customer->customer_code }}</small>
                                </td>
                                <td class="fw-bold text-primary fs-6">{{ $alias->customer_product_name }}</td>
                                <td class="fw-mono">{{ $alias->customer_product_code ?? '-' }}</td>
                                <td class="text-center">
                                    @if($alias->is_default)
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-1"><i class="fas fa-star me-1"></i> افتراضي</span>
                                    @else
                                        @can('products.manage')
                                            <form action="{{ route('products.aliases.set-default', $alias) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-link text-muted p-0 text-decoration-none">تعيين كافتراضي</button>
                                            </form>
                                        @else
                                            <span class="text-muted fs-7">-</span>
                                        @endcan
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($alias->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">نشط</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">معطل</span>
                                    @endif
                                </td>
                                @can('products.manage')
                                    <td class="text-center pe-3">
                                        <form action="{{ route('products.aliases.toggle-status', $alias) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $alias->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $alias->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="fas {{ $alias->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    لا توجد مسميات تجارية مضافة للعملاء لهذا الموديل حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal 1: Add Configuration -->
<div class="modal fade" id="addConfigurationModal" tabindex="-1" aria-labelledby="addConfigurationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('products.configurations.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_model_id" value="{{ $model->id }}">

                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="addConfigurationModalLabel"><i class="fas fa-ruler-combined text-primary me-2"></i>إضافة تكوين تصنيعي للموديل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="standard_bed_size_id" class="form-label fw-semibold">اختر من المقاسات القياسية (اختياري)</label>
                        <select name="standard_bed_size_id" id="standard_bed_size_id" class="form-select" onchange="onStandardSizeSelect(this)">
                            <option value="">-- اختياري: تحديد أبعاد مخصصة --</option>
                            @foreach($standardSizes as $size)
                                <option value="{{ $size->id }}" data-width="{{ $size->width_cm }}" data-length="{{ $size->length_cm }}">
                                    {{ $size->name_ar }} ({{ $size->width_cm }} × {{ $size->length_cm }} سم)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="cfg_width_cm" class="form-label fw-semibold">العرض (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="width_cm" id="cfg_width_cm" class="form-control" required placeholder="160">
                        </div>
                        <div class="col-6">
                            <label for="cfg_length_cm" class="form-label fw-semibold">الطول (سم) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="length_cm" id="cfg_length_cm" class="form-control" required placeholder="200">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="has_storage" id="cfg_has_storage" value="1">
                        <label class="form-check-input-label fw-semibold ms-2" for="cfg_has_storage">يتضمن سحارة / تخزين (Storage Box)</label>
                    </div>

                    <div class="mb-3">
                        <label for="cfg_name" class="form-label fw-semibold">اسم التكوين الإيضاحي (اختياري)</label>
                        <input type="text" name="configuration_name" id="cfg_name" class="form-control" placeholder="مثل: 160×200 سحارة">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ التكوين</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Add Customer Alias -->
<div class="modal fade" id="addAliasModal" tabindex="-1" aria-labelledby="addAliasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('products.aliases.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_model_id" value="{{ $model->id }}">

                <div class="modal-header bg-light py-3">
                    <h5 class="modal-header-title fw-bold text-dark mb-0" id="addAliasModalLabel"><i class="fas fa-tags text-primary me-2"></i>ربط مسمى منتج لعميل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="alias_customer_id" class="form-label fw-semibold">العميل / المتجر <span class="text-danger">*</span></label>
                        <select name="customer_id" id="alias_customer_id" class="form-select" required>
                            <option value="">-- اختر العميل --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name_ar }} ({{ $customer->customer_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="customer_product_name" class="form-label fw-semibold">اسم المنتج لدى العميل <span class="text-danger">*</span></label>
                        <input type="text" name="customer_product_name" id="customer_product_name" class="form-control" required placeholder="مثل: سرير أڤالون الفاخر">
                    </div>

                    <div class="mb-3">
                        <label for="customer_product_code" class="form-label fw-semibold">كود المنتج لدى العميل (SKU / Code)</label>
                        <input type="text" name="customer_product_code" id="customer_product_code" class="form-control dir-ltr" placeholder="e.g. SALLA-102 or R-202">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="alias_is_default" value="1">
                        <label class="form-check-input-label fw-semibold ms-2" for="alias_is_default">تعيين كـ مسمى افتراضي لهذا الموديل عند اختيار العميل</label>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> حفظ المسمى</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onStandardSizeSelect(selectEl) {
    const option = selectEl.options[selectEl.selectedIndex];
    if (option && option.dataset) {
        if (option.dataset.width) {
            document.getElementById('cfg_width_cm').value = option.dataset.width;
        }
        if (option.dataset.length) {
            document.getElementById('cfg_length_cm').value = option.dataset.length;
        }
    }
}
</script>
@endsection
