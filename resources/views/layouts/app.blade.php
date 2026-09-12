<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'مصنع مفروشات سدير - نظام إدارة الإنتاج')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body x-data="appShell" x-on:keydown.escape.window="mobileSidebarOpen && closeMobileSidebar()">

    <!-- Mobile Overlay Backdrop -->
    <button type="button" class="mobile-backdrop d-md-none"
         x-show="mobileSidebarOpen" 
         x-on:click="closeMobileSidebar()"
         x-transition.opacity 
         x-cloak
         aria-label="إغلاق قائمة التنقل"></button>

    <div class="app-layout-wrapper">
        
        <!-- Sidebar Navigation Column -->
        <div class="app-sidebar-column" aria-label="التنقل الرئيسي">
            @include('partials.sidebar')
        </div>

        <!-- Main Workspace Column -->
        <div class="app-main-column">
            
            <!-- Top Header Navbar -->
            @include('partials.header')

            <!-- Main Content Area -->
            <main id="main-content" class="app-content-container" tabindex="-1">
                <!-- Flash Messages & Validation Display -->
                @include('partials.flash')

                <!-- Page Specific Content -->
                @yield('content')
            </main>

            <!-- Application Footer -->
            <footer class="app-footer bg-white border-top text-center text-muted fs-8">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                    <div>
                        &copy; {{ date('Y') }} <strong>مصنع مفروشات سدير</strong> - نظام إدارة الإنتاج الداخلي
                    </div>
                    <div class="text-secondary">
                        المرحلة 3.5 &bull; واجهة تشغيل داخلية
                    </div>
                </div>
            </footer>

        </div>
    </div>

    <!-- Confirmation Modal Component -->
    @include('partials.modal')

    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
