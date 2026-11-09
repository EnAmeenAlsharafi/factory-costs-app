{{-- One card per request on every screen size: one column on phones, two from tablet width up. --}}
<div class="task-list portal-card-list">
    @forelse($submissions as $submission)
        @php($presented = \App\Services\StatusPresenter::present('portal_request', $submission->status))
        <article class="task-card tone-{{ $presented['tone'] }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h3 class="task-card-title">
                        <a class="text-reset text-decoration-none" href="{{ route('portal.requests.show', $submission) }}">@if($submission->order)طلب <span class="ltr-isolate">{{ $submission->order->order_number }}</span>@else طلب <span class="ltr-isolate">PR-{{ $submission->id }}</span>@endif</a>
                    </h3>
                    <div class="task-card-ref"><span class="ltr-isolate">PR-{{ $submission->id }}</span>@if($submission->order) &bull; رقم الطلب <span class="ltr-isolate">{{ $submission->order->order_number }}</span>@endif</div>
                </div>
                <x-status-badge domain="portal_request" :status="$submission->status" />
            </div>
            @include('portal.line-cards', ['lines' => $lineCards[$submission->id] ?? [], 'detailsUrl' => route('portal.requests.show', $submission)])
            <div class="task-card-meta">
                <span><i class="far fa-clock" aria-hidden="true"></i> آخر تحديث <span class="ltr-isolate">{{ $submission->updated_at->format('Y-m-d') }}</span></span>
            </div>
            <div class="task-card-actions">
                <a class="btn btn-outline-primary" href="{{ route('portal.requests.show', $submission) }}">عرض الطلب</a>
                @if($submission->status === 'OFFERED')
                    <a class="btn btn-success" href="{{ route('portal.requests.show', $submission) }}">مراجعة العرض</a>
                @elseif(in_array($submission->status, ['DRAFT', 'CHANGES_REQUESTED'], true))
                    <a class="btn btn-primary" href="{{ route('portal.requests.edit', $submission) }}">إكمال الطلب</a>
                @endif
            </div>
        </article>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-file-signature fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="mb-0">لا توجد طلبات في هذه القائمة.</p>
        </div>
    @endforelse
</div>
