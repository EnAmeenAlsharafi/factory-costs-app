@extends('layouts.app')

@section('title', $task->title.' - مهامي')
@section('page-title', 'مهامي')

@php
    $canWork = $myAssignment && ! $myAssignment->isDeclined() && ! $task->isCancelled();
    $fromSomeoneElse = $task->is_assigned && ! $isAuthor;
    $statusTones = ['OPEN' => 'secondary', 'IN_PROGRESS' => 'primary', 'DONE' => 'success', 'DECLINED' => 'dark'];
@endphp

@section('content')
<div class="d-flex flex-column gap-3 tasks-show">
    <div>
        <a href="{{ route('tasks.index', ['view' => $isAuthor && $task->is_assigned ? 'assigned-by-me' : ($fromSomeoneElse ? 'assigned-to-me' : 'my-day')]) }}" class="text-decoration-none fs-7">
            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i> العودة إلى مهامي
        </a>
    </div>

    <div class="page-header-card d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
        <div class="min-w-0">
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-2 {{ $myAssignment?->isDone() ? 'text-decoration-line-through text-muted' : '' }}">{{ $task->title }}</h1>
            <div class="d-flex flex-wrap gap-3 fs-7 text-muted">
                @if ($task->isCancelled())
                    <span class="badge bg-secondary align-self-center">ملغاة {{ $task->cancelled_at->format('Y-m-d') }}</span>
                @endif
                @if ($task->is_assigned)
                    <span><i class="fas fa-user-tie" aria-hidden="true"></i> أسندها {{ $isAuthor ? 'أنت' : $task->creator?->name }}</span>
                @endif
                <span><i class="fas fa-clock" aria-hidden="true"></i> أُنشئت {{ $task->created_at->format('Y-m-d') }}</span>
                @php $shownDueDate = $myAssignment?->due_date ?? $task->due_date; @endphp
                @if ($shownDueDate)
                    <span class="{{ $myAssignment?->isOverdue() ? 'text-danger fw-bold' : '' }}">
                        <i class="fas fa-calendar-day" aria-hidden="true"></i> الاستحقاق {{ $shownDueDate->format('Y-m-d') }}
                        @if ($myAssignment?->isOverdue()) (متأخرة) @endif
                    </span>
                @endif
            </div>
        </div>
        @if ($isAuthor && ! $task->isCancelled())
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-primary">
                    <i class="fas fa-pen ms-1" aria-hidden="true"></i> تعديل
                </a>
                <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="m-0"
                      onsubmit="return confirm('{{ $task->is_assigned ? 'إلغاء المهمة؟ ستختفي من قوائم الموظفين المسندة إليهم.' : 'حذف المهمة نهائياً؟' }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas {{ $task->is_assigned ? 'fa-ban' : 'fa-trash' }} ms-1" aria-hidden="true"></i>
                        {{ $task->is_assigned ? 'إلغاء المهمة' : 'حذف' }}
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="row g-3">
        <div class="{{ $canWork ? 'col-lg-7' : 'col-12' }} d-flex flex-column gap-3">
            <section class="card-factory p-3" aria-labelledby="details-heading">
                <h2 id="details-heading" class="h6 fw-bold mb-2">التفاصيل</h2>
                @if ($task->details)
                    <p class="mb-0 tasks-note-body">{{ $task->details }}</p>
                @else
                    <p class="mb-0 text-muted fs-7">لا توجد تفاصيل.</p>
                @endif
            </section>

            @if ($myAssignment?->isDeclined())
                <div class="alert alert-secondary mb-0" role="status">
                    <i class="fas fa-circle-info ms-1" aria-hidden="true"></i> قُبل اعتذارك عن هذه المهمة، ولم تعد في قوائمك.
                </div>
            @endif

            @if ($isAuthor && $task->is_assigned)
                <section class="card-factory p-0" aria-labelledby="assignees-heading">
                    <h2 id="assignees-heading" class="h6 fw-bold mb-0 p-3 border-bottom">الموظفون المسندة إليهم ({{ $task->assignees->count() }})</h2>
                    <ul class="list-unstyled mb-0">
                        @foreach ($task->assignees as $assignee)
                            <li class="tasks-assignee">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <span class="fw-bold text-dark">{{ $assignee->user?->name }}</span>
                                        @if ($assignee->user?->department)
                                            <span class="fs-8 text-muted">· {{ $assignee->user->department->name_ar }}</span>
                                        @endif
                                        <div class="fs-8 text-muted">
                                            @if ($assignee->due_date)
                                                <span class="{{ $assignee->isOverdue() ? 'text-danger fw-bold' : '' }}">الاستحقاق {{ $assignee->due_date->format('Y-m-d') }}</span>
                                            @endif
                                            @if ($assignee->completed_at)
                                                <span>· أُنجزت {{ $assignee->completed_at->format('Y-m-d H:i') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-{{ $statusTones[$assignee->status] ?? 'secondary' }}">{{ $assignee->statusLabel() }}</span>
                                        @if ($assignee->isDone() && ! $task->isCancelled())
                                            <form action="{{ route('tasks.assignments.reopen', $assignee) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="إعادة المهمة للموظف لاستكمالها">إعادة فتح</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @if ($assignee->pendingRequest && ! $task->isCancelled())
                                    @php $pendingRequest = $assignee->pendingRequest; @endphp
                                    <div class="tasks-request-box mt-2">
                                        <div class="fw-bold fs-7 mb-1">
                                            <i class="fas fa-hourglass-half text-warning" aria-hidden="true"></i>
                                            {{ $pendingRequest->typeLabel() }}
                                            @if ($pendingRequest->requested_due_date)
                                                إلى {{ $pendingRequest->requested_due_date->format('Y-m-d') }}
                                            @endif
                                        </div>
                                        <p class="fs-7 mb-2">{{ $pendingRequest->reason }}</p>
                                        <form action="{{ route('tasks.requests.decide', $pendingRequest) }}" method="POST" class="d-flex gap-2 m-0">
                                            @csrf
                                            <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success">
                                                <i class="fas fa-check ms-1" aria-hidden="true"></i> موافقة
                                            </button>
                                            <button type="submit" name="decision" value="reject" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-xmark ms-1" aria-hidden="true"></i> رفض
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        @if ($canWork)
            <div class="col-lg-5 d-flex flex-column gap-3">
                <section class="card-factory p-3" aria-labelledby="my-work-heading">
                    <h2 id="my-work-heading" class="h6 fw-bold mb-3">{{ $fromSomeoneElse ? 'حالتك في المهمة' : 'المهمة' }}</h2>

                    <form action="{{ route('tasks.assignments.status', $myAssignment) }}" method="POST" class="mb-3">
                        @csrf @method('PATCH')
                        <div class="btn-group w-100" role="group" aria-label="حالة المهمة">
                            @foreach (\App\Models\TaskAssignee::WORK_STATUSES as $statusKey => $statusLabel)
                                <button type="submit" name="status" value="{{ $statusKey }}"
                                        class="btn {{ $myAssignment->status === $statusKey ? 'btn-'.$statusTones[$statusKey] : 'btn-outline-'.$statusTones[$statusKey] }}"
                                        aria-pressed="{{ $myAssignment->status === $statusKey ? 'true' : 'false' }}">{{ $statusLabel }}</button>
                            @endforeach
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <form action="{{ route('tasks.assignments.my-day', $myAssignment) }}" method="POST" class="m-0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $myAssignment->isInMyDay() ? 'btn-warning' : 'btn-outline-secondary' }}" aria-pressed="{{ $myAssignment->isInMyDay() ? 'true' : 'false' }}">
                                <i class="fas fa-sun ms-1" aria-hidden="true"></i> {{ $myAssignment->isInMyDay() ? 'في يومي' : 'أضف إلى يومي' }}
                            </button>
                        </form>
                        <form action="{{ route('tasks.assignments.important', $myAssignment) }}" method="POST" class="m-0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $myAssignment->is_important ? 'btn-warning' : 'btn-outline-secondary' }}" aria-pressed="{{ $myAssignment->is_important ? 'true' : 'false' }}">
                                <i class="{{ $myAssignment->is_important ? 'fas' : 'far' }} fa-star ms-1" aria-hidden="true"></i> مهم
                            </button>
                        </form>
                    </div>

                    @if ($lists->isNotEmpty())
                        <form action="{{ route('tasks.assignments.list', $myAssignment) }}" method="POST" class="d-flex gap-2 align-items-end m-0">
                            @csrf @method('PATCH')
                            <div class="flex-grow-1">
                                <label for="task-list" class="form-label fs-8 text-muted mb-1">القائمة</label>
                                <select id="task-list" name="task_list_id" class="form-select form-select-sm">
                                    <option value="">المهام (بدون قائمة)</option>
                                    @foreach ($lists as $list)
                                        <option value="{{ $list->id }}" @selected($myAssignment->task_list_id === $list->id)>{{ $list->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">نقل</button>
                        </form>
                    @endif
                </section>

                @if ($fromSomeoneElse)
                    <section class="card-factory p-3" aria-labelledby="request-heading">
                        <h2 id="request-heading" class="h6 fw-bold mb-1">تحتاج اعتذاراً أو وقتاً إضافياً؟</h2>
                        @if ($myAssignment->pendingRequest)
                            <div class="tasks-request-box mt-2" role="status">
                                <div class="fw-bold fs-7 mb-1">
                                    <i class="fas fa-hourglass-half text-warning" aria-hidden="true"></i>
                                    {{ $myAssignment->pendingRequest->typeLabel() }} بانتظار رد {{ $task->creator?->name }}
                                </div>
                                <p class="fs-7 mb-0">{{ $myAssignment->pendingRequest->reason }}</p>
                            </div>
                        @elseif (! $myAssignment->isDone())
                            <p class="text-muted fs-8 mb-2">يصل طلبك إلى {{ $task->creator?->name }} ليوافق عليه أو يرفضه.</p>
                            <form action="{{ route('tasks.requests.store', $myAssignment) }}" method="POST" x-data="{ type: '{{ old('type', 'EXTEND') }}' }" class="m-0">
                                @csrf
                                <div class="btn-group w-100 mb-2" role="radiogroup" aria-label="نوع الطلب">
                                    <input type="radio" class="btn-check" name="type" id="req-extend" value="EXTEND" x-model="type">
                                    <label class="btn btn-sm btn-outline-primary" for="req-extend">تمديد الموعد</label>
                                    <input type="radio" class="btn-check" name="type" id="req-decline" value="DECLINE" x-model="type">
                                    <label class="btn btn-sm btn-outline-danger" for="req-decline">اعتذار عن المهمة</label>
                                </div>
                                <div class="mb-2" x-show="type === 'EXTEND'">
                                    <label for="requested-due" class="form-label fs-8 text-muted mb-1">التاريخ الجديد المطلوب</label>
                                    <input id="requested-due" type="date" name="requested_due_date" class="form-control form-control-sm"
                                           min="{{ today()->toDateString() }}" value="{{ old('requested_due_date') }}" :required="type === 'EXTEND'">
                                </div>
                                <label for="request-reason" class="form-label fs-8 text-muted mb-1">السبب</label>
                                <textarea id="request-reason" name="reason" rows="2" class="form-control form-control-sm mb-2" maxlength="1000" required>{{ old('reason') }}</textarea>
                                <button type="submit" class="btn btn-sm btn-factory-primary w-100">إرسال الطلب</button>
                            </form>
                        @endif

                        @php $pastRequests = $myAssignment->requests->reject->isPending(); @endphp
                        @if ($pastRequests->isNotEmpty())
                            <h3 class="fs-8 fw-bold text-muted mt-3 mb-1">طلباتك السابقة</h3>
                            <ul class="list-unstyled fs-8 mb-0">
                                @foreach ($pastRequests as $pastRequest)
                                    <li class="py-1 border-top">
                                        {{ $pastRequest->typeLabel() }}
                                        @if ($pastRequest->requested_due_date) إلى {{ $pastRequest->requested_due_date->format('Y-m-d') }} @endif
                                        — <strong class="{{ $pastRequest->status === 'APPROVED' ? 'text-success' : 'text-danger' }}">{{ $pastRequest->statusLabel() }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
