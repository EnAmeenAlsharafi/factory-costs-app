@extends('layouts.app')

@section('title', 'أوامر الإنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-industry text-primary me-2"></i>أوامر الإنتاج (Production Orders)</h4>
            <p class="text-muted mb-0">إدارة أوامر التصنيع، متابعة حالات الإطلاق والإنجاز والإيقاف بالورشة</p>
        </div>
        @can('production.release')
            <a href="{{ route('production.orders.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> إنشاء أمر إنتاج
            </a>
        @endcan
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('production.orders.index') }}" method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="بحث برقم أمر الإنتاج، رقم طلب العميل، أو اسم العميل..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة</option>
                        <option value="RELEASED" {{ request('status') === 'RELEASED' ? 'selected' : '' }}>تم الإطلاق</option>
                        <option value="IN_PROGRESS" {{ request('status') === 'IN_PROGRESS' ? 'selected' : '' }}>قيد التصنيع</option>
                        <option value="PARTIALLY_COMPLETED" {{ request('status') === 'PARTIALLY_COMPLETED' ? 'selected' : '' }}>مكتمل جزئياً</option>
                        <option value="COMPLETED" {{ request('status') === 'COMPLETED' ? 'selected' : '' }}>مكتمل</option>
                        <option value="ON_HOLD" {{ request('status') === 'ON_HOLD' ? 'selected' : '' }}>معلق</option>
                        <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>ملغى</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">جميع الأولويات</option>
                        <option value="NORMAL" {{ request('priority') === 'NORMAL' ? 'selected' : '' }}>عادي</option>
                        <option value="URGENT" {{ request('priority') === 'URGENT' ? 'selected' : '' }}>عاجل</option>
                        <option value="VIP" {{ request('priority') === 'VIP' ? 'selected' : '' }}>VIP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> تصفية</button>
                    <a href="{{ route('production.orders.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>أمر الإنتاج</th>
                            <th>طلب العميل</th>
                            <th>العميل</th>
                            <th>المنتج / الموديل</th>
                            <th>الأبعاد والنوع</th>
                            <th class="text-center">الكمية مطلق / منجز</th>
                            <th>الأولوية</th>
                            <th>الحالة</th>
                            <th>تاريخ الإطلاق</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $po)
                            <tr>
                                <td>
                                    <a href="{{ route('production.orders.show', $po) }}" class="fw-bold text-primary text-decoration-none font-monospace">
                                        {{ $po->production_order_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="font-monospace text-secondary">{{ $po->customerOrder?->order_number }}</span>
                                    @if($po->has_customer_order_changed)
                                        <span class="badge bg-warning text-dark ms-1" title="تم تعديل طلب العميل بعد الإطلاق"><i class="fas fa-exclamation-triangle"></i></span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $po->customerOrder?->customer?->name_ar }}</span>
                                </td>
                                <td>
                                    @if($po->is_custom_design)
                                        <span class="badge bg-info text-dark"><i class="fas fa-paint-brush me-1"></i>تصميم خاص: {{ $po->custom_design_name }}</span>
                                    @else
                                        <span class="fw-semibold text-dark">{{ $po->productModel?->name_ar }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted d-block">{{ (int)$po->requested_width_cm }} × {{ (int)$po->requested_length_cm }} سم</small>
                                    @if($po->has_storage)
                                        <span class="badge bg-light text-dark border">سحارة</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-success fs-6">{{ $po->completed_quantity }}</span>
                                    <span class="text-muted fs-7">/ {{ $po->released_quantity }}</span>
                                </td>
                                <td>
                                    @if($po->priority === 'VIP')
                                        <span class="badge bg-danger">VIP</span>
                                    @elseif($po->priority === 'URGENT')
                                        <span class="badge bg-warning text-dark">عاجل</span>
                                    @else
                                        <span class="badge bg-secondary">عادي</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $po->status_badge_class }}">{{ $po->status_arabic }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $po->released_at ? $po->released_at->format('Y-m-d') : 'لم يُطلق بعد' }}
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('production.orders.show', $po) }}" class="btn btn-outline-primary" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if(in_array($po->status, ['DRAFT', 'READY_FOR_RELEASE']) && auth()->user()->can('production.release'))
                                            <a href="{{ route('production.orders.release.form', $po) }}" class="btn btn-outline-success" title="إطلاق للورشة">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fas fa-industry fa-2x mb-2 d-block"></i>
                                    لا توجد أوامر إنتاج مسجلة حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
