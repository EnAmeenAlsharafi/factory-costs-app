@extends('layouts.app')

@section('title', 'عروض الأسعار')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">عروض الأسعار (Quotations)</h1>
            <p class="text-muted mb-0 fs-7">إدارة عروض الأسعار التجارية وتتبع حالتها وتحويل المعتمد منها إلى طلبات عملاء</p>
        </div>
        <div>
            @can('quotations.create')
                <a href="{{ route('sales.quotations.create') }}" class="btn btn-warning px-3 fw-semibold">
                    <i class="fas fa-plus me-1"></i> عرض سعر جديد
                </a>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.quotations.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="بحث برقم العرض أو الملاحظات..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select">
                        <option value="">-- جميع العملاء --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sales_channel_id" class="form-select">
                        <option value="">-- قناة البيع --</option>
                        @foreach($salesChannels as $channel)
                            <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">-- الحالة --</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة</option>
                        <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>معتمد</option>
                        <option value="REJECTED" {{ request('status') === 'REJECTED' ? 'selected' : '' }}>مرفوض</option>
                        <option value="CONVERTED_TO_ORDER" {{ request('status') === 'CONVERTED_TO_ORDER' ? 'selected' : '' }}>محول لطلب</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Quotations Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">رقم العرض</th>
                            <th>العميل</th>
                            <th>قناة البيع</th>
                            <th>تاريخ الاصدار / الصلاحية</th>
                            <th>إجمالي المبلغ</th>
                            <th>الحالة</th>
                            <th class="pe-3 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotations as $quotation)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">
                                    <a href="{{ route('sales.quotations.show', $quotation) }}" class="text-decoration-none fw-bold">
                                        {{ $quotation->quotation_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $quotation->customer?->name }}</div>
                                    <small class="text-muted">{{ $quotation->customer?->phone }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $quotation->salesChannel?->name_ar }}</span>
                                </td>
                                <td>
                                    <div class="fs-7 text-dark">{{ $quotation->issue_date ? $quotation->issue_date->format('Y-m-d') : '-' }}</div>
                                    @if($quotation->valid_until)
                                        <small class="text-muted">حتى {{ $quotation->valid_until->format('Y-m-d') }}</small>
                                    @endif
                                </td>
                                <td class="fw-bold text-dark">
                                    {{ number_format($quotation->total_amount, 2) }} ر.س
                                </td>
                                <td>
                                    @if($quotation->status === 'DRAFT')
                                        <span class="badge bg-secondary">مسودة</span>
                                    @elseif($quotation->status === 'APPROVED')
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> معتمد</span>
                                    @elseif($quotation->status === 'REJECTED')
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> مرفوض</span>
                                    @elseif($quotation->status === 'CONVERTED_TO_ORDER')
                                        <span class="badge bg-info text-dark"><i class="fas fa-shopping-bag me-1"></i> محول لطلب</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('sales.quotations.show', $quotation) }}" class="btn btn-sm btn-outline-primary" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($quotation->status === 'DRAFT')
                                            @can('quotations.edit')
                                                <a href="{{ route('sales.quotations.edit', $quotation) }}" class="btn btn-sm btn-outline-secondary" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-file-invoice-dollar fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد عروض أسعار مسجلة حالياً matching الفلترة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($quotations->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
