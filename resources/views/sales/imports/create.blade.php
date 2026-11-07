@extends('layouts.app')

@section('title', 'رفع ملف استيراد طلبات سلة')

@section('content')
<div class="alert alert-info m-4">سيُحفظ العميل وتُضاف المنتجات البسيطة المطابقة فقط. المنتجات ذات الخيارات والأسرّة والبنود غير المطابقة تُضاف يدويًا من طلبات بانتظار الاستكمال. تُراجع الدفعات بعد اكتمال جميع البنود.</div>
<div class="container-fluid px-4 py-4">

    <div class="page-header-card mb-4">
        <a href="{{ route('sales.imports.index') }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل الاستيراد
        </a>
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">رفع ملف طلبات سلة</h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">يُقرأ الملف ويُعرض للمعاينة قبل أي إنشاء فعلي للطلبات أو العملاء.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2">يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('sales.imports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">قناة البيع <span class="text-danger">*</span></label>
                    <select name="sales_channel_id" class="form-select" required>
                        <option value="">-- اختر قناة البيع --</option>
                        @foreach($channels as $channel)
                            <option value="{{ $channel->id }}">{{ $channel->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">ملف الطلبات (Excel أو CSV) <span class="text-danger">*</span></label>
                    <input type="file" name="import_file" class="form-control" accept=".csv,.xlsx,.txt" required>
                    <small class="text-muted fs-8">يجب أن يطابق ترويسة الملف تصدير سلة الرسمي لطلبات المتجر.</small>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('sales.imports.index') }}" class="btn btn-outline-secondary px-4">إلغاء</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-upload me-1"></i> رفع ومعاينة</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
