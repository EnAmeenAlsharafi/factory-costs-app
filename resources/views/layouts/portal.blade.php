<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة العملاء') — مصنع مفروشات سدير</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="bg-white border-bottom">
        <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <a class="text-decoration-none fw-bold text-dark" href="{{ route('portal.home') }}">مصنع مفروشات سدير <span class="text-muted fw-normal">| بوابة العملاء</span></a>
            <div class="d-flex flex-wrap align-items-center gap-3"><span>{{ auth()->user()->customer?->name }}</span><form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="btn btn-outline-secondary" type="submit">تسجيل الخروج</button></form></div>
        </div>
        <nav class="container d-flex flex-wrap gap-2 pb-3" aria-label="بوابة العملاء">
            @foreach(['portal.home' => 'الرئيسية', 'portal.requests.index' => 'طلباتي وعروضي', 'portal.orders.index' => 'متابعة التنفيذ', 'portal.receipts' => 'سجل الاستلام', 'portal.account' => 'كشف الحساب', 'portal.profile' => 'بيانات المنشأة'] as $destination => $label)
                <a class="btn {{ request()->routeIs($destination) ? 'btn-primary' : 'btn-light' }}" href="{{ route($destination) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </header>
    <main class="container py-4" id="main-content">@include('partials.flash') @yield('content')</main>
    <footer class="container text-muted py-4 border-top">مصنع مفروشات سدير — طلباتك وحسابك في مكان واحد</footer>
</body>
</html>
