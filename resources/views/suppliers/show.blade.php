@extends('layouts.app')

@section('title', 'ملف المورد: ' . $supplier->name . ' - مصنع مفروشات سدير')
@section('page-title', 'تفاصيل ملف المورد')

@section('content')
<div class="d-flex flex-column gap-4 max-w-5xl mx-auto" style="max-width: 1000px;">

    <!-- Top Action Bar -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل الموردين
        </a>
        <div class="d-flex gap-2">
            @can('suppliers.manage')
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-factory-warning btn-sm rounded-3 px-3">
                    <i class="fas fa-edit me-1"></i> تعديل البيانات
                </a>
                <form action="{{ route('suppliers.toggle-status', $supplier) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" 
                            class="btn btn-sm {{ $supplier->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-3 px-3"
                            onclick="return confirm('{{ $supplier->is_active ? 'هل أنت متأكد من تعطيل هذا المورد؟' : 'هل ترغب في إعادة تفعيل هذا المورد؟' }}');">
                        <i class="fas {{ $supplier->is_active ? 'fa-ban' : 'fa-check' }} me-1"></i>
                        {{ $supplier->is_active ? 'تعطيل المورد' : 'تفعيل المورد' }}
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
                        <i class="fas fa-truck"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-dark mb-0">{{ $supplier->name }}</h4>
                            @if ($supplier->is_active)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1">
                                    <i class="fas fa-check-circle me-1"></i> نشط
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1">
                                    <i class="fas fa-ban me-1"></i> معطل
                                </span>
                            @endif
                        </div>
                        @if ($supplier->commercial_name && $supplier->commercial_name !== $supplier->name)
                            <p class="text-muted mb-0 fs-7">الاسم التجاري: <strong class="text-dark">{{ $supplier->commercial_name }}</strong></p>
                        @endif
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="text-muted fs-8 mb-1">رمز المورد التعريفي</div>
                    <code class="bg-white border px-3 py-1.5 rounded-3 text-primary fs-5 fw-bold font-monospace shadow-sm">
                        {{ $supplier->supplier_code }}
                    </code>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="p-4 p-md-5">
            <div class="row g-4">

                <!-- Block 1: Contact Details -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-address-book text-warning me-1"></i> معلومات الاتصال والمسؤول
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">مسؤول المبيعات:</span>
                                <span class="fw-semibold text-dark">{{ $supplier->contact_person ?? 'غير محدد' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">رقم الجوال:</span>
                                <span dir="ltr" class="font-monospace text-dark">{{ $supplier->mobile ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">رقم الهاتف الثابت:</span>
                                <span dir="ltr" class="font-monospace text-dark">{{ $supplier->phone ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">البريد الإلكتروني:</span>
                                <span class="text-dark">{{ $supplier->email ?? 'غير مسجل' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 2: Location & Address -->
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 h-100 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-map-marker-alt text-warning me-1"></i> العنوان وموقع المستودع
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-7">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">المدينة:</span>
                                <span class="fw-semibold text-dark">{{ $supplier->city ?? 'غير محددة' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">العنوان التفصيلي:</span>
                                <span class="text-dark text-end">{{ $supplier->address ?? 'غير مسجل' }}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">تاريخ التسجيل:</span>
                                <span class="text-dark">{{ $supplier->created_at->format('Y-m-d') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Block 3: Legal & Tax -->
                <div class="col-12">
                    <div class="p-3 bg-light rounded-4 border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fas fa-file-invoice text-warning me-1"></i> البيانات الرسمية والضريبية
                        </h6>
                        <div class="row g-3 fs-7">
                            <div class="col-md-6 d-flex justify-content-between">
                                <span class="text-muted">الرقم الضريبي:</span>
                                <span class="font-monospace text-dark">{{ $supplier->tax_number ?? 'غير مسجل' }}</span>
                            </div>
                            <div class="col-md-6 d-flex justify-content-between">
                                <span class="text-muted">رقم السجل التجاري:</span>
                                <span class="font-monospace text-dark">{{ $supplier->commercial_registration ?? 'غير مسجل' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block 4: Notes -->
                @if ($supplier->notes)
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-sticky-note text-warning me-1"></i> شروط التوريد وملاحظات</h6>
                            <p class="mb-0 text-muted fs-7" style="white-space: pre-line;">{{ $supplier->notes }}</p>
                        </div>
                    </div>
                @endif

            </div>
        </div>

    </div>

</div>
@endsection
