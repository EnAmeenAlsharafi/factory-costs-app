{{-- Reusable Page Header Blade Component --}}
<div class="page-header-card" aria-labelledby="page-heading">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            @if (isset($breadcrumbs) && is_array($breadcrumbs))
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0 fs-8 text-muted">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">الرئيسية</a></li>
                        @foreach ($breadcrumbs as $crumb)
                            @if (isset($crumb['url']) && !$loop->last)
                                <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}" class="text-decoration-none text-secondary">{{ $crumb['title'] }}</a></li>
                            @else
                                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $crumb['title'] }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                @if (isset($icon))
                    <i class="{{ $icon }} text-warning fs-5"></i>
                @endif
                <span>{{ $title }}</span>
            </h1>

            @if (isset($subtitle))
                <p class="page-header-subtitle text-muted mb-0 fs-7">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        @if (isset($actionUrl) && isset($actionText))
            @if (!isset($actionPermission) || auth()->user()?->can($actionPermission))
                <div class="page-header-actions flex-shrink-0">
                    <a href="{{ $actionUrl }}" class="btn btn-factory-warning shadow-sm fs-7">
                        @if (isset($actionIcon))
                            <i class="{{ $actionIcon }} me-1"></i>
                        @else
                            <i class="fas fa-plus me-1"></i>
                        @endif
                        <span>{{ $actionText }}</span>
                    </a>
                </div>
            @endif
        @endif
    </div>
</div>
