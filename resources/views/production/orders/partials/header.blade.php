{{-- Production order header: identity, status, secondary actions, then the order journey with the one next step. --}}
@php
    $user = auth()->user();
    $isReleased = ! in_array($order->status, ['DRAFT', 'READY_FOR_RELEASE'], true);
    $liveRequests = $order->materialRequests->where('status', '!=', 'REJECTED');
    $materialsIssued = $order->materialIssues->isNotEmpty() || $liveRequests->contains(fn ($request) => in_array($request->status, ['FULFILLED', 'PARTIALLY_FULFILLED'], true));
    $totalOps = $order->operations->count();
    $doneOps = $order->operations->whereIn('status', ['COMPLETED', 'SKIPPED'])->count();
    $readyUnassigned = $order->operations->where('status', 'READY')->whereNull('assigned_user_id')->count();
    $receivedQty = (float) $order->finishedGoodsReceipts->where('status', 'POSTED')->sum('quantity');
    $awaitingReceipt = (float) $order->completed_quantity - $receivedQty;
    $fullyReceived = $receivedQty > 0 && $receivedQty >= (float) $order->released_quantity;

    $journey = [
        ['label' => 'الإطلاق للورشة', 'detail' => $isReleased ? ($order->released_at?->format('Y-m-d') ?? 'تم') : 'لم يُطلق', 'state' => $isReleased ? 'done' : 'current'],
        ['label' => 'الخامات', 'detail' => $materialsIssued ? 'صُرفت' : ($liveRequests->isNotEmpty() ? 'بانتظار المستودع' : 'لم تُطلب'), 'state' => ! $isReleased ? 'todo' : ($materialsIssued ? 'done' : 'current')],
        ['label' => 'التنفيذ في الأقسام', 'detail' => $totalOps ? "{$doneOps} من {$totalOps} مراحل" : '—', 'state' => ! $isReleased ? 'todo' : ($totalOps && $doneOps === $totalOps ? 'done' : 'current')],
        ['label' => 'الاستلام في المخزون', 'detail' => $receivedQty > 0 ? (int) $receivedQty.' من '.$order->released_quantity.' قطعة' : 'لم يُستلم', 'state' => $fullyReceived ? 'done' : ($awaitingReceipt > 0 ? 'current' : 'todo')],
    ];

    $nextAction = match (true) {
        $order->status === 'CANCELLED' => ['text' => 'الأمر ملغى.', 'url' => null, 'label' => null],
        $order->status === 'ON_HOLD' => ['text' => 'الأمر معلّق: '.($order->hold_reason ?: 'بدون سبب مدوّن').'. استأنفه عند زوال السبب.', 'url' => null, 'label' => null],
        ! $isReleased => ['text' => 'اختر مسار التصنيع وأطلق الأمر لتظهر مهامه في الأقسام.', 'url' => $user->can('production.release') ? route('production.orders.release.form', $order) : null, 'label' => 'إطلاق للورشة'],
        $liveRequests->isEmpty() && ! $fullyReceived && ! $order->manufacturing_recipe_version_id => ['text' => 'هذا الأمر غير مرتبط بوصفة. اربطه بوصفة جاهزة لتُحسب الخامات تلقائياً، أو اطلب الخامات يدوياً.', 'url' => ($recipeChoices ?? null) !== null ? '#recipe' : ($user->can('production.material_requests') ? route('production.material-requests.create', ['production_order_id' => $order->id]) : null), 'label' => ($recipeChoices ?? null) !== null ? 'ربط وصفة' : 'طلب يدوي'],
        $liveRequests->isEmpty() && ! $fullyReceived => ['text' => 'لم تُطلب خامات لهذا الأمر بعد. اطلبها من المستودع قبل بدء التصنيع.', 'url' => $user->can('production.material_requests') ? route('production.material-requests.create', ['production_order_id' => $order->id]) : null, 'label' => 'طلب الخامات'],
        $readyUnassigned > 0 => ['text' => "{$readyUnassigned} مرحلة جاهزة ولم تُسند لأحد. أسندها من قائمة المراحل أدناه.", 'url' => '#operations', 'label' => 'إسناد المراحل الجاهزة'],
        $awaitingReceipt > 0 => ['text' => (int) $awaitingReceipt.' قطعة مكتملة بانتظار التسليم لمخزن المنتجات الجاهزة.', 'url' => $user->can('finished_goods.receive') ? route('finished-goods.receipts.create', ['production_order_id' => $order->id]) : null, 'label' => 'تسليم للمخزن'],
        $fullyReceived => ['text' => 'اكتمل الأمر واستُلم في المخزون، وأصبح جاهزاً للتوصيل.', 'url' => null, 'label' => null],
        default => ['text' => 'الأمر قيد التنفيذ في الأقسام. تابع تقدم كل مرحلة أدناه.', 'url' => '#operations', 'label' => 'عرض المراحل'],
    };
