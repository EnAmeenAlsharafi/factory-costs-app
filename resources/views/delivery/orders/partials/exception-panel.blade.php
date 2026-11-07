{{--
    Outcome of a failed / postponed / completed delivery, and the management decision for a failure.
    Params: $delivery
--}}
@php
    $locationTone = ['FACTORY' => 'success', 'QUARANTINE' => 'warning', 'WITH_DRIVER' => 'danger'];
@endphp

@if ($delivery->recipient_name && in_array($delivery->status, ['DELIVERED', 'INSTALLATION_COMPLETED'], true))
    <div class="alert alert-success mb-0 d-flex gap-2 align-items-center" role="status">
        <i class="fas fa-signature" aria-hidden="true"></i>
        <span>استلمها: <strong>{{ $delivery->recipient_name }}</strong>@if ($delivery->delivered_at) &bull; <span class="ltr-isolate">{{ $delivery->delivered_at->format('Y-m-d H:i') }}</span>@endif</span>
    </div>
@endif

@if (in_array($delivery->status, ['FAILED', 'RESCHEDULED'], true))
    <section class="card-factory p-3 border-warning" aria-labelledby="delivery-exception-heading">
        <h2 id="delivery-exception-heading" class="h6 fw-bold text-dark mb-3">
            <i class="fas fa-triangle-exclamation text-warning" aria-hidden="true"></i>
            {{ $delivery->status === 'RESCHEDULED' ? 'طلب العميل التأجيل' : 'تعذر التسليم' }}
        </h2>

        <dl class="detail-list mb-3">
            <div><dt>السبب</dt><dd>{{ $delivery->failureReasonLabel() ?? $delivery->delivery_notes ?? '—' }}</dd></div>
            @if ($delivery->goods_location)
                <div><dt>مكان البضاعة</dt><dd><span class="status-chip status-chip-{{ $locationTone[$delivery->goods_location] ?? 'secondary' }}">{{ \App\Models\DeliveryOrder::GOODS_LOCATIONS[$delivery->goods_location] ?? $delivery->goods_location }}</span></dd></div>
            @endif
            @if ($delivery->status === 'RESCHEDULED' && $delivery->scheduled_delivery_date)
                <div><dt>الموعد الذي طلبه العميل</dt><dd class="ltr-isolate">{{ $delivery->scheduled_delivery_date->format('Y-m-d') }}</dd></div>
            @endif
        </dl>

        @if ($delivery->successor)
            <div class="alert alert-info mb-0 fs-7">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                أعيدت جدولته بأمر التوصيل
                <a href="{{ route('delivery.orders.show', $delivery->successor) }}" class="fw-bold ltr-isolate">{{ $delivery->successor->delivery_number }}</a>.
            </div>
        @elseif ($delivery->goods_location === 'QUARANTINE')
            <p class="fs-7 text-muted mb-3">
                المنتجات في منطقة الفحص. لا يمكن إعادة توصيلها قبل أن يقرر الفحص أنها سليمة
                @can('finished_goods.view')
                    (<a href="{{ route('finished-goods.quarantine') }}">منطقة الفحص</a>)
                @endcan
                ، أو قرر إلغاء التوصيل.
            </p>
        @endif

        @if ($delivery->isAwaitingDecision())
            @can('delivery.assign')
                <div class="d-flex flex-column gap-3">
                    @if ($delivery->goods_location === 'WITH_DRIVER')
                        <form action="{{ route('delivery.orders.confirm-goods-returned', $delivery) }}" method="POST"
                              data-confirm="تأكيد أن المنتجات عادت فعلياً للمصنع؟">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark w-100 fw-semibold">
                                <i class="fas fa-warehouse" aria-hidden="true"></i> تأكيد عودة البضاعة للمصنع
                            </button>
                        </form>
                    @else
                        <form action="{{ route('delivery.orders.reschedule-as-new', $delivery) }}" method="POST" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-5">
                                <label for="reschedule-date" class="form-label">موعد التوصيل الجديد <span class="text-danger">*</span></label>
                                <input type="date" id="reschedule-date" name="scheduled_delivery_date" class="form-control" required min="{{ now()->toDateString() }}"
                                       value="{{ $delivery->status === 'RESCHEDULED' && $delivery->scheduled_delivery_date?->isFuture() ? $delivery->scheduled_delivery_date->format('Y-m-d') : '' }}">
                            </div>
                            <div class="col-sm-4">
                                <label for="reschedule-notes" class="form-label">ملاحظة للفريق</label>
                                <input type="text" id="reschedule-notes" name="notes" class="form-control" maxlength="500" placeholder="اختياري">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-factory-warning w-100 fw-semibold">
                                    <i class="fas fa-calendar-plus" aria-hidden="true"></i> إعادة جدولة
                                </button>
                            </div>
                        </form>

                        <form action="{{ route('delivery.orders.cancel-after-failure', $delivery) }}" method="POST" class="row g-2 align-items-end"
                              data-confirm="إلغاء أمر التوصيل نهائياً؟ المنتجات تبقى في المصنع.">
                            @csrf
                            <div class="col-sm-9">
                                <label for="cancel-reason" class="form-label">سبب الإلغاء</label>
                                <input type="text" id="cancel-reason" name="reason" class="form-control" maxlength="500" required placeholder="مثال: العميل ألغى الطلب">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-outline-danger w-100">إلغاء التوصيل</button>
                            </div>
                        </form>
                    @endif
                </div>
            @else
                <div class="alert alert-secondary mb-0 fs-7" role="status"><i class="fas fa-circle-info" aria-hidden="true"></i> بانتظار قرار مشرف التوصيل: إعادة الجدولة أو الإلغاء.</div>
            @endcan
        @endif
    </section>
@endif
