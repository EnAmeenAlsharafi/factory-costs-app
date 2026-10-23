{{--
    Structured "block / obstacle" sheet for a single operation (spec §20–21).
    Params: $op
--}}
@php
    $reasonLabels = [
        'MATERIAL_SHORTAGE' => 'نقص مواد',
        'SPEC_ISSUE' => 'مشكلة في المواصفات',
        'QUALITY_ISSUE' => 'مشكلة جودة',
        'AWAITING_MANAGER' => 'انتظار قرار مدير الإنتاج',
        'EQUIPMENT_FAILURE' => 'عطل معدات',
        'OTHER' => 'سبب آخر',
    ];
@endphp
<div class="modal fade" id="block-sheet-{{ $op->id }}" tabindex="-1" aria-labelledby="block-sheet-title-{{ $op->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form action="{{ route('production.operations.block', $op) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold mb-0" id="block-sheet-title-{{ $op->id }}">تسجيل عائق — {{ $op->operation_name_snapshot }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <p class="text-muted fs-7 mb-0">سيتم إيقاف هذه العملية فوراً حتى يُحل العائق. اختر السبب الأقرب لوصف المشكلة.</p>
                <div>
                    <label for="reason-code-{{ $op->id }}" class="form-label">سبب العائق <span class="text-danger">*</span></label>
                    <select id="reason-code-{{ $op->id }}" name="reason_code" class="form-select" required>
                        <option value="" disabled selected>اختر السبب...</option>
                        @foreach ($reasonLabels as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="block-note-{{ $op->id }}" class="form-label">تفاصيل إضافية (اختياري)</label>
                    <textarea id="block-note-{{ $op->id }}" name="note" class="form-control" rows="2" maxlength="500" placeholder="وصف العائق بدقة..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger fw-bold"><i class="fas fa-hand" aria-hidden="true"></i> تسجيل العائق وإيقاف العملية</button>
            </div>
        </form>
    </div>
</div>