@endphp

<div class="page-header-card mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div class="min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-0"><bdi class="text-nowrap">{{ $order->production_order_number }}</bdi></h1>
                <x-status-badge domain="production_order" :status="$order->status" />
                @if (in_array($order->priority, ['URGENT', 'VIP'], true))
                    <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $order->priority === 'VIP' ? 'VIP' : 'عاجل' }}</span>
                @endif
                @if ($order->has_customer_order_changed)
                    <span class="status-chip status-chip-warning" title="تم تعديل طلب العميل في قسم المبيعات بعد إطلاق أمر الإنتاج"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> عُدّل طلب العميل</span>
                @endif
            </div>
            <p class="fw-semibold text-dark mb-1">
                {{ $order->display_name }}
                <span class="text-muted fw-normal">— <bdi>{{ (float) $order->requested_width_cm }}×{{ (float) $order->requested_length_cm }}</bdi> سم · {{ $order->released_quantity ?: $order->ordered_quantity }} قطعة</span>
            </p>
            <p class="page-header-subtitle text-muted mb-0 fs-7">
                طلب العميل: <a href="{{ route('sales.orders.show', $order->customerOrder) }}" class="fw-bold text-decoration-none"><bdi>{{ $order->customerOrder?->order_number }}</bdi></a>
                &bull; {{ $order->customerOrder?->customer?->name }}
                @if ($order->customerOrder?->requested_delivery_date)
                    &bull; التسليم المطلوب: <bdi>{{ $order->customerOrder->requested_delivery_date->format('Y-m-d') }}</bdi>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @if (in_array($order->status, ['RELEASED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'], true) && $user->can('production.hold'))
                <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#holdModal">
                    <i class="fas fa-pause me-1" aria-hidden="true"></i> تعليق
                </button>
            @elseif ($order->status === 'ON_HOLD' && $user->can('production.hold'))
                <form action="{{ route('production.orders.resume', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fas fa-play me-1" aria-hidden="true"></i> استئناف</button>
                </form>
            @endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis me-1" aria-hidden="true"></i> إجراءات أخرى
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @can('production.material_requests')
                        <li><a class="dropdown-item" href="{{ route('production.material-requests.create', ['production_order_id' => $order->id]) }}"><i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>طلب خامات</a></li>
                    @endcan
                    <li><a class="dropdown-item" href="{{ route('production.quality-incidents.create', ['production_order_id' => $order->id]) }}"><i class="fas fa-exclamation-circle me-2 text-danger" aria-hidden="true"></i>بلاغ جودة</a></li>
                    <li><a class="dropdown-item" href="{{ route('production.waste.create', ['production_order_id' => $order->id]) }}"><i class="fas fa-dumpster me-2" aria-hidden="true"></i>تسجيل هدر</a></li>
                    @if ((float) $order->completed_quantity > 0 && $user->can('finished_goods.receive'))
                        <li><a class="dropdown-item" href="{{ route('finished-goods.receipts.create', ['production_order_id' => $order->id]) }}"><i class="fas fa-boxes me-2 text-success" aria-hidden="true"></i>تسليم للمخزن الجاهز</a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('production.orders.index') }}"><i class="fas fa-arrow-right me-2" aria-hidden="true"></i>كل أوامر الإنتاج</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<section class="card-factory order-journey mb-4" aria-labelledby="journey-heading">
    <h2 id="journey-heading" class="visually-hidden">رحلة أمر الإنتاج</h2>
    <ol class="order-journey-steps">
        @foreach ($journey as $step)
            <li class="order-journey-step is-{{ $step['state'] }}">
                <span class="order-journey-dot" aria-hidden="true">
                    @if ($step['state'] === 'done')
                        <i class="fas fa-check"></i>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span class="order-journey-text">
                    <span class="order-journey-label">{{ $step['label'] }}</span>
                    <span class="order-journey-detail">{{ $step['detail'] }}</span>
                </span>
                <span class="visually-hidden">{{ ['done' => '(مكتملة)', 'current' => '(المرحلة الحالية)', 'todo' => '(لم تبدأ)'][$step['state']] }}</span>
            </li>
        @endforeach
    </ol>
    <div class="order-journey-next">
        <p class="mb-0 min-w-0"><i class="fas fa-circle-arrow-left text-primary me-1" aria-hidden="true"></i><strong>الخطوة التالية:</strong> {{ $nextAction['text'] }}</p>
        @if ($nextAction['url'])
            <a href="{{ $nextAction['url'] }}" class="btn btn-primary fw-bold text-nowrap">{{ $nextAction['label'] }}</a>
        @endif
    </div>
</section>
