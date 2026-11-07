@extends('layouts.app')
@section('title', 'معاينة استيراد المنتجات الجاهزة')
@section('content')
<div class="container-fluid px-4 py-4" x-data="{ selected: [], search: '', eligible: @js(array_values(array_map('intval', array_keys(array_filter($preview['rows'], fn ($row) => !$row['error']))))) }">
    <div class="page-header-card mb-4">
        <a href="{{ route('sales.ready-stock-products.import') }}" class="text-decoration-none text-secondary fs-7 d-inline-block mb-2"><i class="fas fa-arrow-right me-1" aria-hidden="true"></i> رفع ملف آخر</a>
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-eye text-warning fs-5" aria-hidden="true"></i>معاينة المنتجات: <span dir="ltr">{{ $fileName }}</span></h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">عدد المنتجات: {{ count($preview['rows']) }}. الصفوف المستبعدة (خيارات، أنواع أخرى، منتجات مخفية أو كمية صفر): {{ $preview['skipped'] }}. لم يتم حفظ أي منتج بعد.</p>
    </div>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <p class="text-muted">سعر البيع مأخوذ من «السعر المخفض» فقط. المنتجات دون سعر مخفض صالح لا يمكن استيرادها.</p>
    <div class="alert alert-warning">اختر المنتجات التي تُباع من المخزون دون تصنيع. يُحدّث المنتج الموجود برقم سلة أو SKU، مع رفض أي تعارض بينهما. المنتجات دون SKU تحتاج إلى مطابقة يدوية للطلبات.</div>
    <form method="POST" action="{{ route('sales.ready-stock-products.import-confirm') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="selected_rows" :value="JSON.stringify(selected)">
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <button type="button" class="btn btn-outline-primary" @click="selected = [...eligible]">تحديد كل المنتجات الصالحة</button>
            <button type="button" class="btn btn-outline-secondary" @click="selected = []">إلغاء التحديد</button>
            <span>المحدد: <strong x-text="selected.length">0</strong></span>
            <button type="submit" class="btn btn-success" :disabled="selected.length === 0">تأكيد استيراد المحدد</button>
        </div>
        <label for="preview_search" class="form-label">البحث في المعاينة</label>
        <input id="preview_search" type="search" class="form-control mb-3" x-model.debounce.200ms="search" placeholder="اسم المنتج أو رقم سلة أو SKU">
        <div class="card border-0 shadow-sm"><div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>اختيار</th><th>رقم سلة</th><th>SKU</th><th>المنتج والتصنيف</th><th>سعر البيع</th>@can('costing.view')<th>التكلفة المرجعية</th>@endcan<th>كمية سلة</th><th>حالة سلة</th><th>نتيجة المعاينة</th></tr></thead>
                <tbody>
                @forelse($preview['rows'] as $row)
                    <tr x-show="!search || $el.dataset.search.includes(search.toLowerCase())" data-search="{{ mb_strtolower($row['name_ar'].' '.$row['salla_product_id'].' '.$row['sales_sku']) }}">
                        <td><input type="checkbox" class="form-check-input" value="{{ $row['row_number'] }}" x-model.number="selected" @disabled($row['error']) aria-label="اختيار {{ $row['name_ar'] }}"></td>
                        <td><bdi>{{ $row['salla_product_id'] }}</bdi></td>
                        <td><bdi>{{ $row['sales_sku'] ?: '—' }}</bdi>@unless($row['sales_sku'])<small class="text-warning d-block">يلزم مطابقة يدوية</small>@endunless</td>
                        <td>
                            @if($row['sales_image_url'])<img src="{{ $row['sales_image_url'] }}" alt="الصورة الأولى للمنتج" width="64" height="64" class="img-thumbnail mb-1" style="object-fit:contain;" loading="lazy" referrerpolicy="no-referrer">@endif
                            {{ $row['name_ar'] }}<small class="text-muted d-block">{{ $row['salla_category'] }}</small>
                            @if($row['has_ready_options'])<small class="d-block text-info">منتج رئيسي: أضف الخيارات من النظام. بنوده في الطلبات تُستكمل يدويًا.</small>@endif
                        </td>
                        <td>{{ $row['sales_price'] ?? '—' }}</td>
                        @can('costing.view')<td>{{ $row['sales_reference_cost'] ?? '—' }}</td>@endcan
                        <td>{{ $row['salla_stock_quantity'] ?? '—' }}</td>
                        <td>{{ $row['salla_status'] ?? '—' }}</td>
                        <td><span class="{{ $row['error'] ? 'text-danger' : 'text-success' }}">{{ $row['error'] ?: $row['action'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center py-4">لا توجد منتجات جاهزة قابلة للاستيراد في الملف.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>
        <p class="text-muted mt-3">تأكيد الاستيراد يحفظ بيانات الكتالوج فقط؛ لا يغيّر رصيد المستودع ولا تكلفة دفعات المخزون.</p>
    </form>
</div>
@endsection
