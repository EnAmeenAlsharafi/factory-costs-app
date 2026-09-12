<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - مصنع مفروشات سدير</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <main class="card-factory text-center p-4 p-md-5 w-100" style="max-width: 560px;" aria-labelledby="error-title">
        <div class="d-inline-grid place-items-center rounded-circle bg-warning bg-opacity-25 text-dark mb-3" style="width: 72px; height: 72px;">
            <i class="fas @yield('icon') fs-2" aria-hidden="true"></i>
        </div>
        <p class="code-badge mb-3">@yield('code')</p>
        <h1 id="error-title" class="h4 fw-bold mb-2">@yield('heading')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-factory-warning px-4">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
            {{ auth()->check() ? 'العودة إلى لوحة التحكم' : 'العودة إلى تسجيل الدخول' }}
        </a>
    </main>
</body>
</html>
