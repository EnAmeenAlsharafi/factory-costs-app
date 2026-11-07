@extends('layouts.app')
@section('title', 'استيراد المنتجات الجاهزة من سلة')
@section('content')
<div class="container-fluid px-4 py-4">
    <div class="page-header-card mb-4">
        <a href="{{ route('sales.ready-stock-products.index') }}" class="text-decoration-none text-secondary fs-7 d-inline-block mb-2"><i class="fas fa-arrow-right me-1" aria-hidden="true"></i> العودة للمنتجات الجاهزة</a>
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2"><i class="fas fa-file-import text-warning fs-5" aria-hidden="true"></i>استيراد المنتجات الجاهزة من ملف سلة</h1>
    </div>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <p>ارفع ملف تصدير المنتجات من سلة. ستظهر معاينة لاختيار المنتجات المراد إضافتها أو تحديثها قبل الحفظ.</p>
        <p>تُقرأ بيانات رقم المنتج (No.)، وSKU، والاسم، والتصنيف، والتكلفة المرجعية، والكمية وحالة المنتج في سلة. سعر البيع للعميل يُؤخذ من «السعر المخفض» فقط؛ يجب استكماله إذا كان فارغًا.</p>
        <p>تُستبعد المنتجات المخفية في سلة والمنتجات التي كميتها صفر، سواء كانت جديدة أو موجودة في النظام.</p>
        <p>يُحفظ رابط الصورة الأولى فقط من عمود «صورة المنتج» وتُعرض في المعاينة وقائمة المنتجات. بقية الصور لا تُستورد، وتظل الصورة معتمدة على رابطها الأصلي.</p>
        <div class="alert alert-info">ملف الطلبات المرفق يعرض SKU ولا يعرض رقم منتج سلة. «الرقم المرجعي على مستوى المنتج بالطلب» ليس بديلًا عن رقم المنتج. المنتجات دون SKU تحتاج إلى مطابقة يدوية.</div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('sales.ready-stock-products.import-upload') }}">
            @csrf
            <label for="import_file" class="form-label">ملف منتجات سلة (Excel أو CSV، حتى 10 ميجابايت)</label>
            <input id="import_file" name="import_file" type="file" class="form-control mb-3" accept=".xlsx,.csv,.txt" required>
            <p class="text-muted">تُحفظ المنتجات ذات الخيارات كمنتجات رئيسية، وتُضاف خياراتها من النظام عبر «إضافة خيار». لا تُستورد صفوف الخيارات من سلة. راجع الاختيار لتجنب إدخال الأسرّة المصنّعة أو البكجات كمنتجات جاهزة. كمية سلة تحفظ كمرجع ولا تُضاف إلى رصيد المستودع.</p>
            <button class="btn btn-primary" type="submit">رفع ومعاينة المنتجات</button>
        </form>
    </div></div>
</div>
@endsection
