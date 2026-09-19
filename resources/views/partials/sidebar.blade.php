{{-- Arabic RTL Admin Sidebar Navigation with Collapsible Sections --}}
<aside id="app-sidebar"
       class="sidebar-wrapper"
       :class="{ 'collapsed': sidebarCollapsed, 'mobile-open': mobileSidebarOpen }"
       aria-label="القائمة الرئيسية">
    
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="sidebar-brand-link d-flex align-items-center gap-3 text-decoration-none text-white overflow-hidden" aria-label="لوحة تحكم مصنع مفروشات سدير">
            <div class="brand-icon-box bg-warning text-dark rounded-3 d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm" style="width: 38px; height: 38px; flex-shrink: 0;">
                <i class="fas fa-bed"></i>
            </div>
            <div class="sidebar-text" x-show="!sidebarCollapsed" x-transition>
                <h6 class="mb-0 fw-bold text-white fs-6" style="letter-spacing: -0.5px;">مصنع مفروشات سدير</h6>
                <small class="text-warning text-opacity-75 fs-8">نظام إدارة الإنتاج</small>
            </div>
        </a>

        <!-- Desktop Collapse Button -->
        <button type="button"
                @click="sidebarCollapsed = !sidebarCollapsed"
                class="btn btn-sm btn-outline-light border-0 d-none d-md-inline-flex text-white p-1"
                :aria-expanded="(!sidebarCollapsed).toString()"
                aria-controls="app-sidebar"
                aria-label="طي أو توسيع القائمة الرئيسية"
                title="طي أو توسيع القائمة">
            <i class="fas" :class="sidebarCollapsed ? 'fa-indent' : 'fa-outdent'"></i>
        </button>

        <!-- Mobile Close Drawer Button -->
        <button type="button" @click="closeMobileSidebar()" class="btn btn-sm btn-outline-light border-0 d-md-none text-white p-1" aria-label="إغلاق القائمة">
            <i class="fas fa-times fs-5"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu custom-scrollbar">
        <div class="d-flex flex-column gap-2 mb-0">

            <!-- Section 1: Main -->
            <div class="sidebar-section">
                <ul class="nav flex-column gap-1 mb-0 ps-0">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard') || request()->routeIs('home') ? 'active' : '' }}" title="لوحة التحكم">
                            <span class="sidebar-icon"><i class="fas fa-chart-line"></i></span>
                            <span class="sidebar-text">لوحة التحكم (Dashboard)</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Section 2: System Administration -->
            @php
                $isAdminActive = request()->routeIs('users.*') || request()->routeIs('roles.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('users.view') || auth()->user()->can('roles.view')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('admin', {{ $isAdminActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('admin', {{ $isAdminActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم إدارة النظام">
                        <span class="sidebar-text">إدارة النظام</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('admin', {{ $isAdminActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('admin', {{ $isAdminActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('users.view')
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="sidebar-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" title="إدارة المستخدمين">
                                    <span class="sidebar-icon"><i class="fas fa-users-cog"></i></span>
                                    <span class="sidebar-text">المستخدمون</span>
                                </a>
                            </li>
                        @endcan

                        @can('roles.view')
                            <li class="nav-item">
                                <a href="{{ route('roles.index') }}" class="sidebar-nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" title="الأدوار والصلاحيات">
                                    <span class="sidebar-icon"><i class="fas fa-shield-alt"></i></span>
                                    <span class="sidebar-text">الأدوار والصلاحيات</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 3: Master Data Foundation -->
            @php
                $isMasterActive = request()->routeIs('materials.*') || request()->routeIs('material-categories.*') || request()->routeIs('customers.*') || request()->routeIs('suppliers.*') || request()->routeIs('sales-channels.*') || request()->routeIs('customer-types.*') || request()->routeIs('units.*') || request()->routeIs('departments.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('materials.view') || auth()->user()->can('customers.view') || auth()->user()->can('suppliers.view') || auth()->user()->can('sales_channels.view') || auth()->user()->can('customer_types.view') || auth()->user()->can('units.view') || auth()->user()->can('departments.view')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('master', {{ $isMasterActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('master', {{ $isMasterActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم البيانات الأساسية">
                        <span class="sidebar-text">البيانات الأساسية</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('master', {{ $isMasterActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('master', {{ $isMasterActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('materials.view')
                            <li class="nav-item">
                                <a href="{{ route('materials.index') }}" class="sidebar-nav-link {{ request()->routeIs('materials.*') ? 'active' : '' }}" title="كتالوج المواد الخام">
                                    <span class="sidebar-icon"><i class="fas fa-layer-group"></i></span>
                                    <span class="sidebar-text">المواد والخامات</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('material-categories.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-categories.*') ? 'active' : '' }}" title="تصنيفات المواد">
                                    <span class="sidebar-icon"><i class="fas fa-boxes-stacked"></i></span>
                                    <span class="sidebar-text">تصنيفات المواد</span>
                                </a>
                            </li>
                        @endcan

                        @can('customers.view')
                            <li class="nav-item">
                                <a href="{{ route('customers.index') }}" class="sidebar-nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" title="سجل العملاء">
                                    <span class="sidebar-icon"><i class="fas fa-users"></i></span>
                                    <span class="sidebar-text">العملاء</span>
                                </a>
                            </li>
                        @endcan

                        @can('suppliers.view')
                            <li class="nav-item">
                                <a href="{{ route('suppliers.index') }}" class="sidebar-nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" title="سجل الموردين">
                                    <span class="sidebar-icon"><i class="fas fa-truck"></i></span>
                                    <span class="sidebar-text">الموردون</span>
                                </a>
                            </li>
                        @endcan

                        @can('sales_channels.view')
                            <li class="nav-item">
                                <a href="{{ route('sales-channels.index') }}" class="sidebar-nav-link {{ request()->routeIs('sales-channels.*') ? 'active' : '' }}" title="قنوات البيع">
                                    <span class="sidebar-icon"><i class="fas fa-store"></i></span>
                                    <span class="sidebar-text">قنوات البيع</span>
                                </a>
                            </li>
                        @endcan

                        @can('customer_types.view')
                            <li class="nav-item">
                                <a href="{{ route('customer-types.index') }}" class="sidebar-nav-link {{ request()->routeIs('customer-types.*') ? 'active' : '' }}" title="تصنيفات العملاء">
                                    <span class="sidebar-icon"><i class="fas fa-id-badge"></i></span>
                                    <span class="sidebar-text">أنواع العملاء</span>
                                </a>
                            </li>
                        @endcan

                        @can('units.view')
                            <li class="nav-item">
                                <a href="{{ route('units.index') }}" class="sidebar-nav-link {{ request()->routeIs('units.*') ? 'active' : '' }}" title="وحدات القياس">
                                    <span class="sidebar-icon"><i class="fas fa-ruler-combined"></i></span>
                                    <span class="sidebar-text">وحدات القياس</span>
                                </a>
                            </li>
                        @endcan

                        @can('departments.view')
                            <li class="nav-item">
                                <a href="{{ route('departments.index') }}" class="sidebar-nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}" title="أقسام المصنع">
                                    <span class="sidebar-icon"><i class="fas fa-building"></i></span>
                                    <span class="sidebar-text">أقسام المصنع</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 4: Product Models & Configurations -->
            @php
                $isProductsActive = request()->routeIs('products.models.*') || request()->routeIs('products.sizes.*');
            @endphp
            @if (auth()->user() && auth()->user()->can('products.view'))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('products', {{ $isProductsActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('products', {{ $isProductsActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم المنتجات والموديلات">
                        <span class="sidebar-text">المنتجات والموديلات</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('products', {{ $isProductsActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('products', {{ $isProductsActive ? 'true' : 'false' }})"
                        x-transition>
                        <li class="nav-item">
                            <a href="{{ route('products.models.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.models.*') ? 'active' : '' }}" title="موديلات المصنع">
                                <span class="sidebar-icon"><i class="fas fa-bed"></i></span>
                                <span class="sidebar-text">موديلات المصنع</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('products.sizes.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.sizes.*') ? 'active' : '' }}" title="المقاسات القياسية">
                                <span class="sidebar-icon"><i class="fas fa-ruler-combined"></i></span>
                                <span class="sidebar-text">المقاسات القياسية</span>
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

            <!-- Section 5: Manufacturing Execution -->
            @php
                $isProductionActive = request()->routeIs('production.orders.*') || request()->routeIs('production.material-requests.*') || request()->routeIs('production.queue.*') || request()->routeIs('production.quality-incidents.*') || request()->routeIs('production.rework.*') || request()->routeIs('production.waste.*') || request()->routeIs('production.routings.*') || request()->routeIs('production.board.*') || request()->routeIs('production.reports.*');
            @endphp
            @if (auth()->user() && auth()->user()->can('production.view'))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('production', {{ $isProductionActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('production', {{ $isProductionActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم إدارة وتنفيذ الإنتاج">
                        <span class="sidebar-text">إدارة وتنفيذ الإنتاج</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('production', {{ $isProductionActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('production', {{ $isProductionActive ? 'true' : 'false' }})"
                        x-transition>
                        <li class="nav-item">
                            <a href="{{ route('production.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.orders.*') ? 'active' : '' }}" title="أوامر الإنتاج">
                                <span class="sidebar-icon"><i class="fas fa-industry"></i></span>
                                <span class="sidebar-text">أوامر الإنتاج</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.material-requests.*') ? 'active' : '' }}" title="طلبات خامات الإنتاج">
                                <span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span>
                                <span class="sidebar-text">طلبات خامات الإنتاج</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.queue.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.queue.*') ? 'active' : '' }}" title="أعمال الأقسام">
                                <span class="sidebar-icon"><i class="fas fa-tasks"></i></span>
                                <span class="sidebar-text">أعمال الأقسام (WIP)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.quality-incidents.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.quality-incidents.*') ? 'active' : '' }}" title="حالات الجودة">
                                <span class="sidebar-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                <span class="sidebar-text">حالات الجودة والعيوب</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.rework.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.rework.*') ? 'active' : '' }}" title="إعادة التصنيع الإضافي">
                                <span class="sidebar-icon"><i class="fas fa-tools"></i></span>
                                <span class="sidebar-text">إعادة التصنيع والإصلاح</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.waste.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.waste.*') ? 'active' : '' }}" title="سجلات الهدر والتلف">
                                <span class="sidebar-icon"><i class="fas fa-dumpster"></i></span>
                                <span class="sidebar-text">الهدر والتلف التحليلي</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.routings.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.routings.*') ? 'active' : '' }}" title="مسارات التصنيع">
                                <span class="sidebar-icon"><i class="fas fa-route"></i></span>
                                <span class="sidebar-text">مسارات التصنيع</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.board.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.board.*') ? 'active' : '' }}" title="متابعة الإنتاج">
                                <span class="sidebar-icon"><i class="fas fa-chart-kanban"></i></span>
                                <span class="sidebar-text">متابعة الإنتاج</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('production.reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('production.reports.*') ? 'active' : '' }}" title="تقارير التكلفة والجودة">
                                <span class="sidebar-icon"><i class="fas fa-chart-pie"></i></span>
                                <span class="sidebar-text">تقارير التكلفة والجودة</span>
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

            <!-- Section 6: Manufacturing & Recipes -->
            @php
                $isRecipesActive = request()->routeIs('recipes.index') || request()->routeIs('recipes.create') || request()->routeIs('recipes.show') || request()->routeIs('recipes.versions.*') || request()->routeIs('recipes.templates.*') || request()->routeIs('recipes.components.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('recipes.view') || auth()->user()->can('manufacturing_templates.view') || auth()->user()->can('semi_finished_components.view')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('recipes', {{ $isRecipesActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('recipes', {{ $isRecipesActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم الوصفات والقوالب">
                        <span class="sidebar-text">الوصفات والقوالب</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('recipes', {{ $isRecipesActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('recipes', {{ $isRecipesActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('recipes.view')
                            <li class="nav-item">
                                <a href="{{ route('recipes.index') }}" class="sidebar-nav-link {{ request()->routeIs('recipes.index') || request()->routeIs('recipes.create') || request()->routeIs('recipes.show') || request()->routeIs('recipes.versions.*') ? 'active' : '' }}" title="وصفات التصنيع">
                                    <span class="sidebar-icon"><i class="fas fa-scroll"></i></span>
                                    <span class="sidebar-text">وصفات التصنيع (BOM)</span>
                                </a>
                            </li>
                        @endcan

                        @can('manufacturing_templates.view')
                            <li class="nav-item">
                                <a href="{{ route('recipes.templates.index') }}" class="sidebar-nav-link {{ request()->routeIs('recipes.templates.*') ? 'active' : '' }}" title="قوالب التصنيع">
                                    <span class="sidebar-icon"><i class="fas fa-layer-group"></i></span>
                                    <span class="sidebar-text">قوالب التصنيع</span>
                                </a>
                            </li>
                        @endcan

                        @can('semi_finished_components.view')
                            <li class="nav-item">
                                <a href="{{ route('recipes.components.index') }}" class="sidebar-nav-link {{ request()->routeIs('recipes.components.*') ? 'active' : '' }}" title="المكونات نصف المصنعة">
                                    <span class="sidebar-icon"><i class="fas fa-cubes"></i></span>
                                    <span class="sidebar-text">المكونات نصف المصنعة</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 7: Finished Goods & Delivery -->
            @php
                $isDeliveryActive = request()->routeIs('finished-goods.*') || request()->routeIs('delivery.orders.*') || request()->routeIs('customer-returns.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('finished_goods.view') || auth()->user()->can('delivery.view')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('delivery', {{ $isDeliveryActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('delivery', {{ $isDeliveryActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم التوصيل والمنتجات الجاهزة">
                        <span class="sidebar-text">التوصيل والمنتجات الجاهزة</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('delivery', {{ $isDeliveryActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('delivery', {{ $isDeliveryActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('finished_goods.view')
                            <li class="nav-item">
                                <a href="{{ route('finished-goods.index') }}" class="sidebar-nav-link {{ request()->routeIs('finished-goods.*') ? 'active' : '' }}" title="المنتجات الجاهزة">
                                    <span class="sidebar-icon"><i class="fas fa-boxes"></i></span>
                                    <span class="sidebar-text">المنتجات الجاهزة (Finished Goods)</span>
                                </a>
                            </li>
                        @endcan

                        @can('delivery.view')
                            <li class="nav-item">
                                <a href="{{ route('delivery.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('delivery.orders.index') || request()->routeIs('delivery.orders.show') || request()->routeIs('delivery.orders.create') ? 'active' : '' }}" title="أوامر التوصيل والتركيب">
                                    <span class="sidebar-icon"><i class="fas fa-truck-ramp-box"></i></span>
                                    <span class="sidebar-text">أوامر التوصيل والتركيب</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('delivery.orders.my-tasks') }}" class="sidebar-nav-link {{ request()->routeIs('delivery.orders.my-tasks') ? 'active' : '' }}" title="مهامي اليومية للتوصيل">
                                    <span class="sidebar-icon"><i class="fas fa-list-check"></i></span>
                                    <span class="sidebar-text">مهامي (فريق التوصيل)</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('customer-returns.index') }}" class="sidebar-nav-link {{ request()->routeIs('customer-returns.*') ? 'active' : '' }}" title="مرتجعات العملاء الميدانية">
                                    <span class="sidebar-icon"><i class="fas fa-arrow-rotate-left"></i></span>
                                    <span class="sidebar-text">مرتجعات العملاء الميدانية</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 8: Commercial & Sales -->
            @php
                $isSalesActive = request()->routeIs('sales.quotations.*') || request()->routeIs('sales.orders.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('quotations.view') || auth()->user()->can('orders.view')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('sales', {{ $isSalesActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('sales', {{ $isSalesActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم المبيعات والطلبات">
                        <span class="sidebar-text">المبيعات والطلبات</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('sales', {{ $isSalesActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('sales', {{ $isSalesActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('quotations.view')
                            <li class="nav-item">
                                <a href="{{ route('sales.quotations.index') }}" class="sidebar-nav-link {{ request()->routeIs('sales.quotations.*') ? 'active' : '' }}" title="عروض الأسعار">
                                    <span class="sidebar-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                                    <span class="sidebar-text">عروض الأسعار</span>
                                </a>
                            </li>
                        @endcan

                        @can('orders.view')
                            <li class="nav-item">
                                <a href="{{ route('sales.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('sales.orders.*') ? 'active' : '' }}" title="طلبات العملاء">
                                    <span class="sidebar-icon"><i class="fas fa-shopping-bag"></i></span>
                                    <span class="sidebar-text">طلبات العملاء</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 8.5: Receivables & Customer Payments -->
            @php
                $isReceivablesActive = request()->routeIs('receivables.*');
            @endphp
            @if (auth()->user() && auth()->user()->can('receivables.view'))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('receivables', {{ $isReceivablesActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('receivables', {{ $isReceivablesActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم الدفعات والتحصيل">
                        <span class="sidebar-text">الدفعات والتحصيل</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('receivables', {{ $isReceivablesActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('receivables', {{ $isReceivablesActive ? 'true' : 'false' }})"
                        x-transition>
                        <li class="nav-item">
                            <a href="{{ route('receivables.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('receivables.dashboard') ? 'active' : '' }}" title="لوحة التحصيل">
                                <span class="sidebar-icon"><i class="fas fa-chart-line"></i></span>
                                <span class="sidebar-text">لوحة التحصيل</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('receivables.payments.index') }}" class="sidebar-nav-link {{ request()->routeIs('receivables.payments.*') ? 'active' : '' }}" title="سجل الدفعات">
                                <span class="sidebar-icon"><i class="fas fa-money-bill-wave"></i></span>
                                <span class="sidebar-text">دفعات العملاء</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('receivables.customers.index') }}" class="sidebar-nav-link {{ request()->routeIs('receivables.customers.*') ? 'active' : '' }}" title="أرصدة العملاء">
                                <span class="sidebar-icon"><i class="fas fa-users-slash"></i></span>
                                <span class="sidebar-text">أرصدة العملاء</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('receivables.credit.index') }}" class="sidebar-nav-link {{ request()->routeIs('receivables.credit.*') ? 'active' : '' }}" title="إدارة الائتمان">
                                <span class="sidebar-icon"><i class="fas fa-credit-card"></i></span>
                                <span class="sidebar-text">إدارة الائتمان</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('receivables.reports.aging') }}" class="sidebar-nav-link {{ request()->routeIs('receivables.reports.*') ? 'active' : '' }}" title="أعمار الديون والمتأخرات">
                                <span class="sidebar-icon"><i class="fas fa-history"></i></span>
                                <span class="sidebar-text">أعمار الديون والمتأخرات</span>
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

            <!-- Section 9: Inventory Operations -->
            @php
                $isInventoryActive = request()->routeIs('inventory.balances.*') || request()->routeIs('inventory.lots.*') || request()->routeIs('inventory.receipts.*') || request()->routeIs('inventory.issues.*') || request()->routeIs('inventory.returns.*') || request()->routeIs('inventory.adjustments.*');
            @endphp
            @if (auth()->user() && (auth()->user()->can('inventory.view') || auth()->user()->can('inventory.receive') || auth()->user()->can('inventory.issue') || auth()->user()->can('inventory.return') || auth()->user()->can('inventory.adjust')))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('inventory', {{ $isInventoryActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('inventory', {{ $isInventoryActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم إدارة المخزون">
                        <span class="sidebar-text">إدارة المخزون</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('inventory', {{ $isInventoryActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('inventory', {{ $isInventoryActive ? 'true' : 'false' }})"
                        x-transition>
                        @can('inventory.view')
                            <li class="nav-item">
                                <a href="{{ route('inventory.balances.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.balances.*') || request()->routeIs('inventory.lots.*') ? 'active' : '' }}" title="أرصدة ودفعات المخزون">
                                    <span class="sidebar-icon"><i class="fas fa-boxes-stacked"></i></span>
                                    <span class="sidebar-text">أرصدة المخزون والدفعات</span>
                                </a>
                            </li>
                        @endcan

                        @can('inventory.receive')
                            <li class="nav-item">
                                <a href="{{ route('inventory.receipts.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.receipts.*') ? 'active' : '' }}" title="استلام المواد">
                                    <span class="sidebar-icon"><i class="fas fa-truck-loading"></i></span>
                                    <span class="sidebar-text">استلام المواد (Receipts)</span>
                                </a>
                            </li>
                        @endcan

                        @can('inventory.issue')
                            <li class="nav-item">
                                <a href="{{ route('inventory.issues.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.issues.*') ? 'active' : '' }}" title="صرف للإنتاج">
                                    <span class="sidebar-icon"><i class="fas fa-dolly"></i></span>
                                    <span class="sidebar-text">صرف للإنتاج (Issues)</span>
                                </a>
                            </li>
                        @endcan

                        @can('inventory.return')
                            <li class="nav-item">
                                <a href="{{ route('inventory.returns.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.returns.*') ? 'active' : '' }}" title="إرجاع المواد">
                                    <span class="sidebar-icon"><i class="fas fa-undo-alt"></i></span>
                                    <span class="sidebar-text">إرجاع للمخزن (Returns)</span>
                                </a>
                            </li>
                        @endcan

                        @can('inventory.adjust')
                            <li class="nav-item">
                                <a href="{{ route('inventory.adjustments.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}" title="تسويات المخزون">
                                    <span class="sidebar-icon"><i class="fas fa-sliders-h"></i></span>
                                    <span class="sidebar-text">تسويات المخزون</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            @endif

            <!-- Section 10: Purchasing & Procurement -->
            @php
                $isPurchasingActive = request()->routeIs('purchasing.*');
            @endphp
            @if (auth()->user() && auth()->user()->can('purchasing.view'))
                <div class="sidebar-section">
                    <button type="button"
                            @click="toggleSection('purchasing', {{ $isPurchasingActive ? 'true' : 'false' }})"
                            class="sidebar-section-header-btn"
                            :aria-expanded="isSectionOpen('purchasing', {{ $isPurchasingActive ? 'true' : 'false' }})"
                            title="طي أو توسيع قسم المشتريات">
                        <span class="sidebar-text">المشتريات والموردون</span>
                        <span class="sidebar-chevron-icon" x-show="!sidebarCollapsed">
                            <i class="fas fa-chevron-down" :class="{'rotate-180': !isSectionOpen('purchasing', {{ $isPurchasingActive ? 'true' : 'false' }})}"></i>
                        </span>
                    </button>
                    <ul class="nav flex-column gap-1 sidebar-sub-menu ps-0"
                        x-show="sidebarCollapsed || isSectionOpen('purchasing', {{ $isPurchasingActive ? 'true' : 'false' }})"
                        x-transition>
                        <li class="nav-item">
                            <a href="{{ route('purchasing.planning.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchasing.planning.*') ? 'active' : '' }}" title="تخطيط المشتريات والنواقص">
                                <span class="sidebar-icon"><i class="fas fa-chart-pie"></i></span>
                                <span class="sidebar-text">تخطيط المشتريات والنواقص</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('purchasing.requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchasing.requests.*') ? 'active' : '' }}" title="طلبات الشراء">
                                <span class="sidebar-icon"><i class="fas fa-file-signature"></i></span>
                                <span class="sidebar-text">طلبات الشراء (PR)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('purchasing.rfqs.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchasing.rfqs.*') ? 'active' : '' }}" title="طلبات عروض الأسعار">
                                <span class="sidebar-icon"><i class="fas fa-paper-plane"></i></span>
                                <span class="sidebar-text">طلبات عروض الأسعار (RFQ)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('purchasing.quotations.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchasing.quotations.*') ? 'active' : '' }}" title="عروض الأسعار ومقارنتها">
                                <span class="sidebar-icon"><i class="fas fa-scale-balanced"></i></span>
                                <span class="sidebar-text">عروض الموردين ومقارنتها</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('purchasing.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchasing.orders.*') ? 'active' : '' }}" title="أوامر الشراء">
                                <span class="sidebar-icon"><i class="fas fa-file-contract"></i></span>
                                <span class="sidebar-text">أوامر الشراء (PO)</span>
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

        </div>
    </div>

    <!-- Footer Profile Info & Logout -->
    @auth
        <div class="sidebar-footer sidebar-text" x-show="!sidebarCollapsed">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <div class="avatar-circle bg-warning text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.85rem;">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="user-info overflow-hidden">
                        <h6 class="mb-0 text-white fs-7 fw-semibold text-truncate">{{ auth()->user()->name }}</h6>
                        <small class="text-warning text-opacity-75 fs-8 d-block text-truncate">{{ auth()->user()->role?->display_name ?? 'مستخدم' }}</small>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light border-0 p-1.5 text-white" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>
    @endauth

</aside>
