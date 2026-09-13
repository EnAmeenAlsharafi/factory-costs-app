@extends('layouts.app')

@section('title', 'مرتجعات العملاء والمنتجات الجاهزة')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-undo text-danger me-2"></i>سجلات مرتجعات العملاء
            </h1>
            <p class="text-muted small mb-0">متابعة المنتجات المرتجعة من العملاء، إعادتها للمخزون، وربطها بحوادث الجودة والعيوب المصنعية.</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('customer-returns.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0" 
                               placeholder="رقم المرتجع، رقم الطلب، أمر الإنتاج..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="reason_code" class="form-select bg-light" onchange="this.form.submit()">
                        <option value="">-- سبب الإرجاع --</option>
                        <option value="DEFECTIVE" {{ request('reason_code') === 'DEFECTIVE' ? 'selected' : '' }}>عيب تصنيعي / جودة</option>
                        <option value="WRONG_SPECIFICATION" {{ request('reason_code') === 'WRONG_SPECIFICATION' ? 'selected' : '' }}>مواصفة غير مطابقة</option>
                        <option value="TRANSPORT_DAMAGE" {{ request('reason_code') === 'TRANSPORT_DAMAGE' ? 'selected' : '' }}>تلف أثناء النقل والتركيب</option>
                        <option value="CUSTOMER_CHANGE" {{ request('reason_code') === 'CUSTOMER_CHANGE' ? 'selected' : '' }}>رغبة العميل</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select bg-light" onchange="this.form.submit()">
                        <option value="">-- حالة المرتجع --</option>
                        <option value="REPORTED" {{ request('status') === 'REPORTED' ? 'selected' : '' }}>تم الإبلاغ عنه</option>
                        <option value="RECEIVED_AT_FACTORY" {{ request('status') === 'RECEIVED_AT_FACTORY' ? 'selected' : '' }}>مستلم بالمصنع</option>
                        <option value="LINKED_TO_QUALITY" {{ request('status') === 'LINKED_TO_QUALITY' ? 'selected' : '' }}>مرتبط بحادثة جودة</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100">فلترة</button>
                    @if(request()->hasAny(['search', 'reason_code', 'status']))
                        <a href="{{ route('customer-returns.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4">رقم المرتجع</th>
                            <th>طلب المبيعات</th>
                            <th>أمر الإنتاج</th>
                            <th>الكمية المرتجعة</th>
                            <th>سبب الإرجاع</th>
                            <th>تاريخ الإبلاغ</th>
                            <th>الحالة</th>
                            <th class="text-end pe-4">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customerReturns as $return)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('customer-returns.show', $return) }}" class="fw-bold text-danger text-decoration-none">
                                    {{ $return->return_number }}
                                </a>
                            </td>
                            <td>
                                @if($return->customerOrder)
                                    <span class="badge bg-light text-dark border">{{ $return->customerOrder->order_number }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($return->productionOrder)
                                    <span class="badge bg-light text-primary border">{{ $return->productionOrder->production_order_number }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-danger px-3 py-1 fs-6">{{ (float)$return->quantity_returned }} قطعة</span>
                            </td>
                            <td>
                                @php
                                    $reasons = [
                                        'DEFECTIVE' => 'عيب تصنيعي',
                                        'WRONG_SPECIFICATION' => 'مواصفة غير مطابقة',
                                        'TRANSPORT_DAMAGE' => 'تلف في النقل',
                                        'CUSTOMER_CHANGE' => 'رغبة العميل',
                                    ];
                                @endphp
                                <span class="badge bg-soft-warning text-dark border">
                                    {{ $reasons[$return->reason_code] ?? $return->reason_code }}
                                </span>
                            </td>
                            <td>
                                {{ $return->reported_at ? $return->reported_at->format('Y-m-d H:i') : '' }}
                            </td>
                            <td>
                                @php
                                    $statusBadges = [
                                        'REPORTED' => 'bg-warning text-dark',
                                        'RECEIVED_AT_FACTORY' => 'bg-info',
                                        'LINKED_TO_QUALITY' => 'bg-danger',
                                    ];
                                    $statusLabels = [
                                        'REPORTED' => 'بلاغ مسجل',
                                        'RECEIVED_AT_FACTORY' => 'تم استلامه بالمصنع',
                                        'LINKED_TO_QUALITY' => 'مرتبط بحادثة جودة',
                                    ];
                                @endphp
                                <span class="badge {{ $statusBadges[$return->status] ?? 'bg-secondary' }} px-2 py-1">
                                    {{ $statusLabels[$return->status] ?? $return->status }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('customer-returns.show', $return) }}" class="btn btn-sm btn-light rounded-circle shadow-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye text-danger"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-undo text-light display-4 d-block mb-3"></i>
                                لا توجد سجلات مرتجعات مسجلة في الوقت الحالي.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($customerReturns->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $customerReturns->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
