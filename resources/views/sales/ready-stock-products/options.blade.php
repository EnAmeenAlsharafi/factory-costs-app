@extends('layouts.app')
@section('title', 'خيارات المنتج الجاهز')
@section('content')
@php
    $savedOptions = $material->readyOptions->map(fn ($option) => ['id' => $option->id, 'label' => $option->ready_option_label, 'sales_sku' => $option->sales_sku, 'sales_price' => $option->sales_price, 'is_active' => $option->is_active ? 1 : 0])->all();
    $initialOptions = old('options', $savedOptions);
@endphp
<div class="container-fluid px-4 py-4">
    <div class="page-header-card mb-4">
        <a href="{{ route('sales.ready-stock-products.edit', $material) }}" class="text-decoration-none text-secondary fs-7 d-inline-block mb-2"><i class="fas fa-arrow-right me-1" aria-hidden="true"></i> العودة لبيانات المنتج</a>
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-ruler-combined text-warning fs-5" aria-hidden="true"></i>خيارات ومقاسات {{ $material->sales_display_name ?: $material->name_ar }}</h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">لكل مقاس سعره وصنفه في المخزون. عند إدخال الطلب تختار المنتج ثم المقاس، ويُعبّأ سعره تلقائيًا.</p>
    </div>
    <form method="POST" action="{{ route('sales.ready-stock-products.options.store', $material) }}" data-unsaved-warning
          x-data="{ rows: (@js($initialOptions)).map(row => ({...row, key: crypto.randomUUID()})), add(label = '') { if(this.rows.length < 100) this.rows.push({id: '', label, sales_sku: '', sales_price: '', is_active: 1, key: crypto.randomUUID()}); }, common() { ['90×190','100×200','120×190','120×200','140×200','150×200','160×200','180×200','200×200'].forEach(label => { if(!this.rows.some(row => row.label === label)) this.add(label); }); } }">
        @csrf
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h2 class="h5 mb-0">قائمة الخيارات</h2>
                <div class="d-flex gap-2 flex-wrap"><button class="btn btn-outline-primary" type="button" @click="add()" :disabled="rows.length >= 100">إضافة خيار</button><button class="btn btn-outline-secondary" type="button" @click="common()">إضافة المقاسات الشائعة</button></div>
            </div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>المقاس / اسم الخيار</th><th>SKU اختياري</th><th>سعر البيع (ر.س)</th><th>الحالة</th><th></th></tr></thead>
                <tbody><template x-for="(row, index) in rows" :key="row.key"><tr>
                    <td><input type="hidden" :name="'options['+index+'][id]'" x-model="row.id"><input class="form-control" :name="'options['+index+'][label]'" x-model="row.label" maxlength="255" placeholder="مثل 160×200" aria-label="المقاس / اسم الخيار" required></td>
                    <td><input class="form-control" :name="'options['+index+'][sales_sku]'" x-model="row.sales_sku" maxlength="100" dir="ltr" aria-label="رمز الخيار SKU"></td>
                    <td><input type="number" min="0" max="99999999999" step="0.0001" class="form-control" :name="'options['+index+'][sales_price]'" x-model="row.sales_price" aria-label="سعر الخيار" required></td>
                    <td><select class="form-select" :name="'options['+index+'][is_active]'" x-model="row.is_active" aria-label="حالة الخيار"><option value="1">نشط</option><option value="0">غير نشط</option></select></td>
                    <td><button class="btn btn-outline-danger btn-sm" type="button" x-show="!row.id" @click="rows.splice(index, 1)">حذف الصف</button><span class="text-muted small" x-show="row.id">لإيقافه اختر «غير نشط»</span></td>
                </tr></template></tbody>
            </table></div>
            <div class="card-body text-muted" x-show="rows.length === 0">لم تُسجّل خيارات بعد. أضف المقاسات وأسعارها ثم احفظها لتظهر في الطلبات.</div>
            <div class="card-footer d-flex flex-wrap justify-content-between gap-2 align-items-center"><span class="text-muted">إضافة المقاسات لا تغيّر رصيد المستودع. استكمل سعر كل مقاس قبل الحفظ.</span><button class="btn btn-primary" :disabled="rows.length === 0">حفظ جميع الخيارات</button></div>
        </div>
    </form>
</div>
@endsection
