{{--
    Driver sheets for the door outcome. Params: $delivery, $sheetId.
    Renders the "delivered" sheet (#complete-{sheetId}: recipient name = proof of delivery) and the
    "could not deliver" sheet (#{sheetId}: structured reason, goods location, optional new date).
--}}
@can('delivery.complete')
<div class="modal fade" id="complete-{{ $sheetId }}" tabindex="-1" aria-labelledby="complete-{{ $sheetId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <form action="{{ route('delivery.orders.complete', $delivery) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title h6 fw-bold mb-0" id="complete-{{ $sheetId }}-title">تأكيد التسليم</h2>
                    <div class="fs-7 text-muted">{{ $delivery->customer_name_snapshot }} &bull; <span class="ltr-isolate">{{ $delivery->delivery_number }}</span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <div>
                    <label for="complete-{{ $sheetId }}-recipient" class="form-label">اسم المستلم</label>
                    <input type="text" id="complete-{{ $sheetId }}-recipient" name="recipient_name" class="form-control" maxlength="120"
                           value="{{ $delivery->customer_name_snapshot }}" autocomplete="off">
                    <div class="form-text">من استلم المنتجات فعلياً (العميل أو من ينوب عنه).</div>
                </div>
                <div>
                    <label for="complete-{{ $sheetId }}-notes" class="form-label">ملاحظة</label>
                    <textarea id="complete-{{ $sheetId }}-notes" name="notes" class="form-control" rows="2" maxlength="900" placeholder="اختياري"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">رجوع</button>
                <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-circle-check" aria-hidden="true"></i> تم التسليم</button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('delivery.reschedule')
@php
    $reasonGroups = [
        'العميل أو العنوان' => ['CUSTOMER_UNAVAILABLE', 'CUSTOMER_POSTPONED', 'WRONG_ADDRESS'],
        'رفض الاستلام' => ['REFUSED_DEFECT', 'REFUSED_WRONG_SPEC', 'REFUSED_CHANGED_MIND', 'REFUSED_PAYMENT'],
        'أخرى' => ['TRANSPORT_DAMAGE', 'VEHICLE_ISSUE', 'OTHER'],
    ];
    $quarantineReasons = \App\Models\DeliveryOrder::QUARANTINE_REASONS;
@endphp
<div class="modal fade" id="{{ $sheetId }}" tabindex="-1" aria-labelledby="{{ $sheetId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form action="{{ route('delivery.orders.fail-or-reschedule', $delivery) }}" method="POST" class="modal-content"
              x-data="{ outcome: 'FAILED', code: '', quarantine: @js($quarantineReasons) }">
            @csrf
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title h6 fw-bold mb-0" id="{{ $sheetId }}-title">تعذر التسليم</h2>
                    <div class="fs-7 text-muted">{{ $delivery->customer_name_snapshot }} &bull; <span class="ltr-isolate">{{ $delivery->delivery_number }}</span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <fieldset>
                    <legend class="form-label fs-6">ما الذي حدث؟ <span class="text-danger">*</span></legend>
                    @foreach ($reasonGroups as $groupLabel => $codes)
                        <div class="fs-8 text-muted fw-semibold mt-2 mb-1">{{ $groupLabel }}</div>
                        <div class="d-flex flex-column gap-2">
                            @foreach ($codes as $code)
                                <input type="radio" class="btn-check" name="reason_code" id="{{ $sheetId }}-{{ $code }}" value="{{ $code }}" x-model="code" required>
                                <label class="btn btn-outline-secondary text-start" for="{{ $sheetId }}-{{ $code }}">{{ \App\Models\DeliveryOrder::FAILURE_REASONS[$code] }}</label>
                            @endforeach
                        </div>
                    @endforeach
                </fieldset>

                <div x-show="quarantine.includes(code)" class="alert alert-warning mb-0 fs-7" role="status">
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    المنتجات ستذهب لمنطقة الفحص ولن تُعاد للبيع قبل قرار الفحص، ويُفتح بلاغ جودة عند العيب أو التلف.
                </div>

                <div>
                    <label for="{{ $sheetId }}-note" class="form-label">تفاصيل <span x-show="code === 'OTHER'" class="text-danger">*</span></label>
                    <textarea id="{{ $sheetId }}-note" name="reason" class="form-control" rows="2" maxlength="900" :required="code === 'OTHER'" placeholder="مثال: الخدش في الجهة اليمنى من الظهر"></textarea>
                </div>

                <div class="form-check form-switch">
                    <input type="hidden" name="new_status" :value="code === 'CUSTOMER_POSTPONED' ? 'RESCHEDULED' : 'FAILED'">
                    {{-- Explicit 0 so unticking is honoured (the controller defaults a missing value to true). --}}
                    <input type="hidden" name="returned_to_factory" value="0">
                    <input class="form-check-input" type="checkbox" name="returned_to_factory" value="1" id="{{ $sheetId }}-return" checked>
                    <label class="form-check-label" for="{{ $sheetId }}-return">المنتجات عادت معي للمصنع</label>
                    <div class="form-text">ألغِ التحديد إذا بقيت المنتجات في المركبة؛ تؤكد الإدارة عودتها لاحقاً.</div>
                </div>

                <div x-show="code === 'CUSTOMER_POSTPONED'">
                    <label for="{{ $sheetId }}-date" class="form-label">الموعد الذي طلبه العميل</label>
                    <input type="date" id="{{ $sheetId }}-date" name="scheduled_delivery_date" class="form-control" min="{{ now()->toDateString() }}" :disabled="code !== 'CUSTOMER_POSTPONED'">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">رجوع</button>
                <button type="submit" class="btn btn-danger fw-bold" :disabled="!code">حفظ</button>
            </div>
        </form>
    </div>
</div>
@endcan
