@extends('layouts.app')

@section('title', 'المنتجات الجاهزة للبيع')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">المنتجات الجاهزة للبيع</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">المنتجات الجاهزة للبيع</h1>
            <p class="text-muted mb-0 fs-7">إدارة المنتجات الجاهزة ومطابقتها مع طلبات سلة ومتابعة توفرها في المستودع.</p>
        </div>
        @can('materials.manage')
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="{{ route('sales.ready-stock-products.create') }}"><i class="fas fa-plus me-1"></i> إضافة منتج يدويًا</a>
                <a class="btn btn-outline-primary" href="{{ route('sales.ready-stock-products.import') }}"><i class="fas fa-file-excel me-1"></i> استيراد من سلة</a>
            </div>
        @endcan
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.ready-stock-products.index') }}" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" aria-label="البحث عن منتج جاهز" value="{{ request('search') }}" placeholder="بحث بالاسم، رقم سلة، SKU، أو الكود...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button>
                </div>
                @if(request()->filled('search'))
                    <div class="col-md-2">
                        <a href="{{ route('sales.ready-stock-products.index') }}" class="btn btn-outline-secondary w-100">إعادة ضبط</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم منتج سلة</th>
                            <th>SKU</th>
                            <th>الاسم المعروض</th>
                            <th>التصنيف</th>
                            <th>سعر البيع</th>
                            <th>كمية سلة</th>
                            <th class="text-center">بالمستودع</th>
                            <th class="text-center">محجوز</th>
                            <th class="text-center">صافي المتاح</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            <tr>
                                <td><bdi>{{ $material->salla_product_id ?: '—' }}</bdi></td>
                                <td class="fw-mono fw-bold text-primary"><bdi>{{ $material->sales_sku ?: '—' }}</bdi>
                                    @unless($material->sales_sku)<small class="text-warning d-block">بحاجة إلى مطابقة يدوية للطلبات</small>@endunless
                                </td>
                                <td class="fw-bold text-dark">
                                    @if($material->sales_image_url)
                                        <img src="{{ $material->sales_image_url }}" alt="{{ $material->sales_display_name ?: $material->name_ar }}" width="64" height="64" class="img-thumbnail mb-1" style="object-fit:contain;" loading="lazy" referrerpolicy="no-referrer">
                                    @endif
                                    {{ $material->sales_display_name ?: $material->name_ar }}
                                    @if($material->has_ready_options)<span class="badge bg-info text-dark">منتج رئيسي — خيارات يدوية</span>@elseif($material->ready_parent_id)<span class="badge bg-light text-dark">{{ $material->ready_option_label }}</span>@endif
                                    <div class="text-muted fs-8">{{ $material->code }}</div>
                                </td>
                                <td>{{ $material->salla_category ?: ($material->category->name_ar ?? '—') }}
                                    @unless($material->is_active)<span class="badge bg-secondary d-block">غير نشط في النظام</span>@endunless
                                </td>
                                <td class="text-nowrap">{{ $material->sales_price !== null ? number_format($material->sales_price, 2).' ر.س' : '—' }}</td>
                                <td>{{ $material->salla_stock_quantity !== null ? number_format($material->salla_stock_quantity, 2) : '—' }}</td>
                                <td class="text-center fw-mono">{{ number_format($material->availability['on_hand'], 2) }}</td>
                                <td class="text-center fw-mono text-muted">{{ number_format($material->availability['committed'], 2) }}</td>
                                <td class="text-center">
                                    @if($material->availability['available'] > 0)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">{{ number_format($material->availability['available'], 2) }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-1">0.00</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @can('materials.manage')
                                        @if($material->has_ready_options)<a href="{{ route('sales.ready-stock-products.options.create', $material) }}" class="btn btn-sm btn-outline-success">إضافة خيار</a>@endif
                                        <a href="{{ route('sales.ready-stock-products.edit', $material) }}" class="btn btn-outline-primary" title="تعديل المنتج الجاهز" aria-label="تعديل {{ $material->sales_display_name ?: $material->name_ar }}">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    لا توجد مواد مُمَيَّزة كمنتجات جاهزة للبيع حتى الآن.
                                    @can('materials.manage')
                                        <br><a href="{{ route('sales.ready-stock-products.create') }}" class="text-decoration-none">إضافة منتج جاهز</a> أو <a href="{{ route('sales.ready-stock-products.import') }}">استيراد ملف منتجات سلة</a>.
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($materials->hasPages())
            <div class="card-footer bg-white">
                {{ $materials->links() }}
            </div>
        @endif
    </div>

    <p class="text-muted fs-7 mt-3">كمية سلة مرجع من ملف المنتجات؛ رصيد المستودع يتغير من خلال مستندات المخزون. المطابقة التلقائية لطلبات سلة تعتمد على SKU، ورقم منتج سلة محفوظ بشكل مستقل.</p>

</div>
@endsection
