{{-- Arabic RTL Top Header --}}
<header class="top-header">
    
    <!-- Right Area: Mobile Toggle & Page Title/Breadcrumbs -->
    <div class="d-flex align-items-center gap-3">
        <button id="mobile-menu-toggle"
                type="button"
                @click="openMobileSidebar()"
                class="btn btn-light d-md-none border rounded-3 p-2 text-secondary shadow-sm"
                :aria-expanded="mobileSidebarOpen.toString()"
                aria-controls="app-sidebar"
                aria-label="فتح القائمة الرئيسية">
            <i class="fas fa-bars fs-5"></i>
        </button>

        <div class="d-flex align-items-center gap-2">
            <span class="header-page-title fw-bold text-dark fs-6">@yield('page-title', 'نظام إدارة إنتاج المصنع')</span>
        </div>
    </div>

    <!-- Left Area: Date Badge & User Profile Dropdown -->
    <div class="d-flex align-items-center gap-3">
        
        <!-- Date Badge -->
        <div class="header-date d-none d-lg-flex align-items-center gap-2 bg-light px-3 py-1.5 rounded-pill border text-secondary fs-8">
            <i class="far fa-calendar-alt text-warning"></i>
            <span>{{ now()->locale('ar')->translatedFormat('l، d F Y') }}</span>
        </div>

        <!-- User Profile Dropdown -->
        @auth
            <div class="dropdown">
                <button type="button" class="btn btn-light border rounded-pill d-flex align-items-center gap-2 py-1 px-2.5 shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="فتح قائمة حساب المستخدم">
                    <div class="avatar-circle bg-warning text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="header-user-meta text-start d-none d-md-block">
                        <div class="fw-bold text-dark fs-8 lh-1">{{ auth()->user()->name }}</div>
                        <small class="text-muted fs-8">{{ auth()->user()->role?->display_name ?? 'مستخدم' }}</small>
                    </div>
                    <i class="fas fa-chevron-down text-muted fs-8 ms-1"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-start shadow-sm rounded-3 mt-2 fs-7 text-end">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold text-dark">{{ auth()->user()->name }}</div>
                        <small class="text-muted">{{ auth()->user()->username }} | {{ auth()->user()->role?->display_name }}</small>
                    </li>
                    @can('users.view')
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="{{ route('users.index') }}">
                                <i class="fas fa-users-cog text-secondary" style="width: 18px;"></i> إدارة المستخدمين
                            </a>
                        </li>
                    @endcan
                    @can('roles.view')
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="{{ route('roles.index') }}">
                                <i class="fas fa-shield-alt text-secondary" style="width: 18px;"></i> دليل الأدوار والصلاحيات
                            </a>
                        </li>
                    @endcan
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger d-flex align-items-center gap-2">
                                <i class="fas fa-sign-out-alt" style="width: 18px;"></i> تسجيل الخروج
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        @endauth

    </div>
</header>
