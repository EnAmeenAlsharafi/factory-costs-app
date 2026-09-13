@extends('layouts.app')

@section('title', 'أوامر التوصيل والتركيب')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-truck-loading text-primary me-2">
                </i>أوامر التوصيل والتركيب
            </h1>
            <p class="text-muted small mb-0">إدارة وشحن منتجات الأثاث الجاهزة، تعيين السائقين ومتابعة حالات التسليم والتركيب.</p>
        </div>
        <div class="d-flex gap-2">
            @can('delivery.view')
            <a href="{{ route('delivery.orders.my-tasks') }}" class="btn btn-outline-primary shadow-sm">
                <i class="fas fa-tasks me-1">
                </i>مهامي (عرض السائق)
            </a>
            @endcan
        </div>
    </div>

    <!-- Filters & Search Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('delivery.orders.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted">
                            </i>
                        </span>
                        <input type="text" name="search" class="form-control bg-light border-start-0" 
                               placeholder="رقم الأمر، اسم العميل، الهاتف..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select bg-light" onchange="this.form.submit()">
                        <option value="">-- جميع الحالات --</option>
                        <option value="PENDING" {{ request('status') === 'PENDING' ? 'selected' : '' }}>جديد / مسودة</option>
                        <option value="ASSIGNED" {{ request('status') === 'ASSIGNED' ? 'selected' : '' }}>تم تعيين الفني/السائق</option>
                        <option value="READY_FOR_DELIVERY" {{ request('status') === 'READY_FOR_DELIVERY' ? 'selected' : '' }}>جاهز للتوصيل</option>
                        <option value="OUT_FOR_DELIVERY" {{ request('status') === 'OUT_FOR_DELIVERY' ? 'selected' : '' }}>خرج للتوصيل (في الطريق)</option>
                        <option value="DELIVERED" {{ request('status') === 'DELIVERED' ? 'selected' : '' }}>تم التسليم للعميل</option>
                        <option value="INSTALLED" {{ request('status') === 'INSTALLED' ? 'selected' : '' }}>تم التركيب النهائي</option>
                        <option value="FAILED" {{ request('status') === 'FAILED' ? 'selected' : '' }}>فشل التسليم</option>
                        <option value="RESCHEDULED" {{ request('status') === 'RESCHEDULED' ? 'selected' : '' }}>معاد جدولته</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="assigned_user_id" class="form-select bg-light" onchange="this.form.submit()">
                        <option value="">-- جميع السائقين / الفنيين --</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" {{ request('assigned_user_id') == $driver->id ? 'selected' : '' }}>
                                {{ $driver->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100">فلترة</button>
                    @if(request()->hasAny(['search', 'status', 'assigned_user_id']))
                        <a href="{{ route('delivery.orders.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery Orders Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4">رقم أمر التوصيل</th>
                            <th>طلب المبيعات</th>
                            <th>العميل والمعلومات</th>
                            <th>المدينة / المنطقة</th>
                            <th>المسؤول / السائق</th>
                            <th>تاريخ التوصيل المجدول</th>
                            <th>الحالة</th>
                            <th class="text-end pe-4">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveryOrders as $delivery)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('delivery.orders.show', $delivery) }}" class="fw-bold text-primary text-decoration-none">
                                    {{ $delivery->delivery_number }}
                                </a>
                            </td>
                            <td>
                                @if($delivery->customerOrder)
                                    <a href="{{ route('sales.orders.show', $delivery->customerOrder) }}" class="badge bg-light text-dark border text-decoration-none">
                                        {{ $delivery->customerOrder->order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $delivery->customer_name_snapshot }}</div>
                                <div class="text-muted small" dir="ltr"><i class="fas fa-phone me-1"></i>{{ $delivery->customer_phone_snapshot }}</div>
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info">
                                    {{ $delivery->city_snapshot ?? 'غير محدد' }} - {{ $delivery->district_snapshot ?? '-' }}
                                </span>
                            </td>
                            <td>
                                @if($delivery->assignedUser)
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <i class="fas fa-user-circle text-secondary"></i>
                                        <span class="fw-semibold small">{{ $delivery->assignedUser->name }}</span>
                                    </span>
                                @else
                                    <span class="badge bg-light text-warning border">غير معين</span>
                                @endif
                            </td>
                            <td>
                                {{ $delivery->scheduled_delivery_date ? $delivery->scheduled_delivery_date->format('Y-m-d') : 'غير محدد' }}
                            </td>
                            <td>
                                @php
                                    $badgeClasses = [
                                        'PENDING' => 'bg-secondary',
                                        'ASSIGNED' => 'bg-info',
                                        'READY_FOR_DELIVERY' => 'bg-primary',
                                        'OUT_FOR_DELIVERY' => 'bg-warning text-dark',
                                        'DELIVERED' => 'bg-success',
                                        'INSTALLED' => 'bg-teal text-white',
                                        'FAILED' => 'bg-danger',
                                        'RESCHEDULED' => 'bg-dark',
                                        'CANCELLED' => 'bg-secondary',
                                    ];
                                    $statusLabels = [
                                        'PENDING' => 'مسودة',
                                        'ASSIGNED' => 'تم التعيين',
                                        'READY_FOR_DELIVERY' => 'جاهز للشحن',
                                        'OUT_FOR_DELIVERY' => 'خرج للتوصيل',
                                        'DELIVERED' => 'تم التسليم',
                                        'INSTALLED' => 'تم التركيب',
                                        'FAILED' => 'متعذر التسليم',
                                        'RESCHEDULED' => 'معاد جدولته',
                                        'CANCELLED' => 'ملغى',
                                    ];
                                @endphp
                                <span class="badge {{ $badgeClasses[$delivery->status] ?? 'bg-secondary' }} px-2 py-1">
                                    {{ $statusLabels[$delivery->status] ?? $delivery->status }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('delivery.orders.show', $delivery) }}" class="btn btn-sm btn-light rounded-circle shadow-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye text-primary"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-truck text-light display-4 d-block mb-3"></i>
                                لا توجد أوامر توصيل مسجلة تطابق محددات البحث.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($deliveryOrders->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $deliveryOrders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
