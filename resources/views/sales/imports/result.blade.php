@extends('layouts.app')

@section('title', 'نتيجة استيراد طلبات سلة')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="mb-4">
        <a href="{{ route('sales.imports.index') }}" class="text-decoration-none text-muted fs-7 mb-2 d-inline-block">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل الاستيراد
        </a>
        <h1 class="h3 mb-1 text-dark fw-bold">نتيجة استيراد: {{ $batch->file_name }}</h1>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted fs-7">طلبات تم إنشاؤها</span>
                    <h3 class="fw-bold text-success mb-0">{{ $batch->created_count }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <span class="text-muted fs-7">طلبات تم تجاهلها</span>
                    <h3 class="fw-bold text-secondary mb-0">{{ $batch->skipped_count }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-danger border-4">
                <div class="card-body p-3">
                    <span class="text-muted fs-7">فشل أو مكرر</span>
                    <h3 class="fw-bold text-danger mb-0">{{ $batch->failed_count }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم طلب سلة</th>
                            <th class="text-center">النتيجة</th>
                            <th>التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch->rows as $row)
                            <tr>
                                <td class="fw-bold text-dark">{{ $row->external_order_reference }}</td>
                                <td class="text-center">
                                    @if($row->outcome === 'CREATED')
                                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">تم الإنشاء</span>
                                    @elseif($row->outcome === 'SKIPPED')
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1">تم التجاهل</span>
                                    @elseif($row->outcome === 'DUPLICATE')
                                        <span class="badge bg-warning bg-opacity-25 text-warning-emphasis fw-bold px-3 py-1">مكرر</span>
                                    @elseif($row->outcome === 'FAILED')
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-1">فشل</span>
                                    @else
                                        <span class="badge bg-light text-dark border">{{ $row->outcome ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row->outcome === 'CREATED' && $row->createdCustomerOrder)
                                        <a href="{{ route('sales.orders.show', $row->createdCustomerOrder) }}" class="text-decoration-none">
                                            عرض الطلب #{{ $row->createdCustomerOrder->order_number ?? $row->created_customer_order_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ $row->message ?: '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
