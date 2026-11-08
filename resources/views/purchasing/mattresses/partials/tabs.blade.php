@php
    $lowCount = app(\App\Services\ReadyProductSupplyService::class)->lowStockCount();
    $tabs = [
        ['route' => 'purchasing.mattresses.stock', 'icon' => 'fa-warehouse', 'label' => 'المخزون والتنبيهات', 'badge' => $lowCount, 'can' => 'purchasing.view'],
        ['route' => 'purchasing.mattresses.orders', 'icon' => 'fa-truck-ramp-box', 'label' => 'طلب توريد للطلبات', 'badge' => 0, 'can' => 'purchasing.create_po'],
        ['route' => 'purchasing.mattresses.production', 'icon' => 'fa-industry', 'label' => 'طلبات الإنتاج', 'badge' => 0, 'can' => 'purchasing.create_po'],
    ];
@endphp
<div class="page-header-card">
    <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
        <i class="fas fa-bed text-primary fs-5" aria-hidden="true"></i> المراتب من الموردين
    </h1>
    <p class="page-header-subtitle text-muted mb-0 fs-7">{{ $subtitle }}</p>
</div>
<nav aria-label="أقسام المراتب">
    <div class="pipeline-filter-chips">
        @foreach ($tabs as $tab)
            @can($tab['can'])
                <a href="{{ route($tab['route']) }}" class="pipeline-chip {{ request()->routeIs($tab['route']) ? 'is-active' : '' }}" @if (request()->routeIs($tab['route'])) aria-current="page" @endif>
                    <i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i> {{ $tab['label'] }}
                    @if ($tab['badge'] > 0)
                        <span class="bg-danger text-white" aria-label="{{ $tab['badge'] }} مقاس منخفض">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            @endcan
        @endforeach
    </div>
</nav>
@if ($errors->any())
    <div class="alert alert-danger mb-0" role="alert">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
