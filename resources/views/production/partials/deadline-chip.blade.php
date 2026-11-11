{{-- A stage against its department deadline: «متبقٍ 3 أيام», «متأخر يومين», «أُنجز في الوقت». $operation. --}}
@php $deadline = $operation->deadlineStatus(); @endphp
@if ($deadline['state'] !== 'none')
    <span class="status-chip status-chip-{{ $deadline['tone'] }} deadline-chip" title="موعد الخروج من القسم: {{ $deadline['due_at']?->format('Y-m-d H:i') }}{{ $deadline['blocked'] ? ' (مُمدد بسبب عائق)' : '' }}">
        <i class="fas {{ ['overdue' => 'fa-hourglass-end', 'warning' => 'fa-hourglass-half', 'on_track' => 'fa-hourglass-start', 'done_on_time' => 'fa-circle-check', 'done_late' => 'fa-clock-rotate-left'][$deadline['state']] }}" aria-hidden="true"></i>
        {{ $deadline['label'] }}
    </span>
@endif
