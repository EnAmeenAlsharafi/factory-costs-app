{{--
    Sales order journey: where the order is in the factory (approval → release → departments →
    delivery) and the one thing to do next, with each production order's stage tracker beneath.
--}}
@php
    $tracking = app(\App\Services\OrderTrackingService::class);
    $journey ??= $tracking->journey($order);
    $next = $journey['next'];
    $stateLabels = ['done' => '(مكتملة)', 'current' => '(المرحلة الحالية)', 'upcoming' => '(لم تبدأ)', 'skipped' => '(لا تنطبق)'];
@endphp
<section class="card-factory order-journey mb-4" aria-labelledby="order-journey-heading">
    <h2 id="order-journey-heading" class="h6 fw-bold mb-3"><i class="fas fa-route text-primary me-2" aria-hidden="true"></i>أين وصل الطلب؟</h2>
    <ol class="order-journey-steps">
        @foreach ($journey['steps'] as $step)
            <li class="order-journey-step is-{{ $step['state'] }}">
                <span class="order-journey-dot" aria-hidden="true">
                    @if ($step['state'] === 'done')
                        <i class="fas fa-check"></i>
                    @elseif ($step['state'] === 'skipped')
                        <i class="fas fa-minus"></i>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span class="order-journey-text">
                    <span class="order-journey-label">{{ $step['label'] }}</span>
                    @if ($step['detail'])<span @class(['order-journey-detail', 'text-danger fw-semibold' => $step['late'] ?? false])>@if ($step['late'] ?? false)<i class="fas fa-hourglass-end me-1" aria-hidden="true"></i>@endif{{ $step['detail'] }}</span>@endif
                </span>
                <span class="visually-hidden">{{ $stateLabels[$step['state']] }}</span>
            </li>
        @endforeach
    </ol>
    <div class="order-journey-next is-{{ $next['tone'] }}">
        <div class="min-w-0">
            <p class="mb-0 fw-bold"><i class="fas fa-circle-arrow-left me-1" aria-hidden="true"></i>الخطوة التالية: {{ $next['title'] }}</p>
            <p class="mb-0 small">{{ $next['text'] }}</p>
        </div>
        @if ($next['action'])
            <a href="{{ $next['action']['url'] }}" class="btn btn-primary fw-bold text-nowrap"><i class="fas {{ $next['action']['icon'] }} me-1" aria-hidden="true"></i>{{ $next['action']['label'] }}</a>
        @endif
    </div>

    @if ($journey['production_orders']->isNotEmpty())
        <div class="d-flex flex-column gap-3 mt-3">
            @foreach ($journey['production_orders'] as $productionOrder)
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <a href="{{ route('production.orders.show', $productionOrder) }}" class="fw-bold text-decoration-none fs-7">أمر الإنتاج <bdi>{{ $productionOrder->production_order_number }}</bdi> — {{ $productionOrder->display_name }}</a>
                        <x-status-badge domain="production_order" :status="$productionOrder->status" />
                    </div>
                    <div class="process-tracker" role="list" aria-label="مراحل التصنيع لأمر الإنتاج {{ $productionOrder->production_order_number }}">
                        @foreach ($tracking->productionProgress($productionOrder) as $row)
                            <div class="process-tracker-step" role="listitem">
                                <x-status-badge domain="operation" :status="$row['status']" />
                                <div class="fs-8 text-muted mt-1">{{ $row['name'] }}</div>
                                <div class="fs-8 fw-bold"><bdi>{{ $row['completed'] }} / {{ $row['required'] }}</bdi></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
