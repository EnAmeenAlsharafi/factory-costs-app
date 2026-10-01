@extends('layouts.app')

@section('title', 'أمر توصيل - ' . $delivery->delivery_number)
@section('page-title', 'تفاصيل التوصيل')

@section('content')
@php
    $user = auth()->user();
    $isFieldUser = ! $user->can('delivery.assign');
    $backUrl = $isFieldUser ? route('delivery.orders.my-tasks') : route('delivery.orders.index');
    $phoneDial = $delivery->customer_phone_snapshot ? preg_replace('/[^0-9+]/', '', $delivery->customer_phone_snapshot) : null;
    $locationUrl = null;
    if ($delivery->location_notes && preg_match('~https?://\S+~u', $delivery->location_notes, $locationMatch)) {
        $locationUrl = $locationMatch[0];
    }
    $eventLabels = [
        'CREATED' => 'إنشاء أمر التوصيل', 'READY' => 'جاهز للتوصيل', 'ASSIGNED' => 'إسناد لسائق',
        'DISPATCHED' => 'خروج للتوصيل', 'DELIVERED' => 'تم التسليم', 'INSTALLED' => 'تم التركيب',
        'FAILED' => 'تعذر التسليم', 'RESCHEDULED' => 'إعادة جدولة', 'RETURNED_TO_FACTORY' => 'إعادة المنتجات للمصنع',
        'CANCELLED' => 'إلغاء', 'NOTE' => 'ملاحظة',
    ];
    $nextActionsHtml = trim(view('delivery.orders.partials.next-actions', ['delivery' => $delivery, 'failSheetId' => 'fail-sheet-show', 'assignSheetId' => 'assign-sheet'])->render());
@endphp

