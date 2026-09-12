@extends('layouts.app')

@section('title', 'ملف العميل: ' . $customer->name . ' - مصنع مفروشات سدير')
@section('page-title', 'تفاصيل ملف العميل')

@section('content')
<div class="d-flex flex-column gap-4 max-w-5xl mx-auto" style="max-width: 1000px;">

    <!-- Top Action Bar -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لقائمة العملاء
        </a>
        <div class="d-flex gap-2">
            @can('customers.update')
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-factory-warning btn-sm rounded-3 px-3">
                    <i class="fas fa-edit me-1"></i> تعديل البيانات
                </a>
                <form action="{{ route('customers.toggle-status', $customer) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" 
                            class="btn btn-sm {{ $customer->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-3 px-3"
                            onclick="return confirm('{{ $customer->is_active ? 'هل أنت متأكد من تعطيل هذا العميل؟' : 'هل ترغب في إعادة تفعيل هذا العميل؟' }}');">
                        <i class="fas {{ $customer->is_active ? 'fa-user-slash' : 'fa-user-check' }} me-1"></i>
                        {{ $customer->is_active ? 'تعطيل العميل' : 'تفعيل العميل' }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- Main Profile Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 overflow-hidden">
        
        <!-- Profile Header -->
        <div class="p-4 p-md-5 border-bottom bg-light bg-opacity-50">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle bg-warning text-dark fw-bold rounded-4 d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-dark mb-0">{{ $customer->name }}</h4>
                            @if ($customer->is_active)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1">
                                    <i class="fas fa-check-circle me-1"></i> نشط
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1">
                                    <i class="fas fa-ban me-1"></i> معطل
                                </span>
                            @endif
                        </div>
                        @if ($customer->commercial_name && $customer->commercial_name !== $customer->name)
                            <p class="text-muted mb-0 fs-7">الاسم التجاري: <strong class="text-dark">{{ $customer->commercial_name }}</strong></p>
                        @endif
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="text-muted fs-8 mb-1">رمز العميل التعريفي</div>
                    <code class="bg-white border px-3 py-1.5 rounded-3 text-primary fs-5 fw-bold font-monospace shadow-sm">
                        {{ $customer->customer_code }}
                    </code>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="p-4 p-md-5">
            <div class="row g-4">

                <!-- Block 1: Classification & Channels -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-sitemap text-warning me-1"></i> التصنيف وقناة البيع
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">تصنيف العميل:</span>
                                <span class="fw-semibold text-dark">{{ $customer->customerType?->name_ar ?? 'غير محدد' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">قناة البيع الافتراضية:</span>
                                <span class="fw-semibold text-dark">{{ $customer->defaultSalesChannel?->name_ar ?? 'غير محددة' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">طريقة التعامل:</span>
                                @if ($customer->is_credit_customer)
                                    <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25">
                                        <i class="fas fa-credit-card me-1"></i> عميل آجل / ائتماني
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">دفع نقدي / فوري</span>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 2: Financial Details -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-coins text-warning me-1"></i> البيانات المالية والائتمان
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">الحد الائتماني المسموح:</span>
                                <span class="fw-bold text-dark">
                                    {{ $customer->is_credit_customer ? number_format($customer->credit_limit, 2) . ' ر.س' : 'غير متاح (نقدي)' }}
                                </span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">الرصيد الافتتاحي:</span>
                                <span class="fw-semibold text-dark">{{ number_format($customer->opening_balance, 2) }} ر.س</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">الرقم الضريبي:</span>
                                <span class="font-monospace text-dark">{{ $customer->tax_number ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">رقم السجل التجاري:</span>
                                <span class="font-monospace text-dark">{{ $customer->commercial_registration ?? 'غير مسجل' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 3: Contact Details -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-phone-alt text-warning me-1"></i> بيانات الاتصال
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">المسؤول / جهة الاتصال:</span>
                                <span class="fw-semibold text-dark">{{ $customer->contact_person ?? 'غير محدد' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">رقم الجوال:</span>
                                <span dir="ltr" class="font-monospace text-dark">{{ $customer->mobile ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">رقم الهاتف الثابت:</span>
                                <span dir="ltr" class="font-monospace text-dark">{{ $customer->phone ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">البريد الإلكتروني:</span>
                                <span class="text-dark">{{ $customer->email ?? 'غير مسجل' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 4: Location & Address -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-map-marker-alt text-warning me-1"></i> العنوان والتسليم
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">المدينة:</span>
                                <span class="fw-semibold text-dark">{{ $customer->city ?? 'غير محددة' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">العنوان التفصيلي:</span>
                                <span class="text-dark text-end">{{ $customer->address ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">تاريخ التسجيل في النظام:</span>
                                <span class="text-dark">{{ $customer->created_at->format('Y-m-d H:i') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 5: Notes -->
                @if ($customer->notes)
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-sticky-note text-warning me-1"></i> ملاحظات وشروط خاصة</h6>
                            <p class="mb-0 text-muted fs-7" style="white-space: pre-line;">{{ $customer->notes }}</p>
                        </div>
                    </div>
                @endif

            </div>
        </div>

    </div>

</div>
@endsection
