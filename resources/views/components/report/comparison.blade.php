@props(['trend'])
@php
    $format = fn ($value) => $value === null ? '—' : ($trend['type'] === 'money' ? \App\Domain\Reports\ReportFormat::money($value) : number_format((float) $value));
    $scale = max(0.01, abs((float) ($trend['current'] ?? 0)), abs((float) ($trend['previous'] ?? 0)));
@endphp
<article class="report-comparison">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><h3 class="fs-7 fw-bold mb-0">{{ $trend['label'] }}</h3>
        <bdi class="report-note">{{ $trend['change_pct'] === null ? 'غير قابل للمقارنة' : ($trend['change_pct'] > 0 ? '+' : '').\App\Domain\Reports\ReportFormat::percent($trend['change_pct']) }}</bdi>
    </div>
    @foreach (['current' => 'الحالية', 'previous' => 'السابقة'] as $key => $label)
        <div class="report-bar mb-2"><span class="report-bar-label">{{ $label }}</span><span class="report-bar-track" aria-hidden="true"><span class="report-bar-fill {{ $key === 'previous' ? 'tone-muted' : '' }} {{ ($trend[$key] ?? 0) < 0 ? 'is-negative' : '' }}" style="width: {{ round(abs((float) ($trend[$key] ?? 0)) / $scale * 100, 1) }}%"></span></span><bdi class="report-bar-value">{{ $format($trend[$key]) }}</bdi></div>
    @endforeach
</article>
