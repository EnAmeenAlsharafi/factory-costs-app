{{-- Phone: one card per request. Tablet/desktop: the same rows as a table. --}}
<div class="task-list mobile-cards-only-lg">
    @forelse($submissions as $submission)
        @php($presented = \App\Services\StatusPresenter::present('portal_request', $submission->status))
        <article class="task-card tone-{{ $presented['tone'] }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h3 class="task-card-title">
                        <a class="text-reset text-decoration-none" href="{{ route('portal.requests.show', $submission) }}">{{ $submission->customer_reference ?: 'طلب بدون مرجع' }}</a>
                    </h3>
                    <div class="task-card-ref"><span class="ltr-isolate">PR-{{ $submission->id }}</span></div>
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

<div class="card border-0 shadow-sm has-mobile-cards-lg">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>رقم الطلب</th><th>مرجعك</th><th>الحالة</th><th>آخر تحديث</th><th>التفاصيل</th></tr></thead>
            <tbody>
                @forelse($submissions as $submission)
                    <tr>
                        <td><span class="ltr-isolate">PR-{{ $submission->id }}</span></td>
                        <td>{{ $submission->customer_reference ?: '—' }}</td>
                        <td><x-status-badge domain="portal_request" :status="$submission->status" /></td>
                        <td><span class="ltr-isolate">{{ $submission->updated_at->format('Y-m-d') }}</span></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('portal.requests.show', $submission) }}">عرض الطلب</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center p-4">لا توجد طلبات في هذه القائمة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
