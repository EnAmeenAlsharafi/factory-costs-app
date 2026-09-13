@extends('layouts.app')

@section('title', 'تسجيل مرتجع جديد من العميل')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-undo text-danger me-2"></i>تسجيل مرتجع عميل جديد
            </h1>
            <p class="text-muted small mb-0">
                لأمر الإنتاج: <strong class="text-dark">{{ $productionOrder->production_order_number }}</strong> 
                (المنتج: {{ $productionOrder->customerOrderLine->productModel->name_ar ?? 'منتج أثاث' }})
            </p>
        </div>
        <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i>العودة لأمر الإنتاج
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light border-0 py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-edit text-danger me-2"></i>بيانات بلاغ الإرجاع
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info border-0 rounded-3 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle fs-5"></i>
                            <div>
                                <strong>الحد الأقصى المسموح بإرجاعه:</strong> 
                                صافي الكمية المسلمة للعميل من أمر الإنتاج هو <strong class="text-dark">{{ (float)$netDelivered }} قطعة</strong>.
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('customer-returns.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">الكمية المرتجعة <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" max="{{ (float)$netDelivered }}" name="quantity_returned" 
                                       class="form-control @error('quantity_returned') is-invalid @enderror" 
                                       value="{{ old('quantity_returned', 1) }}" required>
                                @error('quantity_returned')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">سبب الإرجاع الرئيسي <span class="text-danger">*</span></label>
                                <select name="reason_code" class="form-select @error('reason_code') is-invalid @enderror" required>
                                    <option value="DEFECTIVE" {{ old('reason_code') === 'DEFECTIVE' ? 'selected' : '' }}>عيب تصنيعي / جودة</option>
                                    <option value="WRONG_SPECIFICATION" {{ old('reason_code') === 'WRONG_SPECIFICATION' ? 'selected' : '' }}>مواصفة غير مطابقة للطلب</option>
                                    <option value="TRANSPORT_DAMAGE" {{ old('reason_code') === 'TRANSPORT_DAMAGE' ? 'selected' : '' }}>تلف أو كسر أثناء النقل والتركيب</option>
                                    <option value="CUSTOMER_CHANGE" {{ old('reason_code') === 'CUSTOMER_CHANGE' ? 'selected' : '' }}>رغبة العميل (تغيير رأي)</option>
                                </select>
                                @error('reason_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">الوصف التفصيلي لسبب الإرجاع وحالة الأثاث</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="4" placeholder="شرح المشكلة بالتفصيل، الأجزاء المتضررة، حالة الخشب أو التنجيد..." required>{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-danger px-4 shadow-sm">
                                <i class="fas fa-check-circle me-1"></i>تسجيل بلاغ المرتجع
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
