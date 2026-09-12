@extends('layouts.app')

@section('title', 'مسارات التصنيع - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-route text-primary me-2"></i>مسارات التصنيع (Production Routings)</h4>
            <p class="text-muted mb-0">إدارة القوالب التشغيلية ومسارات العمليات المتعاقبة والمتوازية للورشة</p>
        </div>
        @can('production.manage_routing')
            <a href="{{ route('production.routings.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> إنشاء مسار تصنيع جديد
            </a>
        @endcan
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('production.routings.index') }}" method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="بحث بكود المسار أو اسم المسار..." value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> تصفية</button>
                    <a href="{{ route('production.routings.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Routings Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>كود المسار</th>
                            <th>اسم المسار</th>
                            <th>عدد العمليات</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routings as $routing)
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-primary">{{ $routing->routing_code }}</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $routing->name_ar }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $routing->operations_count }} عمليات</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ Str::limit($routing->description ?? 'لا يوجد وصف', 50) }}</span>
                                </td>
                                <td>
                                    @if($routing->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">معطل</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $routing->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('production.routings.show', $routing) }}" class="btn btn-outline-primary" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @can('production.manage_routing')
                                            <form action="{{ route('production.routings.toggle-status', $routing) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-{{ $routing->is_active ? 'warning' : 'success' }}" title="{{ $routing->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                    <i class="fas fa-{{ $routing->is_active ? 'ban' : 'check' }}"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-route fa-2x mb-2 d-block"></i>
                                    لا توجد مسارات تصنيع معرفة حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($routings->hasPages())
            <div class="card-footer bg-white">
                {{ $routings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
