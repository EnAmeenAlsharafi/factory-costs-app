@extends('layouts.app')

@section('title', 'مهامي - مصنع مفروشات سدير')
@section('page-title', 'مهامي')

@php
    $viewIcon = $currentList ? 'fa-list-ul' : $views[$view]['icon'];
    $isTaskView = ! in_array($view, ['notes', 'assigned-by-me'], true);
    $canQuickAdd = $isTaskView && $view !== 'assigned-to-me';
    $emptyMessages = [
        'my-day' => ['يومك فارغ', 'أضف ما تنوي إنجازه اليوم، أو اختر من مهامك بزر «يومي» في أي مهمة.'],
        'important' => ['لا توجد مهام مهمة', 'اضغط النجمة بجانب أي مهمة لتظهر هنا.'],
        'planned' => ['لا توجد مهام بمواعيد', 'المهام التي لها تاريخ استحقاق تظهر هنا مرتبة حسب الموعد.'],
        'assigned-to-me' => ['لا توجد مهام مسندة إليك', 'حين يسند إليك أحد مهمة ستظهر هنا ويصلك إشعار بها.'],
        'inbox' => ['لا توجد مهام', 'اكتب مهمتك الأولى في الخانة أعلاه.'],
    ];
    $empty = $emptyMessages[$view] ?? ['القائمة فارغة', 'اكتب مهمة في الخانة أعلاه لتُضاف إلى هذه القائمة.'];
@endphp

