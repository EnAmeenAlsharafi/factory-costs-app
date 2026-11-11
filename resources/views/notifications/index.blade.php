@extends('layouts.app')

@section('title', 'الإشعارات - مصنع مفروشات سدير')
@section('page-title', 'الإشعارات')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="page-header-card d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas fa-bell text-warning" aria-hidden="true"></i>
                {{ $showAll ? 'كل إشعارات النظام' : 'إشعاراتي' }}
            </h1>
            <p class="page-header-subtitle text-muted mb-2 fs-7">
                {{ $showAll
                    ? 'كل ما أطلقه النظام، لمراجعة التغطية والضبط.'
                    : 'ما يخص مهامك وقسمك وصلاحياتك. الضغط على الإشعار يفتح الشاشة المعنية.' }}
            </p>
            @include('partials.push-toggle')
        </div>

        <div class="d-flex flex-wrap gap-2">
            @can('notifications.send')
                <a href="{{ route('notifications.announce.create') }}" class="btn btn-warning fw-bold">
                    <i class="fas fa-bullhorn ms-1" aria-hidden="true"></i> إعلان جديد
                </a>
            @endcan
            @can('notifications.manage')
                <a href="{{ route('settings.notifications.edit') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-sliders ms-1" aria-hidden="true"></i> إعدادات الإشعارات
                </a>
                <a href="{{ route('notifications.index', array_merge(request()->except(['scope', 'page']), $showAll ? [] : ['scope' => 'all'])) }}"
                   class="btn btn-outline-secondary">
                    {{ $showAll ? 'عرض إشعاراتي فقط' : 'عرض إشعارات النظام كاملة' }}
                </a>
            @endcan
            @unless ($showAll)
                <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary" @disabled($unreadCount === 0)>
                        <i class="fas fa-check-double ms-1" aria-hidden="true"></i>
                        تعليم الكل كمقروء @if ($unreadCount > 0)<span class="badge bg-primary">{{ $unreadCount }}</span>@endif
                    </button>
                </form>
            @endunless
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('notifications.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            @if ($showAll)<input type="hidden" name="scope" value="all">@endif

            @unless ($showAll)
                <div>
                    <label for="filter" class="form-label fs-8 text-muted mb-1">الحالة</label>
                    <select id="filter" name="filter" class="form-select form-select-sm">
                        <option value="all" @selected($filter === 'all')>الكل</option>
                        <option value="unread" @selected($filter === 'unread')>غير المقروء فقط</option>
                    </select>
                </div>
            @endunless

            <div>
                <label for="group" class="form-label fs-8 text-muted mb-1">المجال</label>
                <select id="group" name="group" class="form-select form-select-sm">
                    <option value="">كل المجالات</option>
                    @foreach ($groups as $groupName)
                        <option value="{{ $groupName }}" @selected($group === $groupName)>{{ $groupName }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="department_id" class="form-label fs-8 text-muted mb-1">القسم</label>
                <select id="department_id" name="department_id" class="form-select form-select-sm">
                    <option value="">كل الأقسام</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name_ar }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-sm btn-primary">تصفية</button>
            @if (request()->hasAny(['filter', 'group', 'department_id']))
                <a href="{{ route('notifications.index', $showAll ? ['scope' => 'all'] : []) }}" class="btn btn-sm btn-link text-decoration-none">إلغاء التصفية</a>
            @endif
        </div>
    </form>

    {{-- List --}}
    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $isRead = $showAll || ($readState[$notification->id] ?? null) !== null;
                    $recipientId = $recipientIds[$notification->id] ?? null;
                @endphp
                <div class="list-group-item d-flex gap-3 align-items-start py-3 {{ $isRead ? '' : 'bg-light-subtle' }}">
                    <span class="notification-icon rounded-circle bg-{{ $notification->severityTone() }}-subtle text-{{ $notification->severityTone() }} d-flex align-items-center justify-content-center flex-shrink-0"
                          style="width: 40px; height: 40px;" aria-hidden="true">
                        <i class="fas {{ $notification->type->icon() }}"></i>
                    </span>

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fw-bold text-dark {{ $isRead ? '' : 'fw-bolder' }}">{{ $notification->title_ar }}</span>
                            @unless ($isRead)
                                <span class="badge bg-danger fs-8">جديد</span>
                            @endunless
                            <span class="badge bg-light text-secondary border fs-8">{{ $notification->type->label() }}</span>
                            @if ($notification->department)
                                <span class="badge bg-light text-dark border fs-8">{{ $notification->department->name_ar }}</span>
                            @endif
                            @if ($notification->isAutomatic())
                                <span class="badge bg-info-subtle text-info-emphasis border fs-8" title="أطلقه النظام تلقائياً">آلي</span>
                            @endif
                        </div>

                        @if ($notification->body_ar)
                            <p class="mb-1 text-secondary fs-7">{{ $notification->body_ar }}</p>
                        @endif

                        <div class="d-flex flex-wrap gap-3 text-muted fs-8">
                            <span><i class="far fa-clock ms-1" aria-hidden="true"></i>{{ $notification->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($notification->triggeredBy)
                                <span><i class="far fa-user ms-1" aria-hidden="true"></i>{{ $notification->triggeredBy->name }}</span>
                            @endif
                            @if ($showAll)
                                <span><i class="fas fa-users ms-1" aria-hidden="true"></i>{{ $notification->recipients_count }} مستلم</span>
                            @endif
                        </div>
                    </div>

                    @if ($recipientId)
                        <form action="{{ route('notifications.read', $recipientId) }}" method="POST" class="m-0 flex-shrink-0">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $isRead ? 'btn-outline-secondary' : 'btn-primary' }}">
                                {{ $notification->url ? 'فتح' : 'تم الاطلاع' }}
                            </button>
                        </form>
                    @elseif ($showAll && $notification->url)
                        <a href="{{ $notification->url }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">فتح</a>
                    @endif
                </div>
            @empty
                <div class="text-center py-5 px-3">
                    <i class="far fa-bell-slash fs-1 text-secondary d-block mb-3" aria-hidden="true"></i>
                    <p class="fw-bold text-dark mb-1">لا توجد إشعارات مطابقة</p>
                    <p class="text-muted fs-7 mb-0">
                        {{ request()->hasAny(['filter', 'group', 'department_id'])
                            ? 'جرّب إلغاء التصفية لعرض كل الإشعارات.'
                            : 'ستصلك هنا تنبيهات المهام والمواعيد والنواقص الخاصة بك وبقسمك.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
