@props([
    'label',
    'value' => null,
    'icon' => 'fa-chart-simple',
    'tone' => 'secondary',
    'hint' => null,
    'href' => null,
    'alert' => false,
])

@php $tag = $href ? 'a' : 'div'; @endphp
{{-- Null value = not available / not calculated: shown as an em dash, never as a misleading zero. --}}
<{{ $tag }} {{ $attributes->class(['metric-tile report-kpi', 'is-attention' => $alert]) }} @if ($href) href="{{ $href }}" @endif>
    <span class="min-w-0 w-100">
        <span class="metric-tile-label d-block">{{ $label }}</span>
        <span @class(['metric-tile-value d-block', 'text-muted' => $value === null, 'text-danger' => $alert && $value !== null])><bdi>{{ $value ?? '—' }}</bdi></span>
        @if ($hint)
            <span class="d-block fs-8 text-muted">{{ $hint }}</span>
        @endif
        @if ($alert)<span class="report-kpi-attention"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> يحتاج مراجعة</span>@endif
        @if ($href)<span class="report-kpi-link">عرض التفاصيل <i class="fas fa-angle-left" aria-hidden="true"></i></span>@endif
    </span>
</{{ $tag }}>
