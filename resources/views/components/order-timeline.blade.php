@props(['timeline', 'emptyText' => 'لا توجد أحداث مسجلة بعد لهذا الطلب.'])

@if (empty($timeline))
    <div class="card-factory empty-state" role="status">
        <i class="fas fa-timeline fs-2 mb-3 d-block text-secondary" aria-hidden="true"></i>
        <p class="mb-0 fw-semibold">{{ $emptyText }}</p>
    </div>
@else
    <ul class="order-timeline" aria-label="سجل رحلة الطلب">
        @foreach (array_reverse($timeline) as $event)
            <li class="order-timeline-item">
                <span class="order-timeline-icon"><i class="fas {{ $event['icon'] }}" aria-hidden="true"></i></span>
                <div class="order-timeline-body">
                    <div class="order-timeline-label">{{ $event['label'] }}</div>
                    <div class="order-timeline-meta">
                        {{ $event['occurred_at']?->format('Y-m-d H:i') }}
                        @if ($event['actor'])
                            &bull; {{ $event['actor'] }}
                        @endif
                    </div>
                    @if ($event['note'])
                        <div class="order-timeline-note">{{ $event['note'] }}</div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
