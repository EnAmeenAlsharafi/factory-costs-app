{{-- Quick failure / reschedule capture: reason chip + optional note + new date. Params: $delivery, $sheetId --}}
@can('delivery.reschedule')
@php
    $presetReasons = ['العميل غير متواجد', 'تعذر الوصول للعنوان', 'العميل طلب التأجيل', 'تلف أثناء النقل', 'سبب آخر'];
@endphp
<div class="modal fade" id="{{ $sheetId }}" tabindex="-1" aria-labelledby="{{ $sheetId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form action="{{ route('delivery.orders.fail-or-reschedule', $delivery) }}" method="POST" class="modal-content"
              x-data="{ outcome: 'RESCHEDULED', preset: '', note: '' }">
            @csrf
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title h6 fw-bold mb-0" id="{{ $sheetId }}-title">تعذر التسليم / إعادة الجدولة</h2>
                    <div class="fs-7 text-muted">{{ $delivery->customer_name_snapshot }} &bull; <span class="ltr-isolate">{{ $delivery->delivery_number }}</span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <fieldset>
                    <legend class="form-label fs-6">ما الذي حدث؟</legend>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="new_status" value="RESCHEDULED" id="{{ $sheetId }}-resched" x-model="outcome">
                            <label class="btn btn-outline-dark w-100" for="{{ $sheetId }}-resched"><i class="fas fa-calendar-days" aria-hidden="true"></i> إعادة جدولة</label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="new_status" value="FAILED" id="{{ $sheetId }}-failed" x-model="outcome">
                            <label class="btn btn-outline-danger w-100" for="{{ $sheetId }}-failed"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> تعذر التسليم</label>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="form-label fs-6">السبب <span class="text-danger">*</span></legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($presetReasons as $index => $presetReason)
                            <input type="radio" class="btn-check" name="reason_preset" id="{{ $sheetId }}-reason-{{ $index }}" value="{{ $presetReason }}" x-model="preset" required>
                            <label class="btn btn-outline-secondary" for="{{ $sheetId }}-reason-{{ $index }}">{{ $presetReason }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <input type="hidden" name="reason" :value="note ? (preset + ' — ' + note) : preset">

                <div>
                    <label for="{{ $sheetId }}-note" class="form-label">ملاحظة <span x-show="preset === 'سبب آخر'" class="text-danger">*</span></label>
                    <textarea id="{{ $sheetId }}-note" class="form-control" rows="2" maxlength="900" x-model="note" :required="preset === 'سبب آخر'" placeholder="تفاصيل مختصرة..."></textarea>
                </div>

                <div x-show="outcome === 'RESCHEDULED'">
                    <label for="{{ $sheetId }}-date" class="form-label">موعد التوصيل الجديد</label>
                    <input type="date" id="{{ $sheetId }}-date" name="scheduled_delivery_date" class="form-control" min="{{ now()->toDateString() }}" :disabled="outcome !== 'RESCHEDULED'">
                </div>

                <div class="form-check form-switch">
                    {{-- Explicit 0 so unticking is honoured (the controller defaults a missing value to true). --}}
                    <input type="hidden" name="returned_to_factory" value="0">
                    <input class="form-check-input" type="checkbox" name="returned_to_factory" value="1" id="{{ $sheetId }}-return" checked>
                    <label class="form-check-label" for="{{ $sheetId }}-return">المنتجات أعيدت إلى مخزن المنتجات الجاهزة</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger fw-bold" :disabled="!preset">حفظ</button>
            </div>
        </form>
    </div>
</div>
@endcan