<div class="d-flex flex-column gap-3 mx-auto" style="max-width: 1100px;">
    {{-- 1. Identity + status (sticky on phones) --}}
    <div class="record-header record-header-sticky">
        <a href="{{ $backUrl }}" class="btn btn-light border record-header-back" aria-label="رجوع">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <div class="record-header-main">
            <h1 id="page-heading" class="record-header-title text-dark">{{ $delivery->customer_name_snapshot }}</h1>
            <div class="fs-8 text-muted"><span class="ltr-isolate">{{ $delivery->delivery_number }}</span>
                @if ($delivery->customerOrder)
                    &bull; طلب
                    @can('orders.view')
                        <a href="{{ route('sales.orders.show', $delivery->customerOrder) }}" class="ltr-isolate">{{ $delivery->customerOrder->order_number }}</a>
                    @else
                        <span class="ltr-isolate">{{ $delivery->customerOrder->order_number }}</span>
                    @endcan
                @endif
            </div>
        </div>
        <x-status-badge domain="delivery" :status="$delivery->status" size="lg" />
    </div>

    {{-- 2. Payment gate (commercial detail only for receivables-authorised users) --}}
    @if ($paymentGate)
        <div class="alert {{ $paymentGate['eligible'] ? 'alert-success' : 'alert-danger' }} mb-0 d-flex gap-2 align-items-start" role="status">
            <i class="fas {{ $paymentGate['eligible'] ? 'fa-circle-check' : 'fa-ban' }} mt-1" aria-hidden="true"></i>
            <div>
                <div class="fw-bold">{{ $paymentGate['headline'] }}</div>
                @if ($paymentGate['collect_amount'] !== null && $paymentGate['collect_amount'] > 0)
                    <div>المبلغ المطلوب تحصيله: <strong class="ltr-isolate">{{ number_format($paymentGate['collect_amount'], 2) }}</strong> ر.س</div>
                @endif
                @if ($paymentGate['detail'])
                    <div class="fs-7">{{ $paymentGate['detail'] }}</div>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5 d-flex flex-column gap-3">
            {{-- 3. Customer, phone, address --}}
            <section class="card-factory p-3" aria-labelledby="delivery-customer-heading">
                <h2 id="delivery-customer-heading" class="h6 fw-bold text-dark mb-3"><i class="fas fa-user text-primary" aria-hidden="true"></i> العميل والعنوان</h2>
                @if ($phoneDial)
                    <a href="tel:{{ $phoneDial }}" class="btn btn-success w-100 mb-3 fw-bold" style="min-height: 50px;">
                        <i class="fas fa-phone" aria-hidden="true"></i> اتصال بالعميل
                        <span class="ltr-isolate">{{ $delivery->customer_phone_snapshot }}</span>
                    </a>
                @else
                    <div class="alert alert-warning py-2 fs-7">لا يوجد رقم تواصل مسجل لهذا التوصيل.</div>
                @endif
                <dl class="detail-list">
                    <div><dt>المدينة</dt><dd>{{ $delivery->city_snapshot ?? 'غير محددة' }}</dd></div>
                    <div><dt>الحي</dt><dd>{{ $delivery->district_snapshot ?? '—' }}</dd></div>
                    <div class="span-all"><dt>العنوان التفصيلي</dt><dd class="fw-normal">{{ $delivery->delivery_address_snapshot ?? 'لا يوجد عنوان تفصيلي مدون' }}</dd></div>
                    @if ($delivery->location_notes)
                        <div class="span-all">
                            <dt>ملاحظات الموقع</dt>
                            <dd class="fw-normal">{{ $delivery->location_notes }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>موعد التوصيل</dt>
                        <dd>
                            @if ($delivery->scheduled_delivery_date)
                                {{ $delivery->scheduled_delivery_date->isToday() ? 'اليوم' : '' }} <span class="ltr-isolate">{{ $delivery->scheduled_delivery_date->format('Y-m-d') }}</span>
                            @else
                                غير محدد
                            @endif
                            @if ($delivery->scheduled_time_notes)
                                <div class="fs-8 text-muted fw-normal">{{ $delivery->scheduled_time_notes }}</div>
                            @endif
                        </dd>
                    </div>
                    <div><dt>التركيب</dt><dd>{{ $delivery->installation_required ? 'مطلوب' : 'غير مطلوب' }}</dd></div>
                </dl>
                @if ($locationUrl)
                    <a href="{{ $locationUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary w-100 mt-3">
                        <i class="fas fa-map-location-dot" aria-hidden="true"></i> فتح الموقع
                    </a>
                @endif
            </section>
        </div>

        <div class="col-lg-7 d-flex flex-column gap-3">
            {{-- 4. Items --}}
            <section class="card-factory p-3" aria-labelledby="delivery-items-heading">
                <h2 id="delivery-items-heading" class="h6 fw-bold text-dark mb-3"><i class="fas fa-couch text-primary" aria-hidden="true"></i> المنتجات ({{ $delivery->lines->count() }})</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    @foreach ($delivery->lines as $line)
                        @php
                            $linePo = $line->productionOrder;
                            $colorCode = $linePo?->fabric_color_code ?? $linePo?->fabricColor?->color_code;
                        @endphp
                        <li class="border rounded-3 p-2 d-flex justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-bold text-dark">{{ $linePo?->productModel?->name_ar ?? $linePo?->customerOrderLine?->productModel?->name_ar ?? 'منتج أثاث' }}</div>
                                <div class="fs-7 text-muted d-flex flex-wrap gap-2">
                                    @if ($linePo?->requested_width_cm)
                                        <span><i class="fas fa-ruler-combined" aria-hidden="true"></i> <span class="ltr-isolate">{{ (int) $linePo->requested_width_cm }}×{{ (int) $linePo->requested_length_cm }}</span> سم</span>
                                    @endif
                                    @if ($linePo?->fabricMaterial)
                                        <span><i class="fas fa-scroll" aria-hidden="true"></i> {{ $linePo->fabricMaterial->name_ar }}</span>
                                    @endif
                                    @if ($colorCode)
                                        <span><span class="color-dot" style="background: {{ $linePo->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span> <span class="ltr-isolate">{{ $colorCode }}</span></span>
                                    @endif
                                </div>
                                @if ($linePo)
                                    @can('production.view')
                                        <a href="{{ route('production.orders.show', $linePo) }}" class="fs-8 ltr-isolate">{{ $linePo->production_order_number }}</a>
                                    @else
                                        <span class="fs-8 text-muted ltr-isolate">{{ $linePo->production_order_number }}</span>
                                    @endcan
                                @endif
                            </div>
                            <span class="fs-5 fw-bold flex-shrink-0">× {{ (float) $line->quantity }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($delivery->returns->isNotEmpty())
                <section class="card-factory p-3" aria-labelledby="delivery-returns-heading">
                    <h2 id="delivery-returns-heading" class="h6 fw-bold text-dark mb-2"><i class="fas fa-rotate-left text-warning" aria-hidden="true"></i> مرتجعات مرتبطة</h2>
                    <ul class="list-unstyled mb-0 fs-7">
                        @foreach ($delivery->returns as $customerReturn)
                            <li class="py-1">
                                @can('delivery.manage_returns')
                                    <a href="{{ route('customer-returns.show', $customerReturn) }}" class="ltr-isolate">{{ $customerReturn->return_number }}</a>
                                @else
                                    <span class="ltr-isolate">{{ $customerReturn->return_number }}</span>
                                @endcan
                                — الكمية {{ (float) $customerReturn->quantity }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- 5. Secondary: assignment --}}
            <section class="card-factory p-3" aria-labelledby="delivery-assignment-heading">
                <h2 id="delivery-assignment-heading" class="h6 fw-bold text-dark mb-2"><i class="fas fa-id-card text-primary" aria-hidden="true"></i> الإسناد</h2>
                <dl class="detail-list">
                    <div><dt>السائق / الفني</dt><dd>{{ $delivery->assignedUser?->name ?? 'لم يُسند بعد' }}</dd></div>
                    @if ($delivery->dispatchedByUser)
                        <div><dt>الخروج للتوصيل</dt><dd>{{ $delivery->dispatchedByUser->name }} <span class="fs-8 text-muted ltr-isolate">{{ $delivery->dispatched_at?->format('Y-m-d H:i') }}</span></dd></div>
                    @endif
                    @if ($delivery->delivery_notes)
                        <div class="span-all"><dt>ملاحظات التوصيل</dt><dd class="fw-normal">{{ $delivery->delivery_notes }}</dd></div>
                    @endif
                </dl>
            </section>

            {{-- 6. Audit trail (collapsed) --}}
            <details class="card-factory p-3">
                <summary class="fw-bold text-dark" style="min-height: 44px; display: flex; align-items: center; gap: .5rem; cursor: pointer;">
                    <i class="fas fa-clock-rotate-left text-secondary" aria-hidden="true"></i> سجل الأحداث ({{ $delivery->events->count() }})
                </summary>
                <ol class="list-unstyled mb-0 mt-2">
                    @forelse ($delivery->events as $event)
                        <li class="border-bottom py-2">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold">{{ $eventLabels[$event->event_type] ?? $event->event_type }}</span>
                                <span class="fs-8 text-muted ltr-isolate">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                            <div class="fs-8 text-muted">بواسطة: {{ $event->user->name ?? 'النظام' }}</div>
                            @if ($event->notes)
                                <div class="fs-7 mt-1">{{ $event->notes }}</div>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted py-2">لا توجد أحداث مسجلة بعد.</li>
                    @endforelse
                </ol>
            </details>
        </div>
    </div>

    {{-- Next action: fixed at the bottom on phones, inline on larger screens --}}
    @if ($nextActionsHtml !== '')
        <x-mobile-action-bar>
            {!! $nextActionsHtml !!}
        </x-mobile-action-bar>
    @elseif (in_array($delivery->status, ['FAILED', 'RESCHEDULED'], true))
        <div class="alert alert-secondary mb-0" role="status"><i class="fas fa-circle-info" aria-hidden="true"></i> هذه المهمة بانتظار قرار الإدارة لإعادة الجدولة أو الإلغاء.</div>
    @endif
</div>

@if ($delivery->status === 'READY_FOR_DELIVERY')
    @can('delivery.assign')
        <div class="modal fade" id="assign-sheet" tabindex="-1" aria-labelledby="assign-sheet-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <form action="{{ route('delivery.orders.assign', $delivery) }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h6 fw-bold" id="assign-sheet-title">إسناد لسائق / فني</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <label for="assigned-user" class="form-label">السائق / الفني <span class="text-danger">*</span></label>
                        <select name="assigned_user_id" id="assigned-user" class="form-select" required>
                            <option value="">-- اختر --</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected($delivery->assigned_user_id == $driver->id)>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold">حفظ الإسناد</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endif

@if ($delivery->status === 'OUT_FOR_DELIVERY')
    @include('delivery.orders.partials.fail-sheet', ['delivery' => $delivery, 'sheetId' => 'fail-sheet-show'])
@endif
@endsection
