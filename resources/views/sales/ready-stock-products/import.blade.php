@extends('layouts.app')
@section('title', 'استيراد المنتجات الجاهزة من سلة')
@section('content')
<div class="container-fluid px-4 py-4">
    <a href="{{ route('sales.ready-stock-products.index') }}" class="text-decoration-none d-inline-block mb-3">العودة للمنتجات الجاهزة</a>
    <h1 class="h3 fw-bold mb-4">استيراد المنتجات الجاهزة من ملف سلة</h1>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <p>ارفع ملف تصدير المنتجات من سلة. ستظهر معاينة لاختيار المنتجات المراد إضافتها أو تحديثها قبل الحفظ.</p>
        <p>تُقرأ بيانات رقم المنتج (No.)، وSKU، والاسم، والتصنيف، وسعر البيع، والتكلفة المرجعية، والكمية وحالة المنتج في سلة.</p>
        <div class="alert alert-info">ملف الطلبات المرفق يعرض SKU ولا يعرض رقم منتج سلة. «الرقم المرجعي على مستوى المنتج بالطلب» ليس بديلًا عن رقم المنتج. المنتجات دون SKU تحتاج إلى مطابقة يدوية.</div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('sales.ready-stock-products.import-upload') }}">
            @csrf
            <label for="import_file" class="form-label">ملف منتجات سلة (Excel أو CSV، حتى 10 ميجابايت)</label>
            <input id="import_file" name="import_file" type="file" class="form-control mb-3" accept=".xlsx,.csv,.txt" required>
            <p class="text-muted">تُستبعد صفوف الخيارات والخدمات حسب الطلب. راجع الاختيار لتجنب إدخال الأسرّة المصنّعة أو البكجات كمنتجات جاهزة. كمية سلة تحفظ كمرجع ولا تُضاف إلى رصيد المستودع.</p>
            <button class="btn btn-primary" type="submit">رفع ومعاينة المنتجات</button>
        </form>
    </div></div>
</div>
@endsection
