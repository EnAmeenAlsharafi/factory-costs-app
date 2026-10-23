@props(['order'])

@php
    $trackingService = app(\App\Services\OrderTrackingService::class);
    $summary = $trackingService->summary($order);
    $productionOrders = $order->productionOrders ?? \App\Models\ProductionOrder::where('customer_order_id', $order->id)->where('status', '!=', 'CANCELLED')->get();
@endphp

<div class="card border-0 shadow-sm mb-4 rounded-3" aria-labelledby="order-tracking-title">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 id="order-tracking-title" class="h6 card-title mb-0 fw-bold text-dark">
            <i class="fas fa-route text-primary me-2" aria-hidden="true"></i> أين وصل الطلب؟ (متابعة الطلب)
        </h2>
        <span class="badge {{ $summary['badge_class'] }} fs-7 px-3 py-2">{{ $summary['status_label'] }}</span>
    </div>
    <div class="card-body p-3">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-md-6">
                <small class="text-muted d-block fw-bold">المرحلة الحالية:</small>
                <div class="fw-bold text-dark">{{ $summary['current_stage'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block fw-bold">تقدم المراحل:</small>
                <div class="fw-bold text-primary"><span class="ltr-isolate">{{ $summary['stages_completed'] }} / {{ $summary['stages_total'] }}</span></div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block fw-bold">آخر تحديث:</small>
                <div class="text-dark fs-7">{{ $summary['last_update']?->diffForHumans() ?? '—' }}</div>
            </div>
        </div>

        @if ($summary['stages_total'] > 0)
            <div class="progress mb-3" style="height: 10px;" role="progressbar" aria-valuenow="{{ $summary['stages_completed'] }}" aria-valuemin="0" aria-valuemax="{{ $summary['stages_total'] }}">
                <div class="progress-bar {{ $summary['is_blocked'] ? 'bg-danger' : 'bg-success' }}" style="width: {{ $summary['stages_total'] > 0 ? round(($summary['stages_completed'] / $summary['stages_total']) * 100) : 0 }}%"></div>
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2">
            @if ($summary['has_open_rework'])
                <span class="rework-flag"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل مفتوحة</span>
            @endif
            @if ($summary['is_blocked'])
                <span class="status-chip status-chip-danger"><i class="fas fa-hand" aria-hidden="true"></i> متوقف — بانتظار حل العائق</span>
            @endif
        </div>

        @if ($productionOrders->isNotEmpty())
            <hr>
            <div class="d-flex flex-column gap-3">
                @foreach ($productionOrders as $po)
                    @php $rows = $trackingService->productionProgress($po); @endphp
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <a href="{{ route('production.orders.show', $po) }}" class="fw-bold text-decoration-none fs-7">
                                أمر الإنتاج <span class="ltr-isolate">{{ $po->production_order_number }}</span>
                            </a>
                            <span class="badge {{ $po->status_badge_class }} fs-8">{{ $po->status_arabic }}</span>
                        </div>
                        <div class="process-tracker" role="list" aria-label="مراحل التصنيع لأمر الإنتاج">
                            @foreach ($rows as $row)
                                <div class="process-tracker-step" role="listitem">
                                    <x-status-badge domain="operation" :status="$row['status']" />
                                    <div class="fs-8 text-muted mt-1">{{ $row['name'] }}</div>
                                    <div class="fs-8 fw-bold ltr-isolate">{{ $row['completed'] }} / {{ $row['required'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
