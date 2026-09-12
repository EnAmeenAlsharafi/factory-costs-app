@extends('layouts.app')

@section('title', 'إنشاء أمر إنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-plus-circle text-primary me-2"></i>إنشاء أمر إنتاج من طلب عميل</h4>
            <p class="text-muted mb-0">تحويل بنود طلبات العملاء المعتمدة للإنتاج إلى أمر تصنيع بالورشة</p>
        </div>
        <a href="{{ route('production.orders.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة لأوامر الإنتاج
        </a>
    </div>

    <form action="{{ route('production.orders.store') }}" method="POST">
        @csrf

        <div class="row g-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-file-invoice me-1"></i> اختيار بند طلب العميل المعتمد</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">طلب العميل المعتمد للإنتاج</label>
                            <select name="customer_order_line_id" id="order-line-select" class="form-select @error('customer_order_line_id') is-invalid @enderror" required>
                                <option value="">-- اختر البند المعتمد للإنتاج --</option>
                                @foreach($approvedOrders as $ord)
                                    <optgroup label="طلب رقم: {{ $ord->order_number }} (العميل: {{ $ord->customer?->name_ar }})">
                                        @foreach($ord->lines as $line)
                                            @php
                                                $alreadyReleased = (int) ($line->released_quantity_sum ?? 0);
                                                $remaining = $line->quantity - $alreadyReleased;
                                            @endphp
                                            @if($remaining > 0)
                                                <option value="{{ $line->id }}" {{ ($orderLine && $orderLine->id === $line->id) ? 'selected' : '' }}
                                                        data-qty="{{ $line->quantity }}"
                                                        data-remaining="{{ $remaining }}"
                                                        data-model="{{ $line->is_custom_design ? 'تصميم خاص: '.$line->custom_design_name : $line->productModel?->name_ar }}"
                                                        data-size="{{ (int)$line->requested_width_cm }}x{{ (int)$line->requested_length_cm }}"
                                                        data-storage="{{ $line->has_storage ? 'نعم' : 'لا' }}">
                                                    بند #{{ $line->item_number }}: {{ $line->is_custom_design ? $line->custom_design_name : $line->productModel?->name_ar }} (الكمية الكلية: {{ $line->quantity }} | المتبقي للإطلاق: {{ $remaining }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('customer_order_line_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">الكمية المراد إطلاقها لأمر الإنتاج</label>
                                <input type="number" name="released_quantity" id="released-qty-input" class="form-control @error('released_quantity') is-invalid @enderror" min="1" value="{{ old('released_quantity', 1) }}" required>
                                <small class="text-muted" id="qty-help">يدعم الإنتاج الجزئي (يمكن إطلاق كمية أقل من إجمالي بند الطلب)</small>
                                @error('released_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">أولوية الإنتاج</label>
                                <select name="priority" class="form-select">
                                    <option value="NORMAL">عادي</option>
                                    <option value="URGENT">عاجل</option>
                                    <option value="VIP">VIP</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-route me-1"></i> مسار التصنيع والتاريخ المخطط</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">مسار التصنيع المختار (Routing)</label>
                                <select name="production_routing_id" class="form-select">
                                    <option value="">-- اختيار مسار التصنيع لاحقاً (حفظ كمسودة) --</option>
                                    @foreach($routings as $rtg)
                                        <option value="{{ $rtg->id }}" {{ $loop->first ? 'selected' : '' }}>
                                            {{ $rtg->name_ar }} ({{ $rtg->routing_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">التاريخ المخطط للبدء</label>
                                <input type="date" name="planned_start_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">التاريخ المخطط للإكمال</label>
                                <input type="date" name="planned_completion_date" class="form-control">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">ملاحظات وتعليمات الإنتاج للورشة</label>
                                <textarea name="production_notes" class="form-control" rows="3" placeholder="أي ملاحظات فنية خاصة بالقص، التنجيد، أو التجميع"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clipboard-list me-1"></i> معاينة مواصفات البند</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">المنتج / الموديل:</small>
                            <span class="fw-bold text-dark" id="preview-model">---</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">المقاس المطلوبة:</small>
                            <span class="fw-bold text-dark" id="preview-size">---</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">السحارات:</small>
                            <span class="fw-bold text-dark" id="preview-storage">---</span>
                        </div>
                        <hr>
                        <div class="alert alert-info py-2 mb-0 small">
                            <i class="fas fa-info-circle me-1"></i>
                            عند حفظ أمر الإنتاج، يتم تجميد وأخذ لقطة (Snapshot) كاملة للمواصفات الفنية لضمان استقلالية خطوط الإنتاج.
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check me-1"></i> حفظ وتأكيد أمر الإنتاج</button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let select = document.getElementById('order-line-select');
    let qtyInput = document.getElementById('released-qty-input');
    let qtyHelp = document.getElementById('qty-help');

    let pModel = document.getElementById('preview-model');
    let pSize = document.getElementById('preview-size');
    let pStorage = document.getElementById('preview-storage');

    function updatePreview() {
        let opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            let rem = opt.getAttribute('data-remaining');
            qtyInput.value = rem;
            qtyInput.max = rem;
            qtyHelp.textContent = `الحد الأقصى المتاح للإطلاق من هذا البند هو ${rem}`;

            pModel.textContent = opt.getAttribute('data-model');
            pSize.textContent = opt.getAttribute('data-size') + ' سم';
            pStorage.textContent = opt.getAttribute('data-storage');
        } else {
            pModel.textContent = '---';
            pSize.textContent = '---';
            pStorage.textContent = '---';
        }
    }

    select.addEventListener('change', updatePreview);
    updatePreview();
});
</script>
@endpush
@endsection
