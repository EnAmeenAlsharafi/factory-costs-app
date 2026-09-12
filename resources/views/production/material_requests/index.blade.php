@extends('layouts.app')

@section('title', 'طلبات خامات الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">طلبات خامات الإنتاج (Production Material Requests)</h4>
            <p class="text-muted mb-0 mt-1">إدارة وصرف طلبات الخامات الصادرة من أقسام الورشة إلى المستودع</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.orders.index') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> طلب جديد من أمر إنتاج
            </a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('production.material-requests.index') }}" class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="بحث برقم الطلب أو رقم أمر الإنتاج..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة (DRAFT)</option>
                        <option value="SUBMITTED" {{ request('status') === 'SUBMITTED' ? 'selected' : '' }}>مقدم للمستودع (SUBMITTED)</option>
                        <option value="PARTIALLY_FULFILLED" {{ request('status') === 'PARTIALLY_FULFILLED' ? 'selected' : '' }}>مستوفى جزئياً (PARTIALLY FULFILLED)</option>
                        <option value="FULFILLED" {{ request('status') === 'FULFILLED' ? 'selected' : '' }}>مستوفى بالكامل (FULFILLED)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    @if(request()->anyFilled(['search', 'status']))
                        <a href="{{ route('production.material-requests.index') }}" class="btn btn-outline-secondary" title="إلغاء التصفية"><i class="fas fa-times"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم الطلب</th>
                            <th>أمر الإنتاج</th>
                            <th>القسم الطالب</th>
                            <th>المستودع</th>
                            <th>تاريخ الطلب</th>
                            <th>عدد البنود</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td>
                                    <a href="{{ route('production.material-requests.show', $req) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $req->request_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('production.orders.show', $req->productionOrder) }}" class="font-monospace text-dark text-decoration-none">
                                        {{ $req->productionOrder?->production_order_number }}
                                    </a>
                                </td>
                                <td>{{ $req->requestedFromDepartment?->name_ar ?? 'غير محدد' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $req->warehouse?->name_ar }}</span></td>
                                <td>{{ $req->request_date?->format('Y-m-d') }}</td>
                                <td><span class="badge bg-secondary rounded-pill">{{ $req->lines->count() }}</span></td>
                                <td>
                                    @php
                                        $statusClass = match($req->status) {
                                            'DRAFT' => 'bg-secondary',
                                            'SUBMITTED' => 'bg-warning text-dark',
                                            'PARTIALLY_FULFILLED' => 'bg-info text-dark',
                                            'FULFILLED' => 'bg-success',
                                            'REJECTED' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        $statusAr = match($req->status) {
                                            'DRAFT' => 'مسودة',
                                            'SUBMITTED' => 'مقدم للمستودع',
                                            'PARTIALLY_FULFILLED' => 'صرف جزئي',
                                            'FULFILLED' => 'صرف مكتمل',
                                            'REJECTED' => 'مرفوض',
                                            default => $req->status
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} fs-7">{{ $statusAr }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.material-requests.show', $req) }}" class="btn btn-sm btn-outline-primary me-1" title="عرض والتفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(in_array($req->status, ['SUBMITTED', 'PARTIALLY_FULFILLED']) && (auth()->user()->can('inventory.issue') || auth()->user()->isAdministrator()))
                                        <a href="{{ route('production.material-requests.fulfill.form', $req) }}" class="btn btn-sm btn-success" title="صرف المواد من المستودع">
                                            <i class="fas fa-dolly me-1"></i> صرف
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-clipboard-list fa-3x mb-3 d-block"></i>
                                    لا توجد طلبات خامات إنتاج مدونة حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
