{{--
    Renders only the valid next workflow actions for a delivery (per DeliveryOrderService::TRANSITIONS)
    and only those the user is permitted to perform. Server-side checks remain authoritative.

    Params: $delivery, $failSheetId, $assignSheetId (nullable: when null the assign action links to the detail page)
--}}
@php
    $allowed = \App\Services\DeliveryOrderService::TRANSITIONS[$delivery->status] ?? [];
    $customer = $delivery->customer_name_snapshot;
@endphp

@if (in_array('READY_FOR_DELIVERY', $allowed, true))
    @can('delivery.create')
        <form action="{{ route('delivery.orders.ready', $delivery) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-box" aria-hidden="true"></i> تحويل إلى جاهز للتوصيل
            </button>
        </form>
    @endcan
@endif

@if (in_array('ASSIGNED', $allowed, true))
    @can('delivery.assign')
        @if ($assignSheetId)
            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#{{ $assignSheetId }}">
                <i class="fas fa-user-check" aria-hidden="true"></i> إسناد لسائق
            </button>
        @else
            <a href="{{ route('delivery.orders.show', $delivery) }}" class="btn btn-primary w-100">
                <i class="fas fa-user-check" aria-hidden="true"></i> إسناد لسائق
            </a>
        @endif
    @else
        <div class="text-muted fs-7 text-center align-self-center"><i class="fas fa-hourglass-half" aria-hidden="true"></i> بانتظار إسناد المشرف</div>
    @endcan
@endif

@if (in_array('OUT_FOR_DELIVERY', $allowed, true))
    @can('delivery.dispatch')
        <form action="{{ route('delivery.orders.dispatch', $delivery) }}" method="POST"
              data-confirm="تأكيد بدء التوصيل إلى {{ $customer }}؟ سيتم إخراج المنتجات من مخزن المنتجات الجاهزة.">
            @csrf
            <button type="submit" class="btn btn-warning text-dark w-100">
                <i class="fas fa-truck-fast" aria-hidden="true"></i> بدء التوصيل
            </button>
        </form>
    @endcan
@endif

@if (in_array('DELIVERED', $allowed, true))
    @can('delivery.complete')
        <form action="{{ route('delivery.orders.complete', $delivery) }}" method="POST"
              data-confirm="تأكيد تسليم المنتجات إلى {{ $customer }}؟">
            @csrf
            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-circle-check" aria-hidden="true"></i> تم التسليم
            </button>
        </form>
    @endcan
@endif

@if (in_array('FAILED', $allowed, true) || in_array('RESCHEDULED', $allowed, true))
    @can('delivery.reschedule')
        <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#{{ $failSheetId }}">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> تعذر / إعادة جدولة
        </button>
    @endcan
@endif

@if (in_array('INSTALLATION_COMPLETED', $allowed, true) && $delivery->installation_required)
    @can('delivery.install')
        <form action="{{ route('delivery.orders.install', $delivery) }}" method="POST"
              data-confirm="تأكيد اكتمال التركيب لدى {{ $customer }}؟">
            @csrf
            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-screwdriver-wrench" aria-hidden="true"></i> تم التركيب
            </button>
        </form>
    @endcan
@endif
