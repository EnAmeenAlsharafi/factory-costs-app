{{--
    App-style bottom tabs on phones for field staff: production workers (their department only)
    and delivery drivers. Office roles keep the side menu only.
--}}
@php
    $fieldUser = auth()->user();
    $isDriver = $fieldUser && $fieldUser->can('delivery.complete') && ! $fieldUser->can('delivery.create');
    $isWorker = $fieldUser && ! $isDriver && $fieldUser->can('production.update_progress')
        && $fieldUser->isDepartmentRestricted() && ! $fieldUser->can('production.assignment.manage');
    $fieldTabs = [];

    if ($isWorker) {
        $openTasks = \App\Models\ProductionOrderOperation::where('assigned_user_id', $fieldUser->id)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED'])->count();
        $fieldTabs = [
            ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'fa-house', 'label' => 'الرئيسية'],
            ['route' => 'production.my-tasks', 'active' => 'production.my-tasks', 'icon' => 'fa-list-check', 'label' => 'مهامي', 'badge' => $openTasks],
            ['route' => 'production.queue.index', 'active' => 'production.queue.*', 'icon' => 'fa-people-group', 'label' => 'أعمال قسمي'],
        ];
    } elseif ($isDriver) {
        $openDeliveries = \App\Models\DeliveryOrder::where('assigned_user_id', $fieldUser->id)
            ->whereIn('status', ['ASSIGNED', 'OUT_FOR_DELIVERY'])->count();
        $fieldTabs = [
            ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'fa-house', 'label' => 'الرئيسية'],
            ['route' => 'delivery.orders.my-tasks', 'params' => [], 'active' => 'delivery.orders.my-tasks', 'icon' => 'fa-truck-fast', 'label' => 'توصيلاتي', 'badge' => $openDeliveries, 'exactFilter' => null],
            ['route' => 'delivery.orders.my-tasks', 'params' => ['filter' => 'today'], 'active' => null, 'icon' => 'fa-calendar-day', 'label' => 'اليوم', 'exactFilter' => 'today'],
        ];
    }

    // Field staff live in this bar: their unread notifications belong here too.
    if ($fieldTabs !== [] && $fieldUser->can('notifications.view')) {
        $fieldTabs[] = [
            'route' => 'notifications.index',
            'active' => 'notifications.*',
            'icon' => 'fa-bell',
            'label' => 'الإشعارات',
            'badge' => \App\Models\NotificationRecipient::forUser($fieldUser)->unread()->count(),
        ];
    }
@endphp
@if ($fieldTabs !== [])
    <nav class="portal-tabbar field-tabbar" aria-label="التنقل السريع">
        @foreach ($fieldTabs as $tab)
            @php
                $isActive = array_key_exists('exactFilter', $tab)
                    ? request()->routeIs($tab['route']) && request('filter') === $tab['exactFilter'] || ($tab['exactFilter'] === null && request()->routeIs($tab['route']) && ! request()->filled('filter'))
                    : request()->routeIs($tab['active']);
            @endphp
            <a class="portal-tabbar-link {{ $isActive ? 'is-active' : '' }}" href="{{ route($tab['route'], $tab['params'] ?? []) }}" @if ($isActive) aria-current="page" @endif>
                <span class="field-tabbar-icon">
                    <i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i>
                    @if (($tab['badge'] ?? 0) > 0)
                        <span class="field-tabbar-badge" aria-label="{{ $tab['badge'] }} مفتوحة">{{ $tab['badge'] }}</span>
                    @endif
                </span>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
        <button type="button" class="portal-tabbar-link btn border-0" @click="mobileSidebarOpen = true" aria-label="فتح القائمة الكاملة">
            <span class="field-tabbar-icon"><i class="fas fa-bars" aria-hidden="true"></i></span>
            <span>المزيد</span>
        </button>
    </nav>
@endif
