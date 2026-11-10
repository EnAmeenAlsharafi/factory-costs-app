{{-- Installable app (PWA) tags. $webAppVariant: 'system' (staff, default) or 'portal' (customers). --}}
@php
    $webAppCompany = \App\Models\CompanySetting::current();
    $isPortalApp = ($webAppVariant ?? 'system') === 'portal';
    $webAppIconVersion = app(\App\Http\Controllers\WebAppController::class)->iconVersion();
    $webAppTitle = $isPortalApp ? 'بوابة '.\Illuminate\Support\Str::afterLast(trim($webAppCompany->name_ar), ' ') : $webAppCompany->name_ar;
@endphp
<link rel="manifest" href="{{ $isPortalApp ? route('webapp.portal-manifest') : route('webapp.manifest') }}">
<meta name="application-name" content="{{ $webAppTitle }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $webAppTitle }}">
<link rel="apple-touch-icon" href="{{ route('webapp.icon', ['size' => 192, 'v' => $webAppIconVersion] + ($isPortalApp ? ['app' => 'portal'] : [])) }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ route('webapp.icon', ['size' => 192, 'v' => $webAppIconVersion] + ($isPortalApp ? ['app' => 'portal'] : [])) }}">
