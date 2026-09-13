@extends('layouts.app')

@section('title', 'إنشاء طلب عرض سعر جديد (RFQ)')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus-circle me-2 text-primary"></i>إصدار طلب عرض سعر جديد (RFQ)
            </h1>
            <p class="text-muted small mb-0">اختيار الموردين المطلوب استدراج عروض الأسعار منهم</p>
        </div>
        <a href="{{ route('purchasing.rfqs.index') }}" class="btn btn-outline-secondary rounded-pill px-4">إلغاء والعودة</a>
    </div>

    <form action="{{ route('purchasing.rfqs.store') }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">ربط بطلب شراء معتمد (اختياري)</label>
                        <select name="purchase_request_id" class="form-select">
                            <option value="">طلب عام / غير مرتبط بطلب شراء محدد</option>
                            @foreach($approvedRequests as $pr)
                            <option value="{{ $pr->id }}" {{ request('purchase_request_id') == $pr->id ? 'selected' : '' }}>
                                {{ $pr->request_number }} - {{ $pr->justification }} ({{ $pr->lines->count() }} بنود)
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">تاريخ الإصدار *</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">آخر موعد لاستلام العروض</label>
                        <input type="date" name="response_due_date" class="form-control" value="{{ now()->addDays(5)->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-bold">الموردون المستهدفون (حدد مورد واحد أو أكثر) *</label>
                        <div class="row g-2 bg-light p-3 rounded-3">
                            @foreach($suppliers as $supplier)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" id="sup-{{ $supplier->id }}">
                                    <label class="form-check-label small fw-bold" for="sup-{{ $supplier->id }}">
                                        {{ $supplier->name }}
                                        <span class="text-muted d-block fs-8">{{ $supplier->phone }}</span>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-bold">ملاحظات / تعليمات للموردين</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="أدخل شروط التسليم، مواصفات التغليف المطلوب..."></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-3 text-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-paper-plane me-1"></i>حفظ وإصدار RFQ
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
