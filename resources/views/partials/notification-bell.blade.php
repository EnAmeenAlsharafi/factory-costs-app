{{--
    Notification bell: unread count in the header, the latest few in a dropdown.
    The count refreshes on a quiet poll — shared hosting carries no socket server.
--}}
@php
    $bellUser = auth()->user();
    $bellUnread = 0;
    $bellLatest = collect();

    if ($bellUser?->can('notifications.view')) {
        $bellLatest = \App\Models\NotificationRecipient::forUser($bellUser)
            ->with('notification.department')
            ->latest('id')->limit(8)->get();
        $bellUnread = \App\Models\NotificationRecipient::forUser($bellUser)->unread()->count();
    }
@endphp

@if ($bellUser?->can('notifications.view'))
    <div class="dropdown notification-bell"
         x-data="{
             unread: {{ $bellUnread }},
             async refresh() {
                 try {
                     const response = await fetch('{{ route('notifications.unread-count') }}', { headers: { 'Accept': 'application/json' } });
                     if (response.ok) { this.unread = (await response.json()).count; }
                 } catch (error) { /* offline: keep the last known count */ }
             },
         }"
         x-init="setInterval(() => refresh(), 60000)">

        <button type="button"
                class="btn btn-light border rounded-circle position-relative d-flex align-items-center justify-content-center p-0 shadow-sm"
                style="width: 38px; height: 38px;"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                :aria-label="unread > 0 ? `الإشعارات، ${unread} غير مقروء` : 'الإشعارات'">
            <i class="fas fa-bell text-secondary" aria-hidden="true"></i>
            <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger fs-8"
                  x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" x-cloak></span>
        </button>

        <div class="dropdown-menu dropdown-menu-start shadow rounded-3 mt-2 p-0 text-end notification-bell-menu">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="fw-bold text-dark fs-7">الإشعارات</span>
                @if ($bellUnread > 0)
                    <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none fs-8">تعليم الكل كمقروء</button>
                    </form>
                @endif
            </div>

            <div class="notification-bell-list">
                @forelse ($bellLatest as $item)
                    @php $bellNotification = $item->notification; @endphp
                    <form action="{{ route('notifications.read', $item) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="dropdown-item d-flex gap-2 align-items-start py-2 px-3 border-bottom text-wrap {{ $item->isRead() ? '' : 'bg-light-subtle fw-semibold' }}">
                            <i class="fas {{ $bellNotification->type->icon() }} text-{{ $bellNotification->severityTone() }} mt-1" aria-hidden="true"></i>
                            <span class="min-w-0">
                                <span class="d-block fs-8 text-dark">{{ $bellNotification->title_ar }}</span>
                                @if ($bellNotification->body_ar)
                                    <span class="d-block fs-8 text-muted">{{ \Illuminate\Support\Str::limit($bellNotification->body_ar, 90) }}</span>
                                @endif
                                <span class="d-block fs-8 text-secondary">{{ $bellNotification->created_at?->diffForHumans() }}</span>
                            </span>
                            @unless ($item->isRead())
                                <span class="badge bg-danger rounded-circle p-1 mt-1" aria-label="غير مقروء">&nbsp;</span>
                            @endunless
                        </button>
                    </form>
                @empty
                    <div class="px-3 py-4 text-center text-muted fs-8">
                        <i class="far fa-bell-slash d-block fs-4 mb-2 text-secondary" aria-hidden="true"></i>
                        لا توجد إشعارات بعد. ستصلك هنا تنبيهات مهامك وأقسامك.
                    </div>
                @endforelse
            </div>

            <a href="{{ route('notifications.index') }}" class="dropdown-item text-center py-2 fw-bold fs-8 text-primary">
                عرض كل الإشعارات
            </a>
        </div>
    </div>
@endif