@section('content')
<div class="tasks-page">
    <div class="row g-3">
        {{-- Lists --}}
        <nav class="col-lg-3" aria-label="قوائم المهام">
            <div class="card-factory tasks-nav">
                {{-- On phones the lists are a sideways row of chips: bring the current one into view. --}}
                <ul class="tasks-nav-list list-unstyled mb-0"
                    x-data x-init="$nextTick(() => { const active = $el.querySelector('.active'); if (active && $el.scrollWidth > $el.clientWidth) { $el.scrollLeft += active.getBoundingClientRect().left - $el.getBoundingClientRect().left - ($el.clientWidth - active.offsetWidth) / 2; } })">
                    @foreach ($views as $key => $item)
                        <li>
                            <a href="{{ route('tasks.index', ['view' => $key]) }}"
                               class="tasks-nav-link {{ $view === $key ? 'active' : '' }}"
                               @if ($view === $key) aria-current="page" @endif>
                                <i class="fas {{ $item['icon'] }} fa-fw" aria-hidden="true"></i>
                                <span class="flex-grow-1">{{ $item['label'] }}</span>
                                @if (($counts[$key] ?? 0) > 0)
                                    <span class="tasks-nav-count {{ $key === 'assigned-by-me' ? 'is-alert' : '' }}"
                                          @if ($key === 'assigned-by-me') title="طلبات بانتظار ردك" @endif>{{ $counts[$key] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                    @if ($lists->isNotEmpty())
                        <li class="tasks-nav-divider" role="presentation"></li>
                    @endif
                    @foreach ($lists as $list)
                        <li>
                            <a href="{{ route('tasks.index', ['view' => 'list-'.$list->id]) }}"
                               class="tasks-nav-link {{ $currentList?->id === $list->id ? 'active' : '' }}"
                               @if ($currentList?->id === $list->id) aria-current="page" @endif>
                                <i class="fas fa-list-ul fa-fw" aria-hidden="true"></i>
                                <span class="flex-grow-1 text-truncate">{{ $list->name }}</span>
                                @if (($counts['list-'.$list->id] ?? 0) > 0)
                                    <span class="tasks-nav-count">{{ $counts['list-'.$list->id] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <form action="{{ route('tasks.lists.store') }}" method="POST" class="tasks-new-list d-flex" x-data="{ open: false }">
                    @csrf
                    <button type="button" class="btn btn-link text-decoration-none px-2" x-show="!open" @click="open = true; $nextTick(() => $refs.name.focus())">
                        <i class="fas fa-plus ms-1" aria-hidden="true"></i> قائمة جديدة
                    </button>
                    <div class="input-group input-group-sm" x-show="open" x-cloak>
                        <input type="text" name="name" x-ref="name" class="form-control" maxlength="80" placeholder="اسم القائمة" aria-label="اسم القائمة الجديدة" required>
                        <button type="submit" class="btn btn-factory-primary">إضافة</button>
                    </div>
                </form>
            </div>
        </nav>

        {{-- Content --}}
        <section class="col-lg-9" aria-labelledby="page-heading">
            <div class="page-header-card d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="min-w-0">
                    <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="fas {{ $viewIcon }} text-warning" aria-hidden="true"></i>
                        <span class="text-truncate">{{ $title }}</span>
                    </h1>
                    <p class="page-header-subtitle text-muted mb-0 fs-7">
                        @switch($view)
                            @case('my-day') {{ now()->locale('ar')->translatedFormat('l j F') }} @break
                            @case('assigned-by-me') مهام أسندتها لغيرك: تقدّم كل موظف والطلبات التي تنتظر ردك. @break
                            @case('notes') ملاحظاتك خاصة بك، لا يراها أحد غيرك. @break
                            @case('assigned-to-me') ما أسنده إليك غيرك. حدّث حالتك، أو اطلب تمديداً أو اعتذاراً من صفحة المهمة. @break
                            @default مهامك الشخصية لا يراها أحد غيرك.
                        @endswitch
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($canAssign)
                        <a href="{{ route('tasks.create') }}" class="btn btn-warning fw-bold">
                            <i class="fas fa-share-from-square ms-1" aria-hidden="true"></i> إسناد مهمة
                        </a>
                    @endif
                    @if ($currentList)
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="خيارات القائمة">
                                <i class="fas fa-ellipsis" aria-hidden="true"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px;">
                                <form action="{{ route('tasks.lists.update', $currentList) }}" method="POST" class="mb-2">
                                    @csrf @method('PUT')
                                    <label for="rename-list" class="form-label fs-8 text-muted mb-1">اسم القائمة</label>
                                    <div class="input-group input-group-sm">
                                        <input id="rename-list" type="text" name="name" class="form-control" value="{{ $currentList->name }}" maxlength="80" required>
                                        <button class="btn btn-outline-primary" type="submit">حفظ</button>
                                    </div>
                                </form>
                                <form action="{{ route('tasks.lists.destroy', $currentList) }}" method="POST"
                                      onsubmit="return confirm('حذف القائمة؟ مهامها لن تُحذف، بل تنتقل إلى «المهام».')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                                        <i class="fas fa-trash ms-1" aria-hidden="true"></i> حذف القائمة
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($canQuickAdd)
                <form action="{{ route('tasks.store') }}" method="POST" class="card-factory tasks-quick-add mb-3">
                    @csrf
                    @if ($view === 'my-day')<input type="hidden" name="my_day" value="1">@endif
                    @if ($view === 'important')<input type="hidden" name="is_important" value="1">@endif
                    @if ($currentList)<input type="hidden" name="task_list_id" value="{{ $currentList->id }}">@endif
                    <i class="fas fa-plus text-primary" aria-hidden="true"></i>
                    <input type="text" name="title" class="form-control border-0 shadow-none" maxlength="200"
                           placeholder="أضف مهمة..." aria-label="عنوان المهمة الجديدة" required value="{{ old('title') }}">
                    <input type="date" name="due_date" class="form-control form-control-sm tasks-quick-date"
                           aria-label="تاريخ الاستحقاق (اختياري)" title="تاريخ الاستحقاق (اختياري)"
                           value="{{ old('due_date', $view === 'planned' ? today()->toDateString() : '') }}">
                    <button type="submit" class="btn btn-factory-primary btn-sm">إضافة</button>
                </form>
            @endif

            @if ($view === 'notes')
                @include('tasks.partials.notes')
            @elseif ($view === 'assigned-by-me')
                @include('tasks.partials.authored')
            @else
                @if ($openItems->isEmpty() && $doneItems->isEmpty())
                    <div class="card-factory empty-state" role="status">
                        <i class="fas {{ $viewIcon }} fs-2 mb-3 text-secondary" aria-hidden="true"></i>
                        <h2 class="h6 fw-bold text-dark">{{ $empty[0] }}</h2>
                        <p class="mb-0 fs-7">{{ $empty[1] }}</p>
                    </div>
                @else
                    @if ($view === 'planned')
                        @php
                            $groups = $openItems->groupBy(fn ($item) => match (true) {
                                $item->due_date->lt(today()) => 'متأخرة',
                                $item->due_date->isToday() => 'اليوم',
                                $item->due_date->lte(today()->addDays(7)) => 'خلال أسبوع',
                                default => 'لاحقاً',
                            });
                        @endphp
                        @foreach (['متأخرة', 'اليوم', 'خلال أسبوع', 'لاحقاً'] as $groupName)
                            @if ($groups->has($groupName))
                                <h2 class="tasks-group-title {{ $groupName === 'متأخرة' ? 'text-danger' : '' }}">{{ $groupName }} <span class="text-muted fw-normal">({{ $groups[$groupName]->count() }})</span></h2>
                                <ul class="tasks-items list-unstyled card-factory mb-3">
                                    @foreach ($groups[$groupName] as $item)
                                        @include('tasks.partials.item', ['item' => $item])
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    @elseif ($openItems->isNotEmpty())
                        <ul class="tasks-items list-unstyled card-factory mb-3">
                            @foreach ($openItems as $item)
                                @include('tasks.partials.item', ['item' => $item])
                            @endforeach
                        </ul>
                    @endif

                    @if ($doneItems->isNotEmpty())
                        <details class="tasks-done" @if ($openItems->isEmpty()) open @endif>
                            <summary class="tasks-group-title">منجزة <span class="text-muted fw-normal">({{ $doneItems->count() }})</span></summary>
                            <ul class="tasks-items list-unstyled card-factory">
                                @foreach ($doneItems as $item)
                                    @include('tasks.partials.item', ['item' => $item])
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endif
            @endif
        </section>
    </div>
</div>
@endsection
