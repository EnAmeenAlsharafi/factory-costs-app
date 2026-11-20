{{-- One task in the employee's lists. $item is their TaskAssignee row. --}}
@php
    $task = $item->task;
    $fromSomeoneElse = $task->is_assigned && $task->created_by_user_id !== auth()->id();
@endphp
<li class="tasks-item {{ $item->isDone() ? 'is-done' : '' }}">
    <form action="{{ route('tasks.assignments.status', $item) }}" method="POST" class="m-0">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ $item->isDone() ? 'OPEN' : 'DONE' }}">
        <button type="submit" class="tasks-check" aria-label="{{ $item->isDone() ? 'إعادة فتح' : 'تعليم كمنجزة' }}: {{ $task->title }}"
                title="{{ $item->isDone() ? 'إعادة فتح' : 'تعليم كمنجزة' }}">
            <i class="{{ $item->isDone() ? 'fas fa-circle-check' : 'far fa-circle' }}" aria-hidden="true"></i>
        </button>
    </form>

    <a href="{{ route('tasks.show', $task) }}" class="tasks-item-body text-reset text-decoration-none min-w-0">
        <span class="tasks-item-title">{{ $task->title }}</span>
        <span class="tasks-item-meta">
            @if ($fromSomeoneElse)
                <span><i class="fas fa-user-tie" aria-hidden="true"></i> من {{ $task->creator?->name }}</span>
            @endif
            @if ($item->status === 'IN_PROGRESS')
                <span class="text-primary"><i class="fas fa-spinner" aria-hidden="true"></i> قيد التنفيذ</span>
            @endif
            @if ($item->due_date)
                <span class="{{ $item->isOverdue() ? 'text-danger fw-bold' : ($item->due_date->isToday() && ! $item->isDone() ? 'text-warning-emphasis fw-bold' : '') }}">
                    <i class="fas fa-calendar-day" aria-hidden="true"></i>
                    {{ $item->due_date->isToday() ? 'اليوم' : ($item->due_date->isTomorrow() ? 'غداً' : $item->due_date->format('Y-m-d')) }}
                    @if ($item->isOverdue()) (متأخرة) @endif
                </span>
            @endif
            @if ($item->isInMyDay() && ($view ?? '') !== 'my-day')
                <span><i class="fas fa-sun" aria-hidden="true"></i> يومي</span>
            @endif
            @if ($item->taskList && ! ($currentList ?? null))
                <span><i class="fas fa-list-ul" aria-hidden="true"></i> {{ $item->taskList->name }}</span>
            @endif
            @if ($item->pendingRequest)
                <span class="text-warning-emphasis"><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ $item->pendingRequest->typeLabel() }} بانتظار الرد</span>
            @endif
            @if ($task->details)
                <span><i class="fas fa-align-right" aria-hidden="true"></i> تفاصيل</span>
            @endif
        </span>
    </a>

    <form action="{{ route('tasks.assignments.important', $item) }}" method="POST" class="m-0">
        @csrf @method('PATCH')
        <button type="submit" class="tasks-star {{ $item->is_important ? 'is-on' : '' }}"
                aria-pressed="{{ $item->is_important ? 'true' : 'false' }}"
                aria-label="مهمة مهمة: {{ $task->title }}" title="{{ $item->is_important ? 'إزالة من المهم' : 'تعليم كمهم' }}">
            <i class="{{ $item->is_important ? 'fas' : 'far' }} fa-star" aria-hidden="true"></i>
        </button>
    </form>
</li>
