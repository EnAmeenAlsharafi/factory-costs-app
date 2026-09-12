@extends('layouts.app')

@section('title', 'قوالب التصنيع')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">قوالب التصنيع (Manufacturing Templates)</h1>
            <p class="text-muted mb-0 fs-7">نماذج نمطية مسبقة الإعداد لتعبئة بنود الوصفات تلقائياً عند إنشاء وصفة جديدة</p>
        </div>
        <div>
            @can('manufacturing_templates.manage')
                <a href="{{ route('recipes.templates.create') }}" class="btn btn-warning px-3 fw-semibold">
                    <i class="fas fa-plus me-1"></i> إنشاء قالب جديد
                </a>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('recipes.templates.index') }}" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="بحث باسم القالب أو الكود..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('recipes.templates.index') }}" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Templates Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">كود القالب</th>
                            <th>اسم القالب</th>
                            <th>الوصف</th>
                            <th>عدد البنود</th>
                            <th>الحالة</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $tpl)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $tpl->template_code }}</td>
                                <td class="fw-bold text-dark">{{ $tpl->name_ar }}</td>
                                <td class="text-muted fs-7">{{ $tpl->description ?: '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $tpl->items->count() }} بند</span></td>
                                <td>
                                    @if($tpl->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">معطل</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('recipes.templates.show', $tpl) }}" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="fas fa-eye me-1"></i> عرض البنود / إنشاء وصفة
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-layer-group fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد قوالب تصنيع معرفة حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($templates->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $templates->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
