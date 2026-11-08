@php $company = \App\Models\CompanySetting::current(); @endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $company->primary_color }}">
    <title>لا يوجد اتصال - {{ $company->name_ar }}</title>
    {{-- Self-contained so it renders from the cache with no network. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; color: #0f172a; font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; padding: 24px; box-sizing: border-box; }
        main { max-width: 360px; text-align: center; background: #fff; border-radius: 18px; padding: 32px 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .mark { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 16px; display: grid; place-items: center; background: {{ $company->primary_color }}; color: #fff; font-size: 30px; font-weight: 700; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { margin: 0 0 20px; color: #475569; line-height: 1.7; }
        button { min-height: 48px; width: 100%; border: 0; border-radius: 12px; background: {{ $company->primary_color }}; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:focus-visible { outline: 3px solid #0f172a; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">!</div>
        <h1>لا يوجد اتصال بالإنترنت</h1>
        <p>تعذّر فتح الصفحة. تأكد من اتصال الجوال بالإنترنت ثم أعد المحاولة. بياناتك محفوظة في النظام.</p>
        <button type="button" onclick="location.reload()">إعادة المحاولة</button>
    </main>
</body>
</html>
