{{--
    Return workflow: progress steps and the single next decision for the current status.
    Params: $return
--}}
@php
    $steps = [
        'REPORTED' => 'البلاغ',
        'APPROVED' => 'الاعتماد',
        'RECEIVED' => 'الاستلام للفحص',
        'INSPECTED' => 'قرار الفحص',
        'RESOLVED' => 'الإغلاق',
    ];
    $position = match ($return->status) {
        'REPORTED' => 0,
        'APPROVED' => 1,
        'RECEIVED', 'LINKED_TO_QUALITY' => 2,
        'UNDER_REPAIR', 'INSPECTED' => 3,
        'RESOLVED' => 4,
        default => -1,
    };
@endphp

<section class="card-factory p-3" aria-labelledby="return-workflow-heading">
    <h2 id="return-workflow-heading" class="h6 fw-bold text-dark mb-3"><i class="fas fa-route text-warning" aria-hidden="true"></i> مسار المرتجع</h2>

    @if ($return->status === 'REJECTED')
        <div class="alert alert-secondary mb-0">
            <strong>مرفوض.</strong> {{ $return->resolution_notes }}
        </div>
    @else
        <ol class="list-unstyled d-flex flex-wrap gap-2 mb-3" aria-label="مراحل المرتجع">
            @foreach (array_values($steps) as $index => $stepLabel)
                <li class="status-chip {{ $index < $position ? 'status-chip-success' : ($index === $position ? 'status-chip-warning' : '') }}"
                    @if ($index === $position) aria-current="step" @endif>
                    @if ($index < $position)<i class="fas fa-check" aria-hidden="true"></i>@endif
                    {{ $index + 1 }}. {{ $stepLabel }}
                </li>
            @endforeach
        </ol>

        @if ($return->inspection_decision)
            <p class="fs-7 mb-2">قرار الفحص: <strong>{{ \App\Models\CustomerReturn::INSPECTION_DECISIONS[$return->inspection_decision] ?? $return->inspection_decision }}</strong>
                @if ($return->inspectedByUser) — {{ $return->inspectedByUser->name }} @endif</p>
        @endif
        @if ($return->resolution_code)
            <p class="fs-7 mb-2">الحل للعميل: <strong>{{ \App\Models\CustomerReturn::RESOLUTIONS[$return->resolution_code] ?? $return->resolution_code }}</strong>
                @if ($return->resolution_notes) — {{ $return->resolution_notes }} @endif</p>
        @endif

        @can('delivery.manage_returns')
            @if (in_array($return->status, ['REPORTED', 'APPROVED'], true))
                <div class="d-flex flex-wrap gap-2">
                    @if ($return->status === 'REPORTED')
                        <form action="{{ route('customer-returns.approve', $return) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-factory-warning"><i class="fas fa-check" aria-hidden="true"></i> اعتماد الإرجاع</button>
                        </form>
                    @endif
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#receiveModal">
                        <i class="fas fa-warehouse" aria-hidden="true"></i> استلام المرتجع لمنطقة الفحص
                    </button>
                </div>
                <form action="{{ route('customer-returns.reject', $return) }}" method="POST" class="row g-2 align-items-end mt-2"
                      data-confirm="رفض طلب الإرجاع؟">
                    @csrf
                    <div class="col-sm-8">
                        <label for="reject-reason" class="form-label">رفض الطلب — السبب</label>
                        <input type="text" id="reject-reason" name="reason" class="form-control" required maxlength="500" placeholder="مثال: خارج مدة الضمان">
                    </div>
                    <div class="col-sm-4"><button type="submit" class="btn btn-outline-danger w-100">رفض الإرجاع</button></div>
                </form>
            @elseif (in_array($return->status, ['RECEIVED', 'LINKED_TO_QUALITY'], true))
                <form action="{{ route('customer-returns.inspect', $return) }}" method="POST" class="d-flex flex-column gap-2">
                    @csrf
                    <fieldset>
                        <legend class="form-label fs-7 fw-semibold">نتيجة فحص المنتج المرتجع <span class="text-danger">*</span></legend>
                        @foreach (\App\Models\CustomerReturn::INSPECTION_DECISIONS as $code => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="decision-{{ $code }}" value="{{ $code }}" required>
                                <label class="form-check-label" for="decision-{{ $code }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <label for="inspect-notes" class="form-label mb-0">ملاحظات الفحص</label>
                    <textarea id="inspect-notes" name="notes" class="form-control" rows="2" maxlength="1000"></textarea>
                    <div><button type="submit" class="btn btn-factory-warning"><i class="fas fa-clipboard-check" aria-hidden="true"></i> حفظ قرار الفحص</button></div>
                </form>
            @elseif ($return->status === 'UNDER_REPAIR')
                <form action="{{ route('customer-returns.complete-repair', $return) }}" method="POST"
                      data-confirm="تأكيد اكتمال الإصلاح وإعادة المنتج للمخزون المتاح؟">
                    @csrf
                    <p class="fs-7 text-muted">المنتج في منطقة الفحص بانتظار الإصلاح. عند انتهاء الإصلاح يعود للمخزون المتاح.</p>
                    <button type="submit" class="btn btn-factory-warning"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i> اكتمل الإصلاح</button>
                </form>
            @elseif ($return->status === 'INSPECTED')
                <form action="{{ route('customer-returns.resolve', $return) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-5">
                        <label for="resolution-code" class="form-label">الحل المقدم للعميل <span class="text-danger">*</span></label>
                        <select id="resolution-code" name="resolution_code" class="form-select" required>
                            <option value="">-- اختر --</option>
                            @foreach (\App\Models\CustomerReturn::RESOLUTIONS as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label for="resolution-notes" class="form-label">ملاحظة</label>
                        <input type="text" id="resolution-notes" name="resolution_notes" class="form-control" maxlength="1000" placeholder="مثال: رقم طلب الاستبدال">
                    </div>
                    <div class="col-sm-3"><button type="submit" class="btn btn-factory-warning w-100">إغلاق المرتجع</button></div>
                </form>
            @endif
        @endcan
    @endif
</section>
