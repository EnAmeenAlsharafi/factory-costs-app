@extends('layouts.app')

@section('title', 'أداء '.$worker->name.' — '.$month->translatedFormat('F Y'))
@section('page-title', 'أداء الموظف')

@section('content')
@php
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    $maxDay = max(1, (float) $report['daily']->max('pieces'));
    $maxCategory = max(1, (float) $report['categories']->max('points'));
    $previous = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth();
@endphp
<div class="report-workspace d-flex flex-column gap-3">
    <div class="page-header-card d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <nav aria-label="مسار التنقل" class="small mb-1"><a href="{{ route('reports.index') }}" class="text-decoration-none">مركز التقارير</a> / <a href="{{ route('reports.workers.index', ['month' => $month->format('Y-m')]) }}" class="text-decoration-none">أداء الموظفين</a></nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1">{{ $worker->name }}</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">{{ $worker->department?->name_ar ?? 'بدون قسم' }} · {{ $month->translatedFormat('F Y') }}</p>
        </div>
        <nav class="d-flex gap-2" aria-label="تغيير الشهر">
            <a class="btn btn-outline-secondary" href="{{ route('reports.workers.show', ['worker' => $worker, 'month' => $previous]) }}"><i class="fas fa-angle-right me-1" aria-hidden="true"></i> الشهر السابق</a>
            @if ($next->lte(now()))
                <a class="btn btn-outline-secondary" href="{{ route('reports.workers.show', ['worker' => $worker, 'month' => $next->format('Y-m')]) }}">الشهر التالي <i class="fas fa-angle-left ms-1" aria-hidden="true"></i></a>
            @endif
        </nav>
    </div>

    <div class="report-kpis">
        <x-report.kpi label="النقاط المكتسبة" :value="$number($report['totals']['points'])" hint="أساس مكافأة الشهر" />
        <x-report.kpi label="القطع المنجزة" :value="$number($report['totals']['pieces'])" />
        <x-report.kpi label="أيام العمل" :value="$report['totals']['active_days']" hint="أيام سُجّل فيها إنجاز" />
        <x-report.kpi label="متوسط القطع لليوم" :value="$number($report['totals']['per_day'])" />
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="daily-heading">
                <div class="card-body">
                    <h2 id="daily-heading" class="report-section-title"><i class="fas fa-chart-column" aria-hidden="true"></i> الإنجاز اليومي</h2>
                    <div class="worker-days" role="list" aria-label="القطع المنجزة لكل يوم">
                        @foreach ($report['daily'] as $date => $day)
                            <div class="worker-day {{ $day['pieces'] == 0 ? 'is-idle' : '' }}" role="listitem" title="{{ $date }}: {{ $number($day['pieces']) }} قطعة — {{ $number($day['points']) }} نقطة">
                                <span class="worker-day-bar" style="height: {{ max(4, round(max(0, $day['pieces']) / $maxDay * 100)) }}%"></span>
                                <span class="worker-day-label">{{ \Illuminate\Support\Carbon::parse($date)->day }}</span>
                                <span class="visually-hidden">{{ $date }}: {{ $number($day['pieces']) }} قطعة، {{ $number($day['points']) }} نقطة</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="categories-heading">
                <div class="card-body">
                    <h2 id="categories-heading" class="report-section-title"><i class="fas fa-medal" aria-hidden="true"></i> النقاط حسب تصنيف السرير</h2>
                    @if ($report['categories']->isEmpty())
                        <x-report.empty-state message="لا يوجد إنجاز في هذا الشهر." hint="" />
                    @else
                        <div class="report-bars">
                            @foreach ($report['categories'] as $category)
                                <div class="report-bar">
                                    <span class="report-bar-label">{{ $category['name'] }} @if ($category['rate'] !== null)<span class="text-muted small">(×{{ $number($category['rate']) }})</span>@endif</span>
                                    <span class="report-bar-track"><span @class(['report-bar-fill d-block', 'tone-muted' => $category['rate'] === null]) style="width: {{ round(max(0, $category['points']) / $maxCategory * 100) }}%"></span></span>
                                    <span class="report-bar-value"><bdi>{{ $number($category['points']) }}</bdi> <span class="small text-muted fw-normal">({{ $number($category['pieces']) }} قطعة)</span></span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="table-factory-wrapper" aria-labelledby="records-heading">
        <h2 id="records-heading" class="report-section-title p-3 mb-0"><i class="fas fa-list-check" aria-hidden="true"></i> سجل الإنجاز ({{ $report['records']->count() }})</h2>
        @if ($report['records']->isEmpty())
            <x-report.empty-state message="لم يُسجّل إنجاز لهذا الموظف في الشهر." class="m-3" />
        @else
            <div class="table-responsive">
                <table class="table table-factory align-middle mb-0">
                    <thead><tr><th scope="col">التاريخ</th><th scope="col">أمر الإنتاج</th><th scope="col">المرحلة</th><th scope="col" class="text-end">الكمية</th><th scope="col">التصنيف</th><th scope="col" class="text-end">النقاط</th></tr></thead>
                    <tbody>
                        @foreach ($report['records'] as $record)
                            @php $productionOrder = $record->operation?->productionOrder; @endphp
                            <tr>
                                <td class="text-nowrap"><bdi>{{ $record->occurred_at->format('Y-m-d H:i') }}</bdi></td>
                                <td>@if ($productionOrder)<a href="{{ route('production.orders.show', $productionOrder) }}" class="text-decoration-none fw-semibold"><bdi>{{ $productionOrder->production_order_number }}</bdi></a><div class="small text-muted">{{ $productionOrder->display_name }}</div>@else — @endif</td>
                                <td>{{ $record->operation?->operation_name_snapshot ?? '—' }}<div class="small text-muted">{{ $record->operation?->workCenter?->department?->name_ar }}</div></td>
                                <td class="text-end fw-semibold"><bdi>{{ $record->quantity }}</bdi>@if ($record->event_type === 'CORRECTION')<span class="d-block small text-warning-emphasis">تصحيح</span>@endif</td>
                                <td>{{ $record->pointCategory?->name_ar ?? ($record->operation?->workCenter?->department?->awards_points ? 'بلا تصنيف' : '—') }}</td>
                                <td class="text-end fw-bold"><bdi>{{ $record->points !== null ? $number($record->points) : '—' }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
