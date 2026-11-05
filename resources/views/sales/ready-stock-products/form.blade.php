@extends('layouts.app')
@section('title', $material->exists ? 'تعديل منتج جاهز' : 'إضافة منتج جاهز')
@section('content')
<div class="container-fluid px-4 py-4">
    <a href="{{ route('sales.ready-stock-products.index') }}" class="text-decoration-none d-inline-block mb-3">العودة للمنتجات الجاهزة</a>
    <h1 class="h3 fw-bold mb-4">{{ $material->exists ? 'تعديل منتج جاهز' : 'إضافة منتج جاهز يدويًا' }}</h1>
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $material->exists ? route('sales.ready-stock-products.update', $material) : route('sales.ready-stock-products.store') }}" class="card border-0 shadow-sm">
        @csrf
        <input type="hidden" name="ready_parent_id" value="{{ $material->ready_parent_id }}">
        @if($material->ready_parent_id)
            <div class="alert alert-info m-3">إضافة / تعديل خيار للمنتج: {{ $material->readyParent?->name_ar }}. سمِّ الصنف باسم المنتج والمقاس لتمييزه عند إدخاله يدويًا في الطلب.</div>
            <input type="hidden" name="has_ready_options" value="0">
            <div class="px-4 mb-3"><label for="ready_option_label" class="form-label">الخيار / المقاس</label><input name="ready_option_label" id="ready_option_label" class="form-control" maxlength="255" placeholder="مثل 160×200 سم" value="{{ old('ready_option_label', $material->ready_option_label) }}" required></div>
        @else
            <div class="form-check m-4">
                <input type="hidden" name="has_ready_options" value="0">
                <input type="checkbox" name="has_ready_options" id="has_ready_options" class="form-check-input" value="1" @checked(old('has_ready_options', $material->has_ready_options))>
                <label for="has_ready_options" class="form-check-label">منتج رئيسي يحتوي خيارات / مقاسات تُضاف من النظام</label>
            </div>
        @endif
        @if($material->exists && $material->has_ready_options)
            <div class="px-4 mb-3">
                <a href="{{ route('sales.ready-stock-products.options.create', $material) }}" class="btn btn-outline-success mb-2">إضافة خيار / مقاس</a>
                @foreach($material->readyOptions as $option)<a class="d-block" href="{{ route('sales.ready-stock-products.edit', $option) }}">{{ $option->ready_option_label }} — {{ $option->sales_display_name ?: $option->name_ar }}</a>@endforeach
            </div>
        @endif
        @if($material->exists) @method('PUT') @endif
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                    <input id="name_ar" name="name_ar" class="form-control" value="{{ old('name_ar', $material->name_ar) }}" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label for="sales_display_name" class="form-label">الاسم المعروض للبيع</label>
                    <input id="sales_display_name" name="sales_display_name" class="form-control" value="{{ old('sales_display_name', $material->sales_display_name) }}" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label for="salla_product_id" class="form-label">رقم المنتج في سلة (No.)</label>
                    <input id="salla_product_id" name="salla_product_id" class="form-control" dir="ltr" inputmode="numeric" value="{{ old('salla_product_id', $material->salla_product_id) }}" maxlength="100">
                    <small class="text-muted">هوية المنتج في كتالوج سلة، مستقلة عن SKU وعن مرجع بند الطلب.</small>
                </div>
                <div class="col-md-6">
                    <label for="sales_sku" class="form-label">رمز المنتج SKU</label>
                    <input id="sales_sku" name="sales_sku" class="form-control" dir="ltr" value="{{ old('sales_sku', $material->sales_sku) }}" maxlength="100">
                    <small class="text-muted">يستخدم للمطابقة التلقائية عند استيراد الطلبات. عند غيابه يلزم إعداد مطابقة يدوية.</small>
                </div>
                <div class="col-md-6">
                    <label for="base_unit_id" class="form-label">وحدة المخزون <span class="text-danger">*</span></label>
                    @if($material->exists)
                        <input type="hidden" name="base_unit_id" value="{{ $material->base_unit_id }}">
                        <input class="form-control" id="base_unit_id" value="{{ $material->baseUnit?->name_ar }}" disabled>
                    @else
                        <select id="base_unit_id" name="base_unit_id" class="form-select" required>
                            @foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('base_unit_id', $material->base_unit_id) == $unit->id)>{{ $unit->name_ar }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <div class="col-md-6">
                    <label for="salla_category" class="form-label">تصنيف المنتج في سلة</label>
                    <input id="salla_category" name="salla_category" class="form-control" value="{{ old('salla_category', $material->salla_category) }}" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label for="sales_price" class="form-label">سعر البيع المرجعي (ر.س)</label>
                    <input id="sales_price" name="sales_price" type="number" min="0" step="0.0001" class="form-control" value="{{ old('sales_price', $material->sales_price) }}">
                </div>
                @can('costing.view')
                    <div class="col-md-4">
                        <label for="sales_reference_cost" class="form-label">تكلفة مرجعية من الكتالوج (ر.س)</label>
                        <input id="sales_reference_cost" name="sales_reference_cost" type="number" min="0" step="0.0001" class="form-control" value="{{ old('sales_reference_cost', $material->sales_reference_cost) }}">
                        <small class="text-muted">تكلفة الدفعات الفعلية تسجل عند استلام المخزون.</small>
                    </div>
                @endcan
                <div class="col-md-4">
                    <label for="salla_stock_quantity" class="form-label">الكمية المسجلة في سلة</label>
                    <input id="salla_stock_quantity" name="salla_stock_quantity" type="number" min="0" step="0.0001" class="form-control" value="{{ old('salla_stock_quantity', $material->salla_stock_quantity) }}">
                </div>
                <div class="col-md-6">
                    <label for="salla_status" class="form-label">حالة المنتج في سلة</label>
                    <input id="salla_status" name="salla_status" class="form-control" value="{{ old('salla_status', $material->salla_status) }}" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label for="is_active" class="form-label">حالة المنتج في النظام</label>
                    <select id="is_active" name="is_active" class="form-select"><option value="1" @selected(old('is_active', $material->is_active) == 1)>نشط</option><option value="0" @selected(old('is_active', $material->is_active) == 0)>غير نشط</option></select>
                </div>
                <div class="col-12">
                    <label for="sales_image_url" class="form-label">رابط الصورة الرئيسية للمنتج</label>
                    <input id="sales_image_url" name="sales_image_url" type="url" class="form-control" dir="ltr" maxlength="2048" placeholder="https://..." value="{{ old('sales_image_url', $material->sales_image_url) }}">
                    @if($material->sales_image_url)<img src="{{ $material->sales_image_url }}" alt="الصورة الرئيسية للمنتج" class="img-thumbnail mt-2" style="max-width:180px;max-height:140px;object-fit:contain;" referrerpolicy="no-referrer">@endif
                    <small class="text-muted d-block">يُحفظ رابط الصورة الأولى من ملف سلة، وتُعرض الصورة من مصدرها الأصلي.</small>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">ملاحظات</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="10000">{{ old('notes', $material->notes) }}</textarea>
                </div>
            </div>
            <p class="text-muted mt-3">حفظ بيانات المنتج لا ينشئ رصيدًا في المستودع. استخدم استلام المخزون لتسجيل الكمية والتكلفة الفعلية.</p>
            <div class="d-flex gap-2 justify-content-end">
                <a class="btn btn-outline-secondary" href="{{ route('sales.ready-stock-products.index') }}">إلغاء</a>
                <button class="btn btn-primary" type="submit">حفظ المنتج</button>
            </div>
        </div>
    </form>
</div>
@endsection
