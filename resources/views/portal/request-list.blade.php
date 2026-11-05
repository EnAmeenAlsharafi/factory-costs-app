<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>رقم الطلب</th><th>مرجعك</th><th>الحالة</th><th>آخر تحديث</th><th>التفاصيل</th></tr></thead>
    <tbody>@forelse($submissions as $submission)<tr><td><bdi>PR-{{ $submission->id }}</bdi></td><td>{{ $submission->customer_reference ?: '—' }}</td><td>{{ $submission->status_label }}</td><td>{{ $submission->updated_at->format('Y-m-d') }}</td><td><a class="btn btn-outline-primary" href="{{ route('portal.requests.show', $submission) }}">عرض الطلب</a></td></tr>@empty<tr><td colspan="5" class="text-center p-4">لا توجد طلبات في هذه القائمة.</td></tr>@endforelse</tbody>
</table></div></div>
