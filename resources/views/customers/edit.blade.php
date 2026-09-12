@extends('layouts.app')

@section('title', 'تعديل بيانات العميل - مصنع مفروشات سدير')
@section('page-title', 'تعديل بيانات العميل: ' . $customer->name)

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 900px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل العملاء
        </a>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-light border text-secondary">
                <i class="fas fa-eye me-1"></i> عرض التفاصيل
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fas fa-user-edit text-warning me-2"></i> تعديل بيانات العميل
                </h5>
                <p class="text-muted fs-7 mb-0">تحديث بيانات الاتصال أو الشروط الائتمانية وقناة البيع.</p>
            </div>
            <code class="bg-light px-3 py-1.5 rounded-3 text-primary fs-6 fw-bold">{{ $customer->customer_code }}</code>
        </div>

        <form action="{{ route('customers.update', $customer) }}" method="POST" x-data="{ isCredit: {{ old('is_credit_customer', $customer->is_credit_customer) ? 'true' : 'false' }} }">
            @csrf
            @method('PUT')

            <div class="row g-3">
                
                <!-- Customer Code (Readonly) -->
                <div class="col-md-4">
                    <label class="form-label text-muted">رمز العميل</label>
                    <input type="text" class="form-control bg-light font-monospace" value="{{ $customer->customer_code }}" readonly disabled>
                </div>

                <!-- Customer Type -->
                <div class="col-md-4">
                    <label for="customer_type_id" class="form-label">تصنيف العميل <span class="text-danger">*</span></label>
                    <select name="customer_type_id" id="customer_type_id" class="form-select @error('customer_type_id') is-invalid @enderror" required>
                        @foreach ($customerTypes as $type)
                            <option value="{{ $type->id }}" {{ old('customer_type_id', $customer->customer_type_id) == $type->id ? 'selected' : '' }}>
                                {{ $type->name_ar }} ({{ $type->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('customer_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Sales Channel -->
                <div class="col-md-4">
                    <label for="default_sales_channel_id" class="form-label">قناة البيع الافتراضية <span class="text-muted fs-8">(اختياري)</span></label>
                    <select name="default_sales_channel_id" id="default_sales_channel_id" class="form-select @error('default_sales_channel_id') is-invalid @enderror">
                        <option value="">-- اختر قناة البيع --</option>
                        @foreach ($salesChannels as $channel)
                            <option value="{{ $channel->id }}" {{ old('default_sales_channel_id', $customer->default_sales_channel_id) == $channel->id ? 'selected' : '' }}>
                                {{ $channel->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('default_sales_channel_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">اسم العميل / المنشأة <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', $customer->name) }}" 
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Commercial Name -->
                <div class="col-md-6">
                    <label for="commercial_name" class="form-label">الاسم التجاري / اسم الشهرة <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="text" 
                           name="commercial_name" 
                           id="commercial_name" 
                           class="form-control @error('commercial_name') is-invalid @enderror" 
                           value="{{ old('commercial_name', $customer->commercial_name) }}">
                    @error('commercial_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Section: Contact Information -->
                <div class="col-12 mt-4 pt-2 border-top">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-address-book text-warning me-1"></i> معلومات الاتصال والعنوان</h6>
                </div>

                <!-- Contact Person -->
                <div class="col-md-4">
                    <label for="contact_person" class="form-label">الشخص المسؤول / جهة الاتصال</label>
                    <input type="text" 
                           name="contact_person" 
                           id="contact_person" 
                           class="form-control @error('contact_person') is-invalid @enderror" 
                           value="{{ old('contact_person', $customer->contact_person) }}">
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
                           value="{{ old('mobile', $customer->mobile) }}">
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
                           value="{{ old('phone', $customer->phone) }}">
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
                           value="{{ old('email', $customer->email) }}">
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
                           value="{{ old('city', $customer->city) }}">
                    @error('city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Address -->
                <div class="col-md-4">
                    <label for="address" class="form-label">العنوان التفصيلي / الحي</label>
                    <input type="text" 
                           name="address" 
                           id="address" 
                           class="form-control @error('address') is-invalid @enderror" 
                           value="{{ old('address', $customer->address) }}">
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Section: Legal & Financial Information -->
                <div class="col-12 mt-4 pt-2 border-top">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-file-invoice-dollar text-warning me-1"></i> البيانات الضريبية والمالية</h6>
                </div>

                <!-- Tax Number -->
                <div class="col-md-4">
                    <label for="tax_number" class="form-label">الرقم الضريبي (15 رقم)</label>
                    <input type="text" 
                           name="tax_number" 
                           id="tax_number" 
                           dir="ltr" 
                           class="form-control font-monospace text-end @error('tax_number') is-invalid @enderror" 
                           value="{{ old('tax_number', $customer->tax_number) }}">
                    @error('tax_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Commercial Registration -->
                <div class="col-md-4">
                    <label for="commercial_registration" class="form-label">السجل التجاري (10 أرقام)</label>
                    <input type="text" 
                           name="commercial_registration" 
                           id="commercial_registration" 
                           dir="ltr" 
                           class="form-control font-monospace text-end @error('commercial_registration') is-invalid @enderror" 
                           value="{{ old('commercial_registration', $customer->commercial_registration) }}">
                    @error('commercial_registration')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Opening Balance -->
                <div class="col-md-4">
                    <label for="opening_balance" class="form-label">الرصيد الافتتاحي (ر.س)</label>
                    <input type="number" 
                           step="0.01" 
                           name="opening_balance" 
                           id="opening_balance" 
                           class="form-control @error('opening_balance') is-invalid @enderror" 
                           value="{{ old('opening_balance', $customer->opening_balance) }}">
                    @error('opening_balance')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Credit Customer Switch -->
                <div class="col-md-6 mt-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="is_credit_customer" 
                                   id="is_credit_customer" 
                                   value="1" 
                                   x-model="isCredit"
                                   {{ old('is_credit_customer', $customer->is_credit_customer) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-7" for="is_credit_customer">
                                عميل آجل / ائتماني (له حد ائتمان مسموح)
                            </label>
                        </div>
                        <small class="text-muted d-block fs-8">
                            العملاء غير الائتمانيين يتعاملون نقداً أو بالدفع المسبق فقط ولا يُسمح لهم برصيد مدين.
                        </small>
                    </div>
                </div>

                <!-- Credit Limit -->
                <div class="col-md-6 mt-3" x-show="isCredit" x-cloak>
                    <div class="p-3 bg-light rounded-3 border">
                        <label for="credit_limit" class="form-label fw-bold">الحد الائتماني الأقصى (ر.س)</label>
                        <input type="number" 
                               step="0.01" 
                               name="credit_limit" 
                               id="credit_limit" 
                               class="form-control @error('credit_limit') is-invalid @enderror" 
                               value="{{ old('credit_limit', $customer->credit_limit ?? '0.00') }}">
                        <small class="text-muted fs-8">الحد الأقصى للمديونية المسموح بها لهذا العميل.</small>
                        @error('credit_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div class="col-12 mt-3">
                    <label for="notes" class="form-label">ملاحظات إضافية</label>
                    <textarea name="notes" 
                              id="notes" 
                              rows="3" 
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $customer->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $customer->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            العميل نشط ومتاح في أوامر البيع وعروض الأسعار
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('customers.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ التعديلات
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
