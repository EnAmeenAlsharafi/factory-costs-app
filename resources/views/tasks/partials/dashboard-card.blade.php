{{-- Home card «مهامي اليوم»: late, due today and planned for today, plus requests waiting on the user. --}}
<section class="card-factory p-3" aria-labelledby="dash-my-tasks">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 id="dash-my-tasks" class="h6 fw-bold mb-0 d-flex align-items-center gap-2 text-dark">
            <i class="fas fa-list-check text-warning" aria-hidden="true"></i> مهامي اليوم
        </h2>
        <div class="d-flex flex-wrap gap-1 fs-8">
            @if ($myTasks['overdue'] > 0)
                <span class="badge bg-danger">{{ $myTasks['overdue'] }} متأخرة</span>
            @endif
            @if ($myTasks['due_today'] > 0)
                <span class="badge bg-warning text-dark">{{ $myTasks['due_today'] }} مستحقة اليوم</span>
            @endif
            @if ($myTasks['pending_decisions'] > 0)
                <a href="{{ route('tasks.index', ['view' => 'assigned-by-me']) }}" class="badge bg-primary text-decoration-none">{{ $myTasks['pending_decisions'] }} طلب بانتظار ردك</a>
            @endif
        </div>
    </div>

    @if ($myTasks['items']->isEmpty())
        <p class="text-muted fs-7 mb-0">
            لا شيء متأخر أو مستحق اليوم.
            <a href="{{ route('tasks.index') }}" class="text-decoration-none">افتح مهامي</a> لتخطيط يومك.
        </p>
    @else
        <ul class="list-unstyled mb-2 tasks-dash-list">
            @foreach ($myTasks['items'] as $item)
                <li>
                    <a href="{{ route('tasks.show', $item->task) }}" class="d-flex justify-content-between gap-2 text-reset text-decoration-none py-2">
                        <span class="text-truncate">
                            <i class="far fa-circle text-muted ms-1" aria-hidden="true"></i>
                            {{ $item->task->title }}
                            @if ($item->task->is_assigned && $item->task->created_by_user_id !== auth()->id())
                                <span class="fs-8 text-muted">· من {{ $item->task->creator?->name }}</span>
                            @endif
                        </span>
                        @if ($item->due_date)
                            <span class="fs-8 text-nowrap {{ $item->isOverdue() ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ $item->isOverdue() ? 'متأخرة · '.$item->due_date->format('m-d') : ($item->due_date->isToday() ? 'اليوم' : $item->due_date->format('m-d')) }}
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('tasks.index', ['view' => $myTasks['overdue'] > 0 || $myTasks['due_today'] > 0 ? 'planned' : 'my-day']) }}" class="btn btn-sm btn-outline-primary">
            عرض كل المهام @if ($myTasks['total'] > 5) ({{ $myTasks['total'] }}) @endif
        </a>
    @endif
</section>
