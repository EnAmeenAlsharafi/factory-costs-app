<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#142d43">
    @include('partials.webapp-head', ['webAppVariant' => 'portal'])
    <title>@yield('title', 'بوابة العملاء') — مصنع مفروشات سدير</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    /**
     * Portal destinations. `tab` marks the four kept in the phone tab bar; everything
     * else is reachable from the «المزيد» sheet, so the bar never scrolls.
     *
     * @var array<string, array{label: string, short: string, icon: string, tab: bool}>
     */
    $portalDestinations = [
        'portal.home' => ['label' => 'الرئيسية', 'short' => 'الرئيسية', 'icon' => 'fa-house', 'tab' => true],
        'portal.requests.index' => ['label' => 'طلباتي وعروضي', 'short' => 'طلباتي', 'icon' => 'fa-file-signature', 'tab' => true],
        'portal.orders.index' => ['label' => 'متابعة التنفيذ', 'short' => 'التنفيذ', 'icon' => 'fa-truck-fast', 'tab' => true],
        'portal.account' => ['label' => 'كشف الحساب', 'short' => 'حسابي', 'icon' => 'fa-file-invoice-dollar', 'tab' => true],
        'portal.receipts' => ['label' => 'سجل الاستلام', 'short' => 'الاستلام', 'icon' => 'fa-box-open', 'tab' => false],
        'portal.profile' => ['label' => 'بيانات المنشأة', 'short' => 'منشأتي', 'icon' => 'fa-building', 'tab' => false],
    ];
    $moreDestinations = array_filter($portalDestinations, fn (array $destination): bool => ! $destination['tab']);
    $onMoreDestination = collect(array_keys($moreDestinations))->contains(fn (string $route): bool => request()->routeIs($route));
    $portalCustomerName = auth()->user()->customer?->commercial_name ?: auth()->user()->customer?->name;
@endphp

<div class="portal-shell">
    <a href="#main-content" class="visually-hidden-focusable p-2 d-inline-block">تجاوز إلى المحتوى</a>

    <header class="portal-topbar">
        <div class="container py-2 d-flex align-items-center justify-content-between gap-2">
            <a class="portal-brand" href="{{ route('portal.home') }}">
                <span class="portal-brand-mark"><i class="fas fa-store" aria-hidden="true"></i></span>
                <span class="min-w-0">
                    <span class="portal-brand-name">مصنع مفروشات سدير</span>
                    <span class="portal-brand-sub">{{ $portalCustomerName ?: 'بوابة العملاء' }}</span>
                </span>
            </a>

            {{-- Account menu: keeps «تسجيل الخروج» out of the primary navigation. --}}
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-light rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" aria-label="حسابي والخروج" style="width:44px;height:44px;">
                    <i class="fas fa-user" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li class="dropdown-header text-truncate">{{ $portalCustomerName }}</li>
                    <li><a class="dropdown-item" href="{{ route('portal.profile') }}"><i class="fas fa-building ms-1 text-muted" aria-hidden="true"></i> بيانات المنشأة</a></li>
                    <li><a class="dropdown-item" href="{{ route('portal.receipts') }}"><i class="fas fa-box-open ms-1 text-muted" aria-hidden="true"></i> سجل الاستلام</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('portal.logout') }}" class="px-2">
                            @csrf
                            <button class="btn btn-outline-secondary w-100" type="submit"><i class="fas fa-arrow-right-from-bracket ms-1" aria-hidden="true"></i> تسجيل الخروج</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Tablet/desktop navigation. On phones the bottom tab bar takes over. --}}
        <nav class="container pb-2 d-none d-md-block" aria-label="أقسام بوابة العملاء">
            <div class="portal-nav">
                @foreach($portalDestinations as $destination => $meta)
                    <a class="portal-nav-link {{ request()->routeIs($destination) ? 'is-active' : '' }}" href="{{ route($destination) }}" @if(request()->routeIs($destination)) aria-current="page" @endif>
                        <i class="fas {{ $meta['icon'] }}" aria-hidden="true"></i> {{ $meta['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    </header>

    <main class="portal-main container" id="main-content">
        @include('partials.flash')
        @include('partials.install-app', ['webAppVariant' => 'portal'])
        @yield('content')
    </main>

    <footer class="portal-footer container pb-3 pt-2 d-none d-md-block border-top">مصنع مفروشات سدير — طلباتك وحسابك في مكان واحد</footer>

    {{-- Phone navigation: thumb-reachable, text + icon, current page marked. --}}
    <nav class="portal-tabbar" aria-label="أقسام بوابة العملاء">
        @foreach($portalDestinations as $destination => $meta)
            @continue(! $meta['tab'])
            <a class="portal-tabbar-link {{ request()->routeIs($destination) ? 'is-active' : '' }}" href="{{ route($destination) }}" @if(request()->routeIs($destination)) aria-current="page" @endif>
                <i class="fas {{ $meta['icon'] }}" aria-hidden="true"></i>
                <span>{{ $meta['short'] }}</span>
            </a>
        @endforeach
        <button class="portal-tabbar-link {{ $onMoreDestination ? 'is-active' : '' }} btn border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#portal-more" aria-controls="portal-more">
            <i class="fas fa-ellipsis" aria-hidden="true"></i>
            <span>المزيد</span>
        </button>
    </nav>

    <div class="offcanvas offcanvas-bottom filter-sheet" tabindex="-1" id="portal-more" aria-labelledby="portal-more-label">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h6 fw-bold mb-0" id="portal-more-label">المزيد</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="إغلاق القائمة"></button>
        </div>
        <div class="offcanvas-body">
            <div class="list-group list-group-flush">
                @foreach($moreDestinations as $destination => $meta)
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3 {{ request()->routeIs($destination) ? 'active' : '' }}" href="{{ route($destination) }}">
                        <i class="fas {{ $meta['icon'] }} fa-fw" aria-hidden="true"></i> {{ $meta['label'] }}
                    </a>
                @endforeach
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3" href="{{ route('portal.requests.create') }}">
                    <i class="fas fa-plus fa-fw" aria-hidden="true"></i> إنشاء طلب جديد
                </a>
            </div>
            <form method="POST" action="{{ route('portal.logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-outline-secondary w-100" type="submit"><i class="fas fa-arrow-right-from-bracket ms-1" aria-hidden="true"></i> تسجيل الخروج</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
