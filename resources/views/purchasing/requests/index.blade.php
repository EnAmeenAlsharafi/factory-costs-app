@extends('layouts.app')

@section('title', 'طلبات الشراء (PR)')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-signature me-2 text-primary"></i>طلبات الشراء (Purchase Requests)
            </h1>
            <p class="text-muted small mb-0">إدارة وتقديم طلبيات الخامات والمستلزمات واقتراحات الشراء</p>
        </div>
        @can('purchasing.request')
        <a href="{{ route('purchasing.requests.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i>طلب شراء جديد
        </a>
        @endcan
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('purchasing.requests.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="رقم الطلب أو مبرر الشراء...">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة</option>
                        <option value="SUBMITTED" {{ request('status') === 'SUBMITTED' ? 'selected' : '' }}>مرفوع للمراجعة</option>
                        <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>معتمد للشراء</option>
                        <option value="PARTIALLY_ORDERED" {{ request('status') === 'PARTIALLY_ORDERED' ? 'selected' : '' }}>مستخرج له أمر شراء جزئياً</option>
                        <option value="ORDERED" {{ request('status') === 'ORDERED' ? 'selected' : '' }}>مستخرج له أمر شراء كاملاً</option>
                        <option value="REJECTED" {{ request('status') === 'REJECTED' ? 'selected' : '' }}>مرفوض</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="priority" class="form-select">
                        <option value="">جميع الأولويات</option>
                        <option value="NORMAL" {{ request('priority') === 'NORMAL' ? 'selected' : '' }}>عادي</option>
                        <option value="URGENT" {{ request('priority') === 'URGENT' ? 'selected' : '' }}>عاجل</option>
                        <option value="CRITICAL" {{ request('priority') === 'CRITICAL' ? 'selected' : '' }}>طارئ / حرج</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-outline-primary w-100 rounded-pill">تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light small">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>التاريخ</th>
                        <th>مقدم الطلب</th>
                        <th>المصدر / القسم</th>
                        <th>الأولوية</th>
                        <th>عدد البنود</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($requests as $pr)
                    <tr>
                        <td class="fw-bold text-primary">{{ $pr->request_number }}</td>
                        <td>{{ $pr->request_date->format('Y-m-d') }}</td>
                        <td>{{ $pr->requestedByUser?->name }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $pr->source_type }}</span>
                            @if($pr->department)<div class="text-muted fs-8">{{ $pr->department->name_ar }}</div>@endif
                        </td>
                        <td>
                            @if($pr->priority === 'CRITICAL')
                            <span class="badge bg-danger">حرج / طارئ</span>
                            @elseif($pr->priority === 'URGENT')
                            <span class="badge bg-warning text-dark">عاجل</span>
                            @else
                            <span class="badge bg-secondary">عادي</span>
                            @endif
                        </td>
                        <td class="fw-bold">{{ $pr->lines->count() }} بنود</td>
                        <td>
                            @php
                                $statusBadges = [
                                    'DRAFT' => ['bg' => 'bg-secondary', 'label' => 'مسودة'],
                                    'SUBMITTED' => ['bg' => 'bg-info', 'label' => 'مرفوع للمراجعة'],
                                    'APPROVED' => ['bg' => 'bg-success', 'label' => 'معتمد للشراء'],
                                    'PARTIALLY_ORDERED' => ['bg' => 'bg-primary', 'label' => 'أمر شراء جزئي'],
                                    'ORDERED' => ['bg' => 'bg-dark', 'label' => 'مكتمل الطلب'],
                                    'REJECTED' => ['bg' => 'bg-danger', 'label' => 'مرفوض'],
                                ];
                                $st = $statusBadges[$pr->status] ?? ['bg' => 'bg-secondary', 'label' => $pr->status];
                            @endphp
                            <span class="badge {{ $st['bg'] }} px-2 py-1">{{ $st['label'] }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('purchasing.requests.show', $pr) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                التفاصيل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا توجد طلبات شراء مسجلة.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $requests->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
