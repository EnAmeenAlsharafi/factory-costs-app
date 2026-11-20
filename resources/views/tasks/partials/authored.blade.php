{{-- «أسندتُها»: tasks the user assigned, with each employee's progress and requests awaiting an answer. --}}
@if ($authoredTasks->isEmpty())
    <div class="card-factory empty-state" role="status">
        <i class="fas fa-share-from-square fs-2 mb-3 text-secondary" aria-hidden="true"></i>
        <h2 class="h6 fw-bold text-dark">لم تُسند مهاماً بعد</h2>
        <p class="mb-0 fs-7">
            @if ($canAssign)
                استخدم زر «إسناد مهمة» لتكليف موظف أو أكثر، وتابع تقدّمهم من هنا.
            @else
                صلاحية إسناد المهام لموظفين آخرين تُمنح من شاشة الأدوار والصلاحيات.
            @endif
        </p>
    </div>
@else
    <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
        @foreach ($authoredTasks as $authored)
            @php
                $working = $authored->assignees->reject->isDeclined();
                $doneCount = $working->filter->isDone()->count();
                $total = $working->count();
                $percent = $total ? round($doneCount / $total * 100) : 0;
                $pending = $authored->assignees->filter(fn ($assignee) => $assignee->pendingRequest)->count();
                $late = $working->filter->isOverdue()->count();
            @endphp
            <li>
                <a href="{{ route('tasks.show', $authored) }}" class="card-factory tasks-authored text-reset text-decoration-none {{ $authored->isCancelled() ? 'is-cancelled' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <span class="fw-bold text-dark">{{ $authored->title }}</span>
                        <span class="d-flex flex-wrap gap-1">
                            @if ($authored->isCancelled())
                                <span class="badge bg-secondary">ملغاة</span>
                            @endif
                            @if ($pending > 0 && ! $authored->isCancelled())
                                <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ $pending }} طلب بانتظار ردك</span>
                            @endif
                            @if ($late > 0 && ! $authored->isCancelled())
                                <span class="badge bg-danger">{{ $late }} متأخر</span>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex flex-wrap gap-3 fs-8 text-muted mt-1">
                        <span><i class="fas fa-users" aria-hidden="true"></i> {{ $authored->assignees->map->user->pluck('name')->take(3)->join('، ') }}@if ($authored->assignees->count() > 3) و{{ $authored->assignees->count() - 3 }} آخرين@endif</span>
                        @if ($authored->due_date)
                            <span><i class="fas fa-calendar-day" aria-hidden="true"></i> {{ $authored->due_date->format('Y-m-d') }}</span>
                        @endif
                    </div>
                    @if ($total > 0)
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div class="progress flex-grow-1" role="progressbar" aria-label="نسبة الإنجاز" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: {{ $percent }}%"></div>
                            </div>
                            <span class="fs-8 fw-bold text-nowrap">{{ $doneCount }} من {{ $total }} أنجزوا</span>
                        </div>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
