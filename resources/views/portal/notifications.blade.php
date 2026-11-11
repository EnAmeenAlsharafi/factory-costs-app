@extends('layouts.portal')

@section('title', 'التنبيهات')

@section('content')
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1">التنبيهات</h1>
            <p class="text-muted fs-7 mb-2">كل ما يخص عروضك وطلباتك ومواعيد تسليمها.</p>
            @include('partials.push-toggle')
        </div>

        @if ($unreadCount > 0)
            <form action="{{ route('portal.notifications.read-all') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-check-double ms-1" aria-hidden="true"></i> تعليم الكل كمقروء
                </button>
            </form>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $isRead = ($readState[$notification->id] ?? null) !== null;
                    $recipientId = $recipientIds[$notification->id] ?? null;
                @endphp
                <li class="list-group-item d-flex gap-3 align-items-start py-3 {{ $isRead ? '' : 'bg-light-subtle' }}">
                    <span class="rounded-circle bg-{{ $notification->severityTone() }}-subtle text-{{ $notification->severityTone() }} d-flex align-items-center justify-content-center flex-shrink-0"
                          style="width: 40px; height: 40px;" aria-hidden="true">
                        <i class="fas {{ $notification->type->icon() }}"></i>
                    </span>

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fw-bold text-dark">{{ $notification->title_ar }}</span>
                            @unless ($isRead)
                                <span class="badge bg-danger fs-8">جديد</span>
                            @endunless
                        </div>
                        @if ($notification->body_ar)
                            <p class="mb-1 text-secondary fs-7">{{ $notification->body_ar }}</p>
                        @endif
                        <span class="text-muted fs-8">
                            <i class="far fa-clock ms-1" aria-hidden="true"></i>{{ $notification->created_at?->format('Y-m-d H:i') }}
                        </span>
                    </div>

                    @if ($recipientId)
                        <form action="{{ route('portal.notifications.read', $recipientId) }}" method="POST" class="m-0 flex-shrink-0">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $isRead ? 'btn-outline-secondary' : 'btn-primary' }}">
                                {{ $notification->url ? 'فتح' : 'تم الاطلاع' }}
                            </button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-center py-5">
                    <i class="far fa-bell-slash fs-1 text-secondary d-block mb-3" aria-hidden="true"></i>
                    <p class="fw-bold text-dark mb-1">لا توجد تنبيهات بعد</p>
                    <p class="text-muted fs-7 mb-0">سنخبرك هنا عند وصول عرض سعر أو تقدّم طلبك أو جاهزيته للتوصيل.</p>
                </li>
            @endforelse
        </ul>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
