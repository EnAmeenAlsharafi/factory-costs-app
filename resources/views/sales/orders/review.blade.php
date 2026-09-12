@extends('layouts.app')

@section('title', 'المراجعة الفنية لطلب العميل ' . $order->order_number)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">المراجعة الفنية والاعتماد للإنتاج</h1>
            <p class="text-muted mb-0 fs-7">مراجعة أبعاد تصنيع المصنع المرجعية وربط وصفات المواد (BOM) لطلب العميل: <strong>{{ $order->order_number }}</strong></p>
        </div>
        <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> التراجع
        </a>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-1"></i> يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Info --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted d-block mb-1">العميل</small>
                    <div class="fw-bold text-dark">{{ $order->customer?->name }}</div>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block mb-1">أمر شراء العميل (PO)</small>
                    <div class="fw-bold text-dark">{{ $order->customer_po_number ?? 'غير محدد' }}</div>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block mb-1">قناة البيع</small>
                    <div class="fw-bold text-dark">{{ $order->salesChannel?->name_ar }}</div>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block mb-1">تاريخ الطلب / التسليم</small>
                    <div class="fw-bold text-dark">{{ $order->order_date ? $order->order_date->format('Y-m-d') : '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('sales.orders.approve-production', $order) }}" method="POST">
        @csrf

        {{-- Review Lines Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-header bg-light py-3">
                <h5 class="card-title mb-0 fw-bold fs-6 text-dark"><i class="fas fa-tasks text-primary me-2"></i> مراجعة بنود الطلب وإسناد مواصفات ووصفات التصنيع</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>الموديل والتوصيف</th>
                                <th>المقاس المطلوب من العميل</th>
                                <th style="min-width: 220px;">المقاس المرجعي المعتمد للإنتاج (سم) <span class="text-danger">*</span></th>
                                <th style="min-width: 260px;">وصفة التصنيع المعتمدة (BOM)</th>
                                <th style="width: 80px;" class="text-center">الكمية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->lines as $index => $line)
                                <tr>
                                    <td class="text-center font-monospace text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        @if($line->custom_design)
                                            <span class="badge bg-purple text-white mb-1" style="background-color: #6f42c1;">تصميم خاص</span>
                                            <div class="fw-bold text-dark">{{ $line->custom_design_name ?? 'تصميم خاص' }}</div>
                                        @else
                                            <div class="fw-bold text-dark">{{ $line->productModel?->name_ar ?? 'موديل غير محدد' }}</div>
                                            <small class="text-muted">كود: {{ $line->productModel?->model_code }}</small>
                                        @endif
                                        @if($line->notes)
                                            <div class="fs-8 text-muted mt-1"><i class="fas fa-info-circle me-1"></i> {{ $line->notes }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            {{ $line->requested_width_cm ?? '—' }} × {{ $line->requested_length_cm ?? '—' }} سم
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label fs-8 text-muted mb-1">العرض المرجعي (سم)</label>
                                                <input type="number" step="0.1" name="lines[{{ $line->id }}][reference_width_cm]" 
                                                       class="form-control form-control-sm" 
                                                       value="{{ old('lines.'.$line->id.'.reference_width_cm', $line->reference_width_cm ?? $line->requested_width_cm) }}" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fs-8 text-muted mb-1">الطول المرجعي (سم)</label>
                                                <input type="number" step="0.1" name="lines[{{ $line->id }}][reference_length_cm]" 
                                                       class="form-control form-control-sm" 
                                                       value="{{ old('lines.'.$line->id.'.reference_length_cm', $line->reference_length_cm ?? $line->requested_length_cm) }}" required>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="lines[{{ $line->id }}][recipe_version_id]" class="form-select form-select-sm">
                                            <option value="">-- بدون ربط وصفة حالياً --</option>
                                            @foreach($recipeVersions as $version)
                                                <option value="{{ $version->id }}" {{ old('lines.'.$line->id.'.recipe_version_id', $line->recipe_version_id) == $version->id ? 'selected' : '' }}>
                                                    {{ $version->recipe?->name }} (V{{ $version->version_number }}) - {{ $version->version_code }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted fs-8">اختياري: ربط الوصفة المعتمدة لحساب استهلاك المواد مستقبلاً</small>
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-6">{{ $line->quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-body p-3">
                <label class="form-label fw-semibold text-dark">ملاحظات المراجعة الفنية والإنتاج</label>
                <textarea name="review_notes" class="form-control" rows="2" placeholder="أدخل أي ملاحظات فنية أو توصيات لقسم التصنيع..."></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('sales.orders.show', $order) }}" class="btn btn-light px-4">إلغاء</a>
            <button type="submit" class="btn btn-success px-5 fw-bold" onclick="return confirm('هل أنت تأكد من الاعتماد النهائي لإنتاج هذا الطلب؟')">
                <i class="fas fa-check-double me-1"></i> اعتماد الطلب رسمياً للإنتاج
            </button>
        </div>
    </form>
</div>
@endsection
