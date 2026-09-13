@extends('layouts.app')

@section('title', 'إنشاء أمر توصيل وتركيب جديد')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-truck-loading text-primary me-2"></i>إنشاء أمر توصيل جديد
            </h1>
            <p class="text-muted small mb-0">لطلب المبيعات: <strong class="text-dark">{{ $customerOrder->order_number }}</strong> (العميل: {{ $customerOrder->customer->name_ar ?? 'غير محدد' }})</p>
        </div>
        <a href="{{ route('sales.orders.show', $customerOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i>العودة لطلب المبيعات
        </a>
    </div>

    <form action="{{ route('delivery.orders.store') }}" method="POST">
        @csrf
        <input type="hidden" name="customer_order_id" value="{{ $customerOrder->id }}">

        <div class="row g-4">
            <!-- Left Column: Order details & Address snapshot -->
            <div class="col-lg-5">
                <!-- Customer Address Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-map-marked-alt text-primary me-2"></i>بيانات موقع التسليم والعميل (Address Snapshot)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">اسم المستلم / العميل <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name_snapshot" class="form-control @error('customer_name_snapshot') is-invalid @enderror" 
                                   value="{{ old('customer_name_snapshot', $customerOrder->customer->name ?? $customerOrder->customer->name_ar ?? '') }}" required>
                            @error('customer_name_snapshot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">رقم الهاتف للتواصل <span class="text-danger">*</span></label>
                            <input type="text" name="customer_phone_snapshot" class="form-control @error('customer_phone_snapshot') is-invalid @enderror" 
                                   value="{{ old('customer_phone_snapshot', $customerOrder->customer->phone ?? '') }}" required dir="ltr">
                            @error('customer_phone_snapshot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">المدينة</label>
                                <input type="text" name="city_snapshot" class="form-control @error('city_snapshot') is-invalid @enderror" 
                                       value="{{ old('city_snapshot', $customerOrder->customer->city ?? 'الرياض') }}">
                                @error('city_snapshot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">الحي / المنطقة</label>
                                <input type="text" name="district_snapshot" class="form-control @error('district_snapshot') is-invalid @enderror" 
                                       value="{{ old('district_snapshot', $customerOrder->customer->district ?? '') }}">
                                @error('district_snapshot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">عنوان الشارع والمبنى التفصيلي</label>
                            <textarea name="delivery_address_snapshot" class="form-control @error('delivery_address_snapshot') is-invalid @enderror" rows="3">{{ old('delivery_address_snapshot', $customerOrder->customer->address ?? '') }}</textarea>
                            @error('delivery_address_snapshot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <!-- Assignment & Schedule Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>جدولة التوصيل والتكليف
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">السائق / الفني المسؤول</label>
                            <select name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror">
                                <option value="">-- اختياري (تأجيل التعيين) --</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ old('assigned_user_id') == $driver->id ? 'selected' : '' }}>
                                        {{ $driver->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">تاريخ التوصيل والتركيب المتوقع</label>
                            <input type="date" name="scheduled_delivery_date" class="form-control @error('scheduled_delivery_date') is-invalid @enderror" 
                                   value="{{ old('scheduled_delivery_date', now()->addDay()->format('Y-m-d')) }}">
                            @error('scheduled_delivery_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">ملاحظات التوصيل والتركيب الخاصة</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات دخول الموقع، مواعيد الشحن..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Lines Selection -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-boxes text-primary me-2"></i>قطع الأثاث الجاهزة للطلب (Lines)
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small">
                                    <tr>
                                        <th class="ps-3">أمر الإنتاج / المنتج</th>
                                        <th>الكمية الإجمالية</th>
                                        <th>المتوفر بالمخزن الجاهز</th>
                                        <th style="width: 140px;">الكمية المشحونة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $lineIndex = 0; @endphp
                                    @foreach($customerOrder->lines as $orderLine)
                                        @foreach($orderLine->productionOrders as $po)
                                            @php
                                                $available = $lineAvailabilities[$po->id] ?? 0.0;
                                            @endphp
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark">{{ $orderLine->productModel->name_ar ?? 'منتج أثاث' }}</div>
                                                    <div class="small text-muted">أمر إنتاج: {{ $po->production_order_number }}</div>
                                                    <input type="hidden" name="lines[{{ $lineIndex }}][production_order_id]" value="{{ $po->id }}">
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">{{ (float)$po->planned_quantity }}</span>
                                                </td>
                                                <td>
                                                    @if($available > 0)
                                                        <span class="badge bg-soft-success text-success fw-bold">{{ (float)$available }} قطعة</span>
                                                    @else
                                                        <span class="badge bg-light text-danger border">0 - لا يوجد رصيد جاهز</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="number" step="1" min="0" max="{{ (float)$available }}" 
                                                           name="lines[{{ $lineIndex }}][quantity]" 
                                                           class="form-control form-control-sm @error("lines.{$lineIndex}.quantity") is-invalid @enderror" 
                                                           value="{{ old("lines.{$lineIndex}.quantity", $available > 0 ? (float)$available : 0) }}" required>
                                                </td>
                                            </tr>
                                            @php $lineIndex++; @endphp
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
                        <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                            <i class="fas fa-save me-1"></i>حفظ كمسودة
                        </button>
                        <button type="submit" name="action" value="ready" class="btn btn-primary shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>اعتماد وتجهيز للتوصيل
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
