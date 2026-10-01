@props([
    'title',
    'icon' => 'fa-chart-column',
    'subtitle' => null,
    'period' => null,
    'basis' => null,
    'exportable' => false,
    'printable' => true,
    'filters' => [],
])

{{-- Report heading. The .print-only block is what a printed report shows instead of navigation and controls. --}}
<div class="page-header-card report-header">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div class="min-w-0">
            <nav aria-label="breadcrumb" class="report-no-print">
                <ol class="breadcrumb mb-1 fs-8">
                    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none">مركز التقارير</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas {{ $icon }} text-warning fs-5" aria-hidden="true"></i>
                <span>{{ $title }}</span>
            </h1>
            @if ($subtitle)
                <details class="report-description" open x-data="{ expanded: window.innerWidth >= 768 }" :open="expanded" @toggle="expanded = $el.open"><summary>عن التقرير</summary><p class="page-header-subtitle text-muted mb-0 fs-7">{{ $subtitle }}</p></details>
            @endif
            @if ($period || $basis)
                <p class="mb-0 mt-1 fs-7">
                    @if ($period)<span class="status-chip status-chip-secondary"><i class="far fa-calendar" aria-hidden="true"></i> <bdi>{{ $period->label() }}</bdi></span>@endif
                    @if ($basis)<span class="text-muted ms-1">الأساس الزمني: {{ $basis }}</span>@endif
                </p>
            @endif
        </div>
        <details class="report-actions report-no-print" open x-data="{ expanded: window.innerWidth >= 768 }" :open="expanded" @toggle="expanded = $el.open">
            <summary class="btn btn-outline-secondary">إجراءات التقرير <i class="fas fa-angle-down" aria-hidden="true"></i></summary>
            <div class="report-action-items d-flex flex-wrap gap-2">
            {{ $actions ?? '' }}
            @if ($exportable)
                @can('reports.export')
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="btn btn-outline-success">
                        <i class="fas fa-file-csv" aria-hidden="true"></i> تصدير CSV
                    </a>
                @endcan
            @endif
            @if ($printable)
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print" aria-hidden="true"></i> طباعة
                </button>
            @endif
            </div>
        </details>
    </div>
    <div class="print-only mt-2 fs-7">
        @foreach ($filters as $label => $value)
            @if ($value !== null && $value !== '')
                <div>{{ $label }}: {{ $value }}</div>
            @endif
        @endforeach
        <div>تاريخ التوليد: {{ now()->format('Y-m-d H:i') }} — {{ auth()->user()->name }}</div>
    </div>
</div>
