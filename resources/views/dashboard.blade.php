@extends('layouts.app')

@section('title', 'لوحة التحكم - مصنع مفروشات سدير')
@section('page-title', 'لوحة التحكم')

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-gauge-high text-warning fs-5" aria-hidden="true"></i>
            <span>مرحباً {{ auth()->user()->name }}</span>
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">ما الذي يحتاج إلى إجراء منك الآن؟ اضغط على أي بطاقة للانتقال مباشرة إلى العمل.</p>
    </div>

    @forelse ($sections as $section)
        <section aria-labelledby="dash-{{ $section['key'] }}">
            <h2 id="dash-{{ $section['key'] }}" class="h6 fw-bold mb-2 d-flex align-items-center gap-2 text-dark">
                <i class="fas {{ $section['icon'] }} text-warning" aria-hidden="true"></i>
                {{ $section['title'] }}
            </h2>
            <div class="row g-2 g-md-3">
                @foreach ($section['tiles'] as $tile)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ $tile['url'] }}"
                           class="metric-tile {{ $tile['attention'] ? 'is-attention' : '' }} {{ (float) $tile['value'] === 0.0 ? 'is-zero' : '' }}"
                           data-dashboard-tile="{{ $section['key'] }}">
                            <span class="metric-tile-icon tone-bg-{{ $tile['tone'] }}" aria-hidden="true"><i class="fas {{ $tile['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <span class="metric-tile-value d-block">{{ $tile['display'] }}</span>
                                <span class="metric-tile-label">{{ $tile['label'] }}</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-circle-info fs-2 mb-3 text-secondary" aria-hidden="true"></i>
            <h2 class="h5 fw-bold">لا توجد مهام تشغيلية مرتبطة بدورك</h2>
            <p class="mb-0">لم تُمنح صلاحيات على أعمال يومية بعد. استخدم القائمة للوصول إلى السجلات المتاحة لك، أو تواصل مع مدير النظام.</p>
        </div>
    @endforelse

    @if (count($masterDataStats) > 0)
        <details class="card-factory p-3" @if (count($sections) === 0) open @endif>
            <summary class="fw-bold text-dark d-flex align-items-center gap-2" style="min-height: 44px; cursor: pointer;">
                <i class="fas fa-database text-secondary" aria-hidden="true"></i>
                السجلات الأساسية
            </summary>
            <div class="row g-2 g-md-3 mt-1">
                @foreach ($masterDataStats as $stat)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ route($stat['route']) }}" class="metric-tile">
                            <span class="metric-tile-icon tone-bg-secondary" aria-hidden="true"><i class="fas {{ $stat['icon'] }}"></i></span>
                            <span class="min-w-0">
                                @if ($stat['value'] !== null)
                                    <span class="metric-tile-value d-block">{{ number_format($stat['value']) }}</span>
                                @endif
                                <span class="metric-tile-label">{{ $stat['label'] }}</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
@endsection
