{{-- Installable app (PWA) tags, shared by the app layout and the login page. --}}
@php $webAppCompany = \App\Models\CompanySetting::current(); @endphp
<link rel="manifest" href="{{ route('webapp.manifest') }}">
<meta name="application-name" content="{{ $webAppCompany->name_ar }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $webAppCompany->name_ar }}">
<link rel="apple-touch-icon" href="{{ route('webapp.icon', ['size' => 192, 'v' => $webAppCompany->updated_at?->timestamp]) }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ route('webapp.icon', ['size' => 192, 'v' => $webAppCompany->updated_at?->timestamp]) }}">
