@extends('layouts.app')

@section('title', 'تعديل بيانات المورد - مصنع مفروشات سدير')
@section('page-title', 'تعديل بيانات المورد: ' . $supplier->name)

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 900px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل الموردين
        </a>
        <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-light border text-secondary">
            <i class="fas fa-eye me-1"></i> عرض التفاصيل
        </a>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fas fa-truck text-warning me-2"></i> تعديل بيانات المورد
                </h5>
                <p class="text-muted fs-7 mb-0">تحديث بيانات الاتصال والمسؤول أو البيانات الضريبية والتجارية.</p>
            </div>
            <code class="bg-light px-3 py-1.5 rounded-3 text-primary fs-6 fw-bold">{{ $supplier->supplier_code }}</code>
        </div>

        <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                
                <!-- Supplier Code (Readonly) -->
                <div class="col-md-4">
                    <label class="form-label text-muted">رمز المورد</label>
                    <input type="text" class="form-control bg-light font-monospace" value="{{ $supplier->supplier_code }}" readonly disabled>
                </div>

                <!-- Full Name -->
                <div class="col-md-4">
                    <label for="name" class="form-label">اسم المورد / الشركة <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', $supplier->name) }}" 
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Commercial Name -->
                <div class="col-md-4">
                    <label for="commercial_name" class="form-label">الاسم التجاري <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="commercial_name" 
                           id="commercial_name" 
                           class="form-control @error('commercial_name') is-invalid @enderror" 
                           value="{{ old('commercial_name', $supplier->commercial_name) }}">
                    @error('commercial_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Section: Contact Information -->
                <div class="col-12 mt-4 pt-2 border-top">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-address-book text-warning me-1"></i> معلومات الاتصال والمسؤول</h6>
                </div>

                <!-- Contact Person -->
                <div class="col-md-4">
                    <label for="contact_person" class="form-label">مسؤول المبيعات / جهة الاتصال</label>
                    <input type="text" 
                           name="contact_person" 
                           id="contact_person" 
                           class="form-control @error('contact_person') is-invalid @enderror" 
                           value="{{ old('contact_person', $supplier->contact_person) }}">
                    @error('contact_person')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Mobile -->
                <div class="col-md-4">
                    <label for="mobile" class="form-label">رقم الجوال</label>
                    <input type="text" 
                           name="mobile" 
                           id="mobile" 
                           dir="ltr" 
                           class="form-control text-end @error('mobile') is-invalid @enderror" 
                           value="{{ old('mobile', $supplier->mobile) }}">
                    @error('mobile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Phone -->
                <div class="col-md-4">
                    <label for="phone" class="form-label">رقم الهاتف الثابت <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="phone" 
                           id="phone" 
                           dir="ltr" 
                           class="form-control text-end @error('phone') is-invalid @enderror" 
                           value="{{ old('phone', $supplier->phone) }}">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Email -->
                <div class="col-md-4">
                    <label for="email" class="form-label">البريد الإلكتروني</label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           class="form-control @error('email') is-invalid @enderror" 
                           value="{{ old('email', $supplier->email) }}">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- City -->
                <div class="col-md-4">
                    <label for="city" class="form-label">المدينة</label>
                    <input type="text" 
                           name="city" 
                           id="city" 
                           class="form-control @error('city') is-invalid @enderror" 
                           value="{{ old('city', $supplier->city) }}">
                    @error('city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Address -->
                <div class="col-md-4">
                    <label for="address" class="form-label">العنوان / المنطقة الصناعية</label>
                    <input type="text" 
                           name="address" 
                           id="address" 
                           class="form-control @error('address') is-invalid @enderror" 
                           value="{{ old('address', $supplier->address) }}">
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Section: Tax & Commercial Registration -->
                <div class="col-12 mt-4 pt-2 border-top">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-file-contract text-warning me-1"></i> البيانات الرسمية والضريبية</h6>
                </div>

                <!-- Tax Number -->
                <div class="col-md-6">
                    <label for="tax_number" class="form-label">الرقم الضريبي (15 رقم)</label>
                    <input type="text" 
                           name="tax_number" 
                           id="tax_number" 
                           dir="ltr" 
                           class="form-control font-monospace text-end @error('tax_number') is-invalid @enderror" 
                           value="{{ old('tax_number', $supplier->tax_number) }}">
                    @error('tax_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Commercial Registration -->
                <div class="col-md-6">
                    <label for="commercial_registration" class="form-label">السجل التجاري (10 أرقام)</label>
                    <input type="text" 
                           name="commercial_registration" 
                           id="commercial_registration" 
                           dir="ltr" 
                           class="form-control font-monospace text-end @error('commercial_registration') is-invalid @enderror" 
                           value="{{ old('commercial_registration', $supplier->commercial_registration) }}">
                    @error('commercial_registration')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Notes -->
                <div class="col-12 mt-3">
                    <label for="notes" class="form-label">ملاحظات وشروط التوريد</label>
                    <textarea name="notes" 
                              id="notes" 
                              rows="3" 
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $supplier->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            المورد نشط ومتاح في طلبات الشراء وأذون استلام المواد
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ التعديلات
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
