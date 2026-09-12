@extends('layouts.app')

@section('title', 'إطلاق أمر إنتاج للورشة - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-play-circle text-success me-2"></i>إطلاق أمر الإنتاج للورشة</h4>
            <p class="text-muted mb-0">أمر إنتاج رقم: <span class="font-monospace text-primary fw-bold">{{ $order->production_order_number }}</span></p>
        </div>
        <a href="{{ route('production.orders.show', $order) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة لأمر الإنتاج
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <form action="{{ route('production.orders.release', $order) }}" method="POST">
                @csrf

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-route me-1"></i> اختيار مسار التصنيع لتوليد العمليات</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label required">اختر مسار التصنيع المعتمد لهذا أمر الإنتاج</label>
                            <select name="production_routing_id" class="form-select form-select-lg" required>
                                <option value="">-- اختر مسار التصنيع --</option>
                                @foreach($routings as $rtg)
                                    <option value="{{ $rtg->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        {{ $rtg->name_ar }} (كود: {{ $rtg->routing_code }} - يحتوي على {{ $rtg->operations->count() }} مراحل)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>تنبيه الإطلاق:</strong> عند الضغط على إطلاق لأمر الإنتاج، ستقوم المنظومة بأخذ لقطة لجميع عمليات المسار، وإنشاء طابور العمليات (Operations) وإتاحتها للورش وأقسام الإنتاج في طوابير العمل.
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('production.orders.show', $order) }}" class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-success btn-lg px-4"><i class="fas fa-rocket me-1"></i> إطلاق للورشة الآن</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-dark">ملخص أمر الإنتاج</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">طلب العميل:</td>
                            <td class="fw-bold font-monospace">{{ $order->customerOrder?->order_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">العميل:</td>
                            <td class="fw-bold">{{ $order->customerOrder?->customer?->name_ar }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المنتج:</td>
                            <td class="fw-bold">{{ $order->is_custom_design ? $order->custom_design_name : $order->productModel?->name_ar }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الأبعاد:</td>
                            <td>{{ (int)$order->requested_width_cm }} × {{ (int)$order->requested_length_cm }} سم</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الكمية المطلوبة:</td>
                            <td class="fw-bold text-success fs-5">{{ $order->released_quantity }} قطعة</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
